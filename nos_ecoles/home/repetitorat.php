<?php
// repetitorat.php — Formulaire de réservation de répétitorat
declare(strict_types=1);

require __DIR__ . '/paserelle_url.php';             
require __DIR__ . '/../../database/db_connect.php'; 
require __DIR__ . '/email.php';                      

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!($pdo instanceof PDO)) { http_response_code(500); exit('Base de données indisponible'); }

// Protection CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF_TOKEN = $_SESSION['csrf'];

// Fonctions utilitaires
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function old(string $k, $d=''){ return $_POST[$k] ?? $d; }
function is_phone(?string $s): bool {
    if ($s === null || $s === '') return true;
    return (bool)preg_match('/^[0-9+\s().-]{6,20}$/', $s);
}

function ascii(string $s): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $t !== false ? $t : $s;
}
function slugify(string $s): string {
    $s = strtolower(ascii($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'eleve';
}

// Contexte établissement (Récupération depuis la session ou la requête POST)
$code_ecole   = $_POST['code_ecole'] ?? ($_SESSION['code_ecole'] ?? null);
$handle_ecole = $url_session_ecole ?? ($_SESSION['url_ecole'] ?? null);
$nom_ecole    = $_SESSION['nom_ecole'] ?? ($_SESSION['landingpage']['nom_ecole'] ?? 'Votre établissement');

// Traitement POST
$alert = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException("Session expirée. Veuillez recharger la page.");
        }

        // 1. Élève & Tuteur
        $nom            = trim((string)($_POST['nom'] ?? ''));
        $postnom        = trim((string)($_POST['postnom'] ?? ''));
        $prenom         = trim((string)($_POST['prenom'] ?? ''));
        $sexe           = strtolower(trim((string)($_POST['sexe'] ?? '')));
        $lieu_naissance = trim((string)($_POST['lieu_naissance'] ?? ''));
        $date_naissance = trim((string)($_POST['date_naissance'] ?? ''));
        $classe         = trim((string)($_POST['classe'] ?? ''));
        $ecole          = trim((string)($_POST['ecole'] ?? ''));
        $phone_eleve    = trim((string)($_POST['phone_eleve'] ?? ''));
        $nom_parent     = trim((string)($_POST['nom_parent'] ?? ''));
        $whatsapp_parent= trim((string)($_POST['whatsapp_parent'] ?? ''));
        $phone_parent   = trim((string)($_POST['phone_parent'] ?? ''));

        // 2. Matières
        $matieres       = $_POST['matieres'] ?? [];
        $autre_matiere  = trim((string)($_POST['autre_matiere'] ?? ''));

        // 3. Mode
        $mode           = trim((string)($_POST['mode'] ?? ''));

        // 4. En ligne
        $plateforme_enl  = trim((string)($_POST['plateforme_enl'] ?? ''));
        $whatsapp_enl    = trim((string)($_POST['whatsapp_enl'] ?? ''));
        $email_enl       = trim((string)($_POST['email_enl'] ?? ''));
        $device_enl      = trim((string)($_POST['device_enl'] ?? ''));
        $internet_enl    = trim((string)($_POST['internet_enl'] ?? ''));

        // 5. À domicile
        $commune_dom    = trim((string)($_POST['commune_dom'] ?? ''));
        $quartier_dom   = trim((string)($_POST['quartier_dom'] ?? ''));
        $avenue_dom     = trim((string)($_POST['avenue_dom'] ?? ''));
        $repere_dom     = trim((string)($_POST['repere_dom'] ?? ''));
        $phone_dom      = trim((string)($_POST['phone_dom'] ?? ''));

        // 6. Horaire
        $jours          = $_POST['jours'] ?? [];
        $heure_souhaitee= trim((string)($_POST['heure_souhaitee'] ?? ''));
        $duree          = trim((string)($_POST['duree'] ?? ''));
        $frequence      = trim((string)($_POST['frequence'] ?? ''));

        // 7. Besoin
        $objectifs      = $_POST['objectifs'] ?? [];
        $difficultes    = trim((string)($_POST['difficultes'] ?? ''));

        // 8. Réservation
        $date_debut     = trim((string)($_POST['date_debut'] ?? ''));
        $nb_eleves      = (int)($_POST['nb_eleves'] ?? 1);
        $repetiteur_pref= trim((string)($_POST['repetiteur_pref'] ?? ''));
        $budget         = trim((string)($_POST['budget'] ?? ''));
        $source_canal   = trim((string)($_POST['source_canal'] ?? ''));

        // 9. Confirmations
        $accept_cond    = isset($_POST['accept_cond']);
        $accept_exact   = isset($_POST['accept_exact']);

        // Validations
        if ($nom === '' || $prenom === '') throw new RuntimeException("Le nom et le prénom de l'élève sont requis.");
        if (!in_array($sexe, ['homme', 'femme'], true)) throw new RuntimeException("Veuillez sélectionner le sexe de l'élève.");
        if ($date_naissance === '') throw new RuntimeException("La date de naissance est requise.");
        if ($nom_parent === '') throw new RuntimeException("Le nom du parent/tuteur est requis.");
        if ($whatsapp_parent === '') throw new RuntimeException("Le numéro WhatsApp du parent est requis.");
        if (!is_phone($whatsapp_parent) || !is_phone($phone_parent) || !is_phone($phone_eleve)) {
            throw new RuntimeException("Format de numéro de téléphone invalide.");
        }
        if (empty($matieres)) throw new RuntimeException("Veuillez sélectionner au moins une matière.");
        if ($mode === '') throw new RuntimeException("Veuillez choisir un type de répétitorat.");
        if (!$accept_cond || !$accept_exact) throw new RuntimeException("Veuillez valider les cases de confirmation.");
        if (!$code_ecole) throw new RuntimeException("Code école manquant dans la session.");

        // Traitement de la liste de matières
        $liste_matieres = implode(', ', array_map('e', $matieres));
        if (in_array('Autre', $matieres) && $autre_matiere !== '') {
            $liste_matieres .= " ($autre_matiere)";
        }

        // Insertion directe dans la table reservations_repetitorat
        $sqlR = "INSERT INTO reservations_repetitorat (
            code_ecole, nom_eleve, postnom_eleve, prenom_eleve, sexe_eleve, lieu_naissance, date_naissance,
            classe, ecole_provenance, phone_eleve, nom_parent, whatsapp_parent, phone_parent,
            matieres, autre_matiere, mode, plateforme_enl, whatsapp_enl, email_enl, device_enl, internet_enl,
            commune_dom, quartier_dom, avenue_dom, repere_dom, phone_dom, jours_souhaites, heure_souhaitee,
            duree_seance, frequence, objectifs, difficultes, date_debut, nb_eleves, repetiteur_pref, budget, source_canal
        ) VALUES (
            :code_ecole, :nom_eleve, :postnom_eleve, :prenom_eleve, :sexe_eleve, :lieu_naissance, :date_naissance,
            :classe, :ecole_provenance, :phone_eleve, :nom_parent, :whatsapp_parent, :phone_parent,
            :matieres, :autre_matiere, :mode, :plateforme_enl, :whatsapp_enl, :email_enl, :device_enl, :internet_enl,
            :commune_dom, :quartier_dom, :avenue_dom, :repere_dom, :phone_dom, :jours_souhaites, :heure_souhaitee,
            :duree_seance, :frequence, :objectifs, :difficultes, :date_debut, :nb_eleves, :repetiteur_pref, :budget, :source_canal
        )";

        $stR = $pdo->prepare($sqlR);
        $stR->execute([
            ':code_ecole'       => $code_ecole,
            ':nom_eleve'        => $nom,
            ':postnom_eleve'    => $postnom,
            ':prenom_eleve'     => $prenom,
            ':sexe_eleve'       => $sexe,
            ':lieu_naissance'   => $lieu_naissance,
            ':date_naissance'   => $date_naissance,
            ':classe'           => $classe,
            ':ecole_provenance' => $ecole,
            ':phone_eleve'      => $phone_eleve,
            ':nom_parent'       => $nom_parent,
            ':whatsapp_parent'  => $whatsapp_parent,
            ':phone_parent'     => $phone_parent,
            ':matieres'         => $liste_matieres,
            ':autre_matiere'    => $autre_matiere,
            ':mode'             => $mode,
            ':plateforme_enl'   => $plateforme_enl,
            ':whatsapp_enl'     => $whatsapp_enl,
            ':email_enl'        => $email_enl,
            ':device_enl'       => $device_enl ?: null,
            ':internet_enl'     => $internet_enl ?: null,
            ':commune_dom'      => $commune_dom,
            ':quartier_dom'     => $quartier_dom,
            ':avenue_dom'       => $avenue_dom,
            ':repere_dom'       => $repere_dom,
            ':phone_dom'        => $phone_dom,
            ':jours_souhaites'  => implode(', ', array_map('e', $jours)),
            ':heure_souhaitee'  => $heure_souhaitee !== '' ? $heure_souhaitee : null,
            ':duree_seance'     => $duree,
            ':frequence'        => $frequence,
            ':objectifs'        => implode(', ', array_map('e', $objectifs)),
            ':difficultes'      => $difficultes,
            ':date_debut'       => $date_debut !== '' ? $date_debut : null,
            ':nb_eleves'        => $nb_eleves,
            ':repetiteur_pref'  => $repetiteur_pref,
            ':budget'           => $budget,
            ':source_canal'     => $source_canal
        ]);

        // NOTIFICATION MAIL A L'ADMINISTRATEUR DE L'ECOLE
        try {
            $stmtAdmin = $pdo->prepare("
                SELECT email 
                FROM users 
                WHERE code_ecole = :code_ecole 
                  AND role IN ('admin', 'administrateur', 'promoteur')
                  AND email IS NOT NULL 
                  AND email != ''
                LIMIT 1
            ");
            $stmtAdmin->execute([':code_ecole' => $code_ecole]);
            $adminEmail = trim((string)$stmtAdmin->fetchColumn());

            if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $sujetAdmin = "Nouvelle souscription au Répétitorat - " . $nom_ecole;
                
                $htmlAdmin = '
                <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;">
                    <h2 style="color:#0d6efd;margin-top:0;">Nouvelle souscription au Répétitorat</h2>
                    <p>Un nouvel élève a souscrit au répétitorat pour l\'établissement <strong>' . htmlspecialchars($nom_ecole, ENT_QUOTES, 'UTF-8') . '</strong> (Code : ' . htmlspecialchars((string)$code_ecole, ENT_QUOTES, 'UTF-8') . ').</p>
                    
                    <div style="background-color:#f8f9fa;padding:15px;border-left:4px solid #0d6efd;margin:20px 0;">
                        <p style="margin:4px 0;"><strong>Élève :</strong> ' . htmlspecialchars($nom . ' ' . $postnom . ' ' . $prenom, ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Classe :</strong> ' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Tuteur :</strong> ' . htmlspecialchars($nom_parent, ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>WhatsApp Tuteur :</strong> ' . htmlspecialchars($whatsapp_parent, ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Matières :</strong> ' . htmlspecialchars($liste_matieres, ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Mode de cours :</strong> ' . htmlspecialchars(ucfirst($mode), ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Jours :</strong> ' . htmlspecialchars(implode(', ', $jours), ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin:4px 0;"><strong>Heure / Fréquence :</strong> ' . htmlspecialchars($heure_souhaitee, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($frequence, ENT_QUOTES, 'UTF-8') . ')</p>
                    </div>
                    
                    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
                    <p style="font-size:12px;color:#888;">Ceci est un e-mail automatique envoyé par Kelasi, merci de ne pas y répondre directement.</p>
                </div>';

                mail_html($adminEmail, $sujetAdmin, $htmlAdmin);
            }
        } catch (Throwable $e) {
            // Silence pour ne pas bloquer le traitement principal
        }

        $ok = true;
        $alert = '<div class="alert alert-success">✅ Votre réservation de répétitorat a bien été enregistrée avec succès.</div>';
        $_POST = [];

    } catch (Throwable $e) {
        $alert = '<div class="alert alert-danger">❌ '.$e->getMessage().'</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Réservation — Répétitorat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="img/favicon.ico" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@600&family=Lobster+Two:wght@700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg bg-white navbar-light sticky-top px-4 px-lg-5 py-lg-0">
            <a href="." class="navbar-brand">
                <img src="../../img/logo.png" alt="Logo">
            </a>
            <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav mx-auto"></div>
                <?php if (!empty($handle_ecole)): ?>
                <a href=".?ecole=<?= e($handle_ecole) ?>" class="btn btn-primary rounded-pill px-3 d-none d-lg-block">
                    Accueil <i class="fa fa-arrow-right ms-2"></i>
                </a>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Formulaire -->
        <div class="container mb-5 mt-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <h1 class="text-uppercase mb-2">📝 Formulaire d'inscription — Répétitorat</h1>
                <?php if (!empty($code_ecole)): ?>
                <span class="badge bg-primary fs-6 px-3 py-2 mt-2 mt-md-0">
                    <i class="fa fa-school me-1"></i> Code École : <strong><?= e((string)$code_ecole) ?></strong>
                </span>
                <?php endif; ?>
            </div>
            <p class="text-muted">Optez pour un accompagnement pédagogique sur-mesure pour votre enfant.</p>

            <?= $alert ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form action="" method="POST" novalidate>
                        <input type="hidden" name="csrf" value="<?= e($CSRF_TOKEN) ?>">
                        <?php if (!empty($code_ecole)): ?>
                        <input type="hidden" name="code_ecole" value="<?= e((string)$code_ecole) ?>">
                        <?php endif; ?>

                        <!-- 1. Informations sur l'élève -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i class="fa fa-user me-2"></i>1.
                                Informations sur l'élève</h3>
                            <hr class="mt-1">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" name="nom" class="form-control" required
                                        value="<?= e(old('nom')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Postnom</label>
                                    <input type="text" name="postnom" class="form-control"
                                        value="<?= e(old('postnom')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" name="prenom" class="form-control" required
                                        value="<?= e(old('prenom')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Sexe *</label>
                                    <select class="form-select" name="sexe" required>
                                        <option value="" disabled <?= old('sexe')===''?'selected':''; ?>>Choisir...
                                        </option>
                                        <option value="homme" <?= old('sexe')==='homme'?'selected':''; ?>>Masculin
                                        </option>
                                        <option value="femme" <?= old('sexe')==='femme'?'selected':''; ?>>Féminin
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lieu de naissance</label>
                                    <input type="text" name="lieu_naissance" class="form-control"
                                        value="<?= e(old('lieu_naissance')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date de naissance *</label>
                                    <input type="date" name="date_naissance" class="form-control" required
                                        value="<?= e(old('date_naissance')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Classe / Niveau *</label>
                                    <input type="text" name="classe" class="form-control" required
                                        value="<?= e(old('classe')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">École provenance</label>
                                    <input type="text" name="ecole" class="form-control" value="<?= e(old('ecole')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Téléphone de l'élève <small
                                            class="text-muted">(facultatif)</small></label>
                                    <input type="tel" name="phone_eleve" class="form-control"
                                        value="<?= e(old('phone_eleve')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nom du parent/tuteur *</label>
                                    <input type="text" name="nom_parent" class="form-control" required
                                        value="<?= e(old('nom_parent')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Numéro WhatsApp du parent/tuteur *</label>
                                    <input type="tel" name="whatsapp_parent" class="form-control" required
                                        value="<?= e(old('whatsapp_parent')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Téléphone du parent/tuteur</label>
                                    <input type="tel" name="phone_parent" class="form-control"
                                        value="<?= e(old('phone_parent')) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Matières à étudier -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i class="fa fa-book me-2"></i>2.
                                Matière à étudier</h3>
                            <hr class="mt-1">
                            <div class="row g-2">
                                <?php 
                                $mat_list = ['Mathématiques', 'Français', 'Anglais', 'Cours option', 'Informatique', 'Autre'];
                                $posted_mat = is_array(old('matieres')) ? old('matieres') : [];
                                foreach($mat_list as $mat):
                                ?>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input matiere-checkbox" type="checkbox"
                                            name="matieres[]" value="<?= $mat ?>" id="mat_<?= slugify($mat) ?>"
                                            <?= in_array($mat, $posted_mat) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"
                                            for="mat_<?= slugify($mat) ?>"><?= $mat ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 d-none" id="autre_matiere_box">
                                <label class="form-label">Autre matière (préciser)</label>
                                <input type="text" name="autre_matiere" class="form-control"
                                    value="<?= e(old('autre_matiere')) ?>">
                            </div>
                        </div>

                        <!-- 3. Type de répétitorat -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i
                                    class="fa fa-layer-group me-2"></i>3. Type de répétitorat</h3>
                            <hr class="mt-1">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check card-body border rounded">
                                        <input class="form-check-input" type="radio" name="mode" id="mode_domicile"
                                            value="domicile" <?= old('mode') === 'domicile' ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="mode_domicile">
                                            🏠 À domicile
                                        </label>
                                        <div class="small text-muted">Le répétiteur se déplace chez l'élève</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card-body border rounded">
                                        <input class="form-check-input" type="radio" name="mode" id="mode_ligne"
                                            value="ligne" <?= old('mode') === 'ligne' ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="mode_ligne">
                                            💻 En ligne
                                        </label>
                                        <div class="small text-muted">Cours à distance via la plateforme</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Si le cours est à domicile -->
                        <div class="mb-4 p-3 bg-light border rounded d-none" id="block_dom">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i
                                    class="fa fa-map-marker-alt me-2"></i>5. Localisation — Cours à domicile</h3>
                            <div class="alert alert-warning py-2 my-2 small">
                                ⚠️ Pour la sécurité, l'adresse exacte finale ne sera confirmée qu'après la validation de
                                votre réservation.
                            </div>
                            <hr class="mt-1">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Avenue/Numéro/Rue</label>
                                    <input type="text" name="avenue_dom" class="form-control"
                                        value="<?= e(old('avenue_dom')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quartier</label>
                                    <input type="text" name="quartier_dom" class="form-control"
                                        value="<?= e(old('quartier_dom')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Adresse / Commune</label>
                                    <input type="text" name="commune_dom" class="form-control"
                                        value="<?= e(old('commune_dom')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Référence</label>
                                    <input type="text" name="repere_dom" class="form-control"
                                        value="<?= e(old('repere_dom')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone domicile</label>
                                    <input type="tel" name="phone_dom" class="form-control"
                                        value="<?= e(old('phone_dom')) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 6. Horaire souhaité -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i class="fa fa-clock me-2"></i>6.
                                Horaire souhaité</h3>
                            <hr class="mt-1">
                            <label class="form-label d-block fw-bold">Jours souhaités</label>
                            <div class="row g-2 mb-3">
                                <?php 
                                $jours_list = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                                $posted_jours = is_array(old('jours')) ? old('jours') : [];
                                foreach($jours_list as $j):
                                ?>
                                <div class="col-auto">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="jours[]" value="<?= $j ?>"
                                            id="j_<?= slugify($j) ?>"
                                            <?= in_array($j, $posted_jours) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="j_<?= slugify($j) ?>"><?= $j ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Heure souhaitée</label>
                                    <input type="time" name="heure_souhaitee" class="form-control"
                                        value="<?= e(old('heure_souhaitee')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Durée par séance</label>
                                    <select class="form-select" name="duree">
                                        <option value="1 heure">1 heure</option>
                                        <option value="1 h 30">1 h 30</option>
                                        <option value="2 heures" selected>2 heures</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fréquence</label>
                                    <select class="form-select" name="frequence">
                                        <option value="1 fois/semaine">1 fois/semaine</option>
                                        <option value="2 fois/semaine">2 fois/semaine</option>
                                        <option value="3 fois/semaine" selected>3 fois/semaine</option>
                                        <option value="Tous les jours">Tous les jours</option>
                                        <option value="À définir">À définir</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Besoin de l'élève -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i class="fa fa-bullseye me-2"></i>7.
                                Besoin de l'élève</h3>
                            <hr class="mt-1">
                            <label class="form-label d-block fw-bold">Objectif du répétitorat</label>
                            <div class="row g-2 mb-3">
                                <?php 
                                $obj_list = ['Comprendre les cours', 'Faire les exercices', 'Lecture', 'Ecriture', 'Préparer un contrôle', 'Remonter le niveau', 'Orthographe', 'Aide aux devoirs', 'Autre'];
                                $posted_obj = is_array(old('objectifs')) ? old('objectifs') : [];
                                foreach($obj_list as $o):
                                ?>
                                <div class="col-md-4 col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="objectifs[]"
                                            value="<?= $o ?>" id="obj_<?= slugify($o) ?>"
                                            <?= in_array($o, $posted_obj) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="obj_<?= slugify($o) ?>"><?= $o ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Difficultés particulières / Détails supplémentaires</label>
                                <textarea name="difficultes" class="form-control" rows="3"
                                    placeholder="Expliquez ici les besoins spécifiques ou blocages de l'élève..."><?= e(old('difficultes')) ?></textarea>
                            </div>
                        </div>

                        <!-- 8. Informations sur la réservation -->
                        <div class="mb-4">
                            <h3 class="text-primary text-uppercase fs-5 fw-bold"><i
                                    class="fa fa-clipboard-list me-2"></i>8. Informations sur la réservation</h3>
                            <hr class="mt-1">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date souhaitée pour commencer</label>
                                    <input type="date" name="date_debut" class="form-control"
                                        value="<?= e(old('date_debut')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nombre d'élève(s)</label>
                                    <input type="number" name="nb_eleves" class="form-control" min="1" max="10"
                                        value="<?= e(old('nb_eleves', '1')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Comment avez-vous connu notre répétitorat ?</label>
                                    <select class="form-select" name="source_canal">
                                        <option value="">Sélectionner...</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="École">École</option>
                                        <option value="Ami/connaissance">Ami/connaissance</option>
                                        <option value="Autre">Autre</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 9. Confirmation -->
                        <div class="mb-4 p-3 bg-light border rounded">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="accept_cond" id="accept_cond"
                                    required>
                                <label class="form-check-label" for="accept_cond">
                                    J'accepte les conditions de réservation du programme de répétitorat.
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="accept_exact" id="accept_exact"
                                    required>
                                <label class="form-check-label" for="accept_exact">
                                    Je confirme que l'ensemble des informations fournies sont correctes.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 text-uppercase">
                                Réserver ma place
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <div class="container-fluid bg-dark text-white-50 footer pt-4">
            <div class="container py-3 text-center">
                <small>&copy; Kelasi — Service de Répétitorat & Soutien Scolaire</small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Affichage conditionnel Matières ("Autre")
        const matAutre = document.getElementById('mat_autre');
        const autreBox = document.getElementById('autre_matiere_box');
        if (matAutre && autreBox) {
            matAutre.addEventListener('change', () => {
                autreBox.classList.toggle('d-none', !matAutre.checked);
            });
        }

        // Affichage conditionnel du mode à domicile
        const radioModes = document.querySelectorAll('input[name="mode"]');
        const blockDom = document.getElementById('block_dom');

        function toggleModes() {
            const selected = document.querySelector('input[name="mode"]:checked')?.value;
            if (blockDom) blockDom.classList.toggle('d-none', selected !== 'domicile');
        }

        radioModes.forEach(r => r.addEventListener('change', toggleModes));
        toggleModes();
    });
    </script>
</body>

</html>