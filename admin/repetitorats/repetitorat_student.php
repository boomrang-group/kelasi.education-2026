<?php
// kelasi/admin/repetitorats/repetitorat_student.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

// Inclusion du service de répétitorat (qui ne doit pas redéclarer h())
require_once('service/repetitorat-service-student.php');

// Fonction utilitaire sécurisée anti-redéclaration
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// --- Contexte école ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $st =$pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$uid]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] =$codeEcole;
    }
}

$alertMsg = '';

// --- Traitement du changement de statut ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'update_statut') {
    $resId = (int)($_POST['reservation_id'] ?? 0);
    $newStatut = trim((string)($_POST['statut'] ?? ''));
    if ($resId > 0 && in_array($newStatut, ['en_attente', 'confirmee', 'annulee'], true)) {
        $stUp =$pdo->prepare("UPDATE reservations_repetitorat SET statut = :st WHERE id = :id AND code_ecole = :ce");
        $stUp->execute([':st' =>$newStatut, ':id' => $resId, ':ce' =>$codeEcole]);
        header('Location: repetitorat_student.php?msg=updated'); exit;
    }
}

// --- Traitement de la création du compte (Utilisateur avec rôle 'eleve_encadre' + profil student) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'create_eleve_encadre') {
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $first_name     = trim((string)($_POST['first_name'] ?? ''));
    $last_name      = trim((string)($_POST['last_name'] ?? ''));
    $gender         = trim((string)($_POST['gender'] ?? ''));
    $date_of_birth  = trim((string)($_POST['date_of_birth'] ?? ''));
    $phone          = trim((string)($_POST['phone'] ?? ''));
    $email          = trim((string)($_POST['email'] ?? ''));
    $father         = trim((string)($_POST['father'] ?? ''));
    $phone_resp     = trim((string)($_POST['phone_resp'] ?? ''));
    $username       = trim((string)($_POST['username'] ?? ''));
    $plainPassword  = trim((string)($_POST['password'] ?? '123456'));
    $passwordHash   = password_hash($plainPassword, PASSWORD_BCRYPT);
    $ecole_prov     = trim((string)($_POST['ecole_provenance'] ?? ''));

    try {
        $pdo->beginTransaction();

        // 1. Enregistrement dans la table `users` avec le rôle 'eleve_encadre'
        $stmtUser =$pdo->prepare("
            INSERT INTO users (username, password, email, role, first_name, last_name, phone, code_ecole)
            VALUES (:username, :password, :email, 'eleve_encadre', :first_name, :last_name, :phone, :code_ecole)
        ");
        $stmtUser->execute([
            ':username'   => $username,
            ':password'   => $passwordHash,
            ':email'      => $email !== '' ? $email :$username . '@kelasi.local',
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':phone'      => $phone ?: $phone_resp,
            ':code_ecole' => $codeEcole
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        // 2. Enregistrement du profil dans la table `students` lié via `id_user`
        $stmtStudent =$pdo->prepare("
            INSERT INTO students (
                first_name, last_name, username, gender, date_of_birth, email, phone, 
                password, father, phone_responsable, email_responsable, code_ecole, 
                ecole_provenance, statut, id_user
            ) VALUES (
                :fn, :ln, :un, :gen, :dob, :email, :ph, 
                :pwd, :fat, :ph_resp, :email_resp, :ce, 
                :ep, 'valider', :uid
            )
        ");
        $stmtStudent->execute([
            ':fn'         => $first_name,
            ':ln'         => $last_name,
            ':un'         => $username,
            ':gen'        => $gender,
            ':dob'        => $date_of_birth ?: null,
            ':email'      => $email !== '' ?$email : '',
            ':ph'         => $phone,
            ':pwd'        => $passwordHash, 
            ':fat'        => $father,
            ':ph_resp'    => $phone_resp,
            ':email_resp' => $email,
            ':ce'         => $codeEcole,
            ':ep'         => $ecole_prov,
            ':uid'        => $newUserId
        ]);

        // 3. Mise à jour du statut de la réservation en confirmée
        if ($reservation_id > 0) {
            $upRes =$pdo->prepare("UPDATE reservations_repetitorat SET statut = 'confirmee' WHERE id = :id");
            $upRes->execute([':id' =>$reservation_id]);
        }

        $pdo->commit();$alertMsg = '<div class="alert alert-success">✅ Compte élève encadré créé avec succès (Rôle : <b>eleve_encadre</b>). Mot de passe : <b>' . h($plainPassword) . '</b></div>';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();$alertMsg = '<div class="alert alert-danger">❌ Erreur lors de la création du compte : ' . h($e->getMessage()) . '</div>';
    }
}

// --- Charger les réservations de répétitorat ---
$reservations = [];
try {
    $sql = "
        SELECT 
            id, code_ecole, nom_eleve, postnom_eleve, prenom_eleve, sexe_eleve,
            date_naissance, lieu_naissance, classe, ecole_provenance, phone_eleve,
            nom_parent, whatsapp_parent, phone_parent, matieres, mode,
            jours_souhaites, heure_souhaitee, frequence, statut, created_at
        FROM reservations_repetitorat
    ";

    $params = [];
    if ($codeEcole !== '') {$sql .= " WHERE code_ecole = :ce ";
        $params[':ce'] =$codeEcole;
    }

    $sql .= " ORDER BY id DESC ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $reservations =$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {$reservations = [];
}
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Inscriptions Répétitorat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- CSS existants -->
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../css/animate.min.css">
    <link rel="stylesheet" href="../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>
    <style>
    .search-wrap {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-wrap .form-control {
        max-width: 360px;
    }

    .btn-sm {
        padding: .25rem .5rem;
    }

    .table td,
    .table th {
        vertical-align: middle;
    }

    .chip {
        display: inline-block;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: .1rem .55rem;
        font-size: .8rem;
        background: #fafafa;
    }

    .muted {
        color: #6b7280;
    }

    .badge-status {
        font-size: .85rem;
        padding: .35em .65em;
        border-radius: 4px;
        font-weight: 600;
    }

    .badge-attente {
        background-color: #ffe17d;
        color: #856404;
    }

    .badge-confirmee {
        background-color: #d4edda;
        color: #155724;
    }

    .badge-annulee {
        background-color: #f8d7da;
        color: #721c24;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php require_once('../layout/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php require_once('../layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <!-- Header + Alertes -->
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <div>
                        <h3>Demandes de Répétitorat</h3>
                        <p class="muted mb-0">École : <span class="chip"><?= $codeEcole ? h($codeEcole) : '—' ?></span>
                        </p>
                    </div>
                </div>

                <?= $alertMsg ?>

                <!-- Zone de recherche -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="search-wrap">
                            <input id="tableSearch" type="text" class="form-control"
                                placeholder="Rechercher (Élève, Parent, Classe, Matières, Téléphone)...">
                        </div>
                    </div>
                </div>

                <!-- Table répétitorat -->
                <div class="card height-auto">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="repetitoratTable" class="table display data-table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom de l'élève</th>
                                        <th>Classe</th>
                                        <th>Parent / Tuteur</th>
                                        <th>WhatsApp / Tél</th>
                                        <th>Matières</th>
                                        <th>Mode</th>
                                        <th>Statut</th>
                                        <th>Date Demande</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($reservations): ?>
                                    <?php foreach ($reservations as$res):
                                        $fullName = trim(($res['nom_eleve'] ?? '').' '.($res['postnom_eleve'] ?? '').' '.($res['prenom_eleve'] ?? ''));
                                        $createdAt =$res['created_at'] ? date('d/m/Y H:i', strtotime((string)$res['created_at'])) : '—';
                                        
                                        $st =$res['statut'] ?? 'en_attente';
                                        $badgeClass = 'badge-attente';$stLabel = 'En attente';
                                        if ($st === 'confirmee') {
                                            $badgeClass = 'badge-confirmee';$stLabel = 'Confirmée';
                                        } elseif ($st === 'annulee') {
                                            $badgeClass = 'badge-annulee';$stLabel = 'Annulée';
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?= (int)$res['id'] ?></td>
                                        <td><strong><?= h($fullName) ?></strong></td>
                                        <td><?= h($res['classe'] ?: '—') ?></td>
                                        <td><?= h($res['nom_parent'] ?: '—') ?></td>
                                        <td>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', (string)$res['whatsapp_parent']) ?>"
                                                target="_blank" class="text-success fw-bold">
                                                <i class="fab fa-whatsapp me-1"></i><?= h($res['whatsapp_parent']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 180px;"
                                                title="<?= h($res['matieres']) ?>">
                                                <?= h($res['matieres'] ?: '—') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= h(ucfirst((string)$res['mode'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= $badgeClass ?>"><?= $stLabel ?></span>
                                        </td>
                                        <td><?= h($createdAt) ?></td>
                                        <td class="text-nowrap">
                                            <!-- Bouton Créer Compte -->
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                data-bs-target="#createAccountModal" data-id="<?= (int)$res['id'] ?>"
                                                data-nom="<?= h($res['nom_eleve']) ?>"
                                                data-prenom="<?= h($res['prenom_eleve']) ?>"
                                                data-sexe="<?= h($res['sexe_eleve']) ?>"
                                                data-dob="<?= h($res['date_naissance']) ?>"
                                                data-phone="<?= h($res['phone_eleve']) ?>"
                                                data-parent="<?= h($res['nom_parent']) ?>"
                                                data-whatsapp="<?= h($res['whatsapp_parent']) ?>"
                                                data-ecole="<?= h($res['ecole_provenance']) ?>">
                                                <i class="fa fa-user-plus me-1"></i> Créer Compte
                                            </button>

                                            <!-- Dropdown Changer Statut -->
                                            <div class="dropdown d-inline ms-1">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                    type="button" data-bs-toggle="dropdown">
                                                    Statut
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="confirmee">
                                                            <button type="submit" class="dropdown-item text-success"><i
                                                                    class="fa fa-check me-2"></i>Confirmer</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="annulee">
                                                            <button type="submit" class="dropdown-item text-danger"><i
                                                                    class="fa fa-times me-2"></i>Annuler</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="en_attente">
                                                            <button type="submit" class="dropdown-item text-warning"><i
                                                                    class="fa fa-clock me-2"></i>En attente</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                            <button class="btn btn-sm btn-danger ms-1"
                                                onclick="deleteReservation(<?= (int)$res['id'] ?>)">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center muted">Aucune inscription de répétitorat
                                            trouvée.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php require_once('../layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- MODAL DE CRÉATION DE COMPTE -->
    <div class="modal fade" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title text-white" id="createAccountModalLabel"><i
                                class="fa fa-user-graduate me-2"></i>Créer le compte (Élève Encadré)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_eleve_encadre">
                        <input type="hidden" name="reservation_id" id="modal_reservation_id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom</label>
                                <input type="text" class="form-control" name="last_name" id="modal_last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="first_name" id="modal_first_name"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Genre</label>
                                <select class="form-select" name="gender" id="modal_gender">
                                    <option value="homme">Masculin</option>
                                    <option value="femme">Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" name="date_of_birth" id="modal_dob">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Téléphone de l'élève</label>
                                <input type="text" class="form-control" name="phone" id="modal_phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom du Parent / Tuteur</label>
                                <input type="text" class="form-control" name="father" id="modal_father">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone WhatsApp (Responsable)</label>
                                <input type="text" class="form-control" name="phone_resp" id="modal_whatsapp">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom d'utilisateur (Username)</label>
                                <input type="text" class="form-control" name="username" id="modal_username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mot de passe provisoire</label>
                                <input type="text" class="form-control" name="password" value="123456" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">E-mail (optionnel)</label>
                                <input type="email" class="form-control" name="email" placeholder="eleve@ecole.com">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">École de provenance</label>
                                <input type="text" class="form-control" name="ecole_provenance" id="modal_ecole">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check me-1"></i> Créer le compte
                            utilisateur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/plugins.js"></script>
    <script src="../../js/popper.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/jquery.scrollUp.min.js"></script>
    <script src="../../js/jquery.dataTables.min.js"></script>
    <script src="../../js/main.js"></script>
    <script>
    $(function() {
        // Filtrage instantané
        const $rows = $('#repetitoratTable tbody tr');
        $('#tableSearch').on('input', function() {
            const q = $(this).val().toString().trim().toLowerCase();
            if (!q) {
                $rows.show();
                return;
            }
            $rows.each(function() {
                const txt = $(this).text().toLowerCase();
                $(this).toggle(txt.indexOf(q) !== -1);
            });
        });

        // Remplissage automatique de la modale au clic
        const createModal = document.getElementById('createAccountModal');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;

                document.getElementById('modal_reservation_id').value = button.getAttribute('data-id');
                document.getElementById('modal_last_name').value = button.getAttribute('data-nom');
                document.getElementById('modal_first_name').value = button.getAttribute('data-prenom');
                document.getElementById('modal_gender').value = button.getAttribute('data-sexe');
                document.getElementById('modal_dob').value = button.getAttribute('data-dob');
                document.getElementById('modal_phone').value = button.getAttribute('data-phone');
                document.getElementById('modal_father').value = button.getAttribute('data-parent');
                document.getElementById('modal_whatsapp').value = button.getAttribute('data-whatsapp');
                document.getElementById('modal_ecole').value = button.getAttribute('data-ecole');

                // Génération automatique d'un username propre
                const prenom = button.getAttribute('data-prenom') || '';
                const nom = button.getAttribute('data-nom') || '';
                const baseUser = (prenom + '.' + nom).toLowerCase().normalize("NFD").replace(
                    /[\u0300-\u036f]/g, "").replace(/[^a-z0-9]/g, '');
                document.getElementById('modal_username').value = baseUser || 'eleve.encadre';
            });
        }
    });

    function deleteReservation(id) {
        if (!id) return;
        if (confirm('Voulez-vous vraiment supprimer la réservation #' + id + ' ?')) {
            window.leeLocation = window.location.href; // Optionnel
            window.location.href = 'service/repetitorat-service-student.php?action=delete&id=' + encodeURIComponent(id);
        }
    }
    </script>
</body>

</html>