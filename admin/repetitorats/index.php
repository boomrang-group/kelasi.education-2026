<?php
// kelasi/admin/repetitorats/index.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

// Connexion DB
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : Base de données indisponible.');
}

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');

// Initialisation des indicateurs
$totalDemandes = 0;
$demandesEnAttente = 0;
$demandesConfirmees = 0;
$demandesAnnulees = 0;
$totalProfesseurs = 0;
$recentEleves = [];
$recentProfesseurs = [];

try {
    // Total des demandes
    $st1 = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat" . ($codeEcole ? " WHERE code_ecole = :ce" : ""));
    $st1->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $totalDemandes = (int)$st1->fetchColumn();

    // Demandes en attente
    $st2 = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'en_attente'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $st2->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesEnAttente = (int)$st2->fetchColumn();

    // Demandes confirmées
    $stConf = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'confirmee'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $stConf->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesConfirmees = (int)$stConf->fetchColumn();

    // Demandes annulées
    $stAnn = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'annulee'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $stAnn->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesAnnulees = (int)$stAnn->fetchColumn();

    // Total des professeurs (rôle 'repetiteur')
    $st3 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'repetiteur'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $st3->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $totalProfesseurs = (int)$st3->fetchColumn();

    // Récupération des 5 derniers élèves (réservations)
    $sqlEl = "SELECT id, nom_eleve, prenom_eleve, classe, statut FROM reservations_repetitorat" . ($codeEcole ? " WHERE code_ecole = :ce" : "") . " ORDER BY id DESC LIMIT 5";
    $stEl = $pdo->prepare($sqlEl);
    $stEl->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $recentEleves = $stEl->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Récupération des 5 derniers répétiteurs
    $sqlPr = "SELECT id, first_name, last_name, username FROM users WHERE role = 'repetiteur'" . ($codeEcole ? " AND code_ecole = :ce" : "") . " ORDER BY id DESC LIMIT 5";
    $stPr = $pdo->prepare($sqlPr);
    $stPr->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $recentProfesseurs = $stPr->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Throwable $e) {
    // Gestion silencieuse si les tables n'ont pas encore la structure complète
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Kelasi | Pilotage du Module Répétitorat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../css/animate.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>
    <style>
    .module-card {
        border: none;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
    }

    .module-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
    }

    .process-step {
        background: #ffffff;
        border: 1px solid #e9ecef;
        border-left: 4px solid #002147;
        border-radius: 6px;
        padding: 20px;
        height: 100%;
    }

    .table td,
    .table th {
        vertical-align: middle;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('../layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('../layout/sidebar.php'); ?>
            <div class="dashboard-content-one">

                <!-- En-tête de la page -->
                <div class="breadcrumbs-area mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Tableau de Bord — Module Répétitorat</h3>
                        <p class="text-muted mb-0">Espace de pilotage centralisé pour la gestion administrative des
                            cours de soutien scolaire.</p>
                    </div>
                    <!-- <div>
                        <span class="badge bg-secondary text-white px-3 py-2">Établissement :
                            <strong><?= $codeEcole ? h($codeEcole) : 'Global' ?></strong></span>
                    </div> -->
                </div>

                <!-- Indicateurs Statistiques (Widgets) -->
                <div class="row gutters-20">
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-primary text-primary"><i
                                            class="flaticon-classmates"></i></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-number"><?= $totalDemandes ?></div>
                                    <div class="item-title">Total Demandes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-yellow text-yellow"><i
                                            class="flaticon-attendance"></i></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-number"><?= $demandesEnAttente ?></div>
                                    <div class="item-title">En attente</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-red text-danger"><i class="flaticon-error"></i></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-number"><?= $demandesAnnulees ?></div>
                                    <div class="item-title">Annulées</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-green text-green"><i class="flaticon-teacher"></i>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-number"><?= $totalProfesseurs ?></div>
                                    <div class="item-title">Répétiteurs Actifs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section des deux tableaux récents (Élèves & Répétiteurs) -->
                <div class="row gutters-20">
                    <!-- Tableau 5 Élèves Récents -->
                    <div class="col-xl-6 col-12 mb-4">
                        <div class="card height-auto h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="card-title text-dark fw-bold mb-0">
                                        <i class="flaticon-classmates text-primary me-2"></i> <span>Élèves</span>
                                    </h4>
                                    <a href="repetitorat_student.php" class="btn btn-lg btn-primary">
                                        Voir tout <i class="fa fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Nom de l'élève</th>
                                                <th>Classe</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentEleves): ?>
                                            <?php foreach ($recentEleves as $el): 
                                                    $nomComplet = trim(($el['nom_eleve'] ?? '') . ' ' . ($el['prenom_eleve'] ?? ''));
                                                    $st = $el['statut'] ?? 'en_attente';
                                                    $badgeBg = $st === 'confirmee' ? 'bg-success text-white' : ($st === 'annulee' ? 'bg-danger text-white' : 'bg-warning text-dark');
                                                ?>
                                            <tr>
                                                <td><strong><?= h($nomComplet) ?></strong></td>
                                                <td><?= h($el['classe'] ?: '—') ?></td>
                                                <td><span class="badge <?= $badgeBg ?>"><?= h(ucfirst(str_replace('_', ' ', $st))) ?></span></td>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Aucun élève récent.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau 5 Répétiteurs Récents -->
                    <div class="col-xl-6 col-12 mb-4">
                        <div class="card height-auto h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="card-title text-dark fw-bold mb-0">
                                        <i class="flaticon-teacher text-success me-2"></i>Répétiteurs Récents
                                    </h4>
                                    <a href="repetitorat_teacher.php" class="btn btn-lg btn-success">
                                        Voir tout <i class="fa fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Nom du Répétiteur</th>
                                                <th>Identifiant (Username)</th>
                                                <th>Rôle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentProfesseurs): ?>
                                            <?php foreach ($recentProfesseurs as $pr): 
                                                    $profNom = trim(($pr['first_name'] ?? '') . ' ' . ($pr['last_name'] ?? ''));
                                                    if (!$profNom) $profNom = $pr['username'];
                                                ?>
                                            <tr>
                                                <td><strong><?= h($profNom) ?></strong></td>
                                                <td><?= h($pr['username']) ?></td>
                                                <td><span class="badge bg-success text-white">repetiteur</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Aucun répétiteur récent.
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php require_once('../layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/plugins.js"></script>
    <script src="../../js/popper.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/jquery.scrollUp.min.js"></script>
    <script src="../../js/main.js"></script>
</body>

</html>