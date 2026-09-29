<?php
// admin/paiement.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Connexion DB robuste ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v){ if ($v === null || $v === '') return '—'; return number_format((float)$v, 0, ',', ' ').' $'; }

// --- Contexte école ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$uid]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
    }
}

// --- Récupère les lignes pour un statut donné + la table de config correspondante
// $statut ∈ {'Inscription','Minerval','Autre'}
// $cfgTable ∈ {'frais_d_inscription','minerval','autres_frais'}
function fetchRows(PDO $pdo, string $statut, string $cfgTable, ?string $codeEcole = null): array
{
    $extraCols = '';

    if ($cfgTable === 'autres_frais') {
        $extraCols = ", cfg.description AS autres_frais_description";
    }

    $sql = "
        SELECT
            p.id,
            p.eleve,
            p.montant_a_payer,
            p.montant_paye,
            p.solde,
            p.date_paiement,
            p.statut,

            s.first_name,
            s.last_name,
            s.gender,

            c.classe,
            c.description AS classe_desc,

            n.description   AS niveau_label,
            sec.description AS section_label,
            opt.description AS option_label,

            cfg.montant AS montant_fixe

            {$extraCols}

        FROM paiement p

        INNER JOIN students s
            ON s.id = p.eleve

        LEFT JOIN classes c
            ON c.id = s.class_id

        LEFT JOIN niveau n
            ON n.id = c.niveau

        LEFT JOIN section sec
            ON sec.id = c.section

        LEFT JOIN options opt
            ON opt.id = c.options

        LEFT JOIN {$cfgTable} cfg
            ON cfg.code_ecole = s.code_ecole
           AND cfg.niveau     = c.niveau
           AND cfg.section    = c.section
           AND cfg.`OPTION`   = c.options
           AND cfg.classe     = c.id

        WHERE p.statut = :statut
    ";

    $params = [
        ':statut' => $statut
    ];

    if (!empty($codeEcole)) {
        $sql .= " AND s.code_ecole = :ce";
        $params[':ce'] = $codeEcole;
    }

    $sql .= " ORDER BY p.date_paiement DESC, p.id DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// --- Datasets pour les 3 onglets ---
$rowsInscription = fetchRows($pdo, 'Inscription', 'frais_d_inscription', $codeEcole);
$rowsMinerval    = fetchRows($pdo, 'Minerval',    'minerval',           $codeEcole);
$rowsAutres      = fetchRows($pdo, 'Autre',       'autres_frais',       $codeEcole);

$totalInscription = 0;
$totalMinerval = 0;
$totalAutres = 0;

foreach ($rowsInscription as $r) {
    $totalInscription += (float)($r['montant_paye'] ?? 0);
}
foreach ($rowsMinerval as $r) {
    $totalMinerval += (float)($r['montant_paye'] ?? 0);
}
foreach ($rowsAutres as $r) {
    $totalAutres += (float)($r['montant_paye'] ?? 0);
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Paiement | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    .img-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover
    }

    .muted {
        color: #6b7280
    }

    .text-dange {
        color: #b91c1c;
        font-weight: 600;
    }

    .ui-tab-card .nav-tabs .nav-link {
        margin-right: .5rem
    }

    .table td,
    .table th {
        vertical-align: middle
    }

    i {
        margin-right: 5px;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('layout/navbar.php'); ?>

        <div class="dashboard-page-one">
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="row mt-3">
                    <div class="card ui-tab-card w-100">
                        <div class="card-body">
                            <h3>Finances - Historique des paiements</h3>
                            <p class="muted">Gérer la situation finacière de toute l'école.</p>
                            <div class="icon-tab">
                                <ul class="nav nav-tabs d-flex align-items-center" role="tablist">

                                    <li class="nav-item">
                                        <a class="nav-link border-dark-pastel-green active" data-toggle="tab"
                                            href="#tabInscription">
                                            <i class="fas fa-user-plus"></i> Inscription
                                        </a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link border-dodger-blue" data-toggle="tab" href="#tabMinerval">
                                            <i class="fas fa-coins"></i> Minerval
                                        </a>
                                    </li>

                                    <li class="nav-item">
                                        <a class="nav-link border-orange-peel" data-toggle="tab" href="#tabAutres">
                                            <i class="fas fa-file-invoice-dollar"></i> Autres frais
                                        </a>
                                    </li>

                                    <!-- <li class="ml-auto d-flex">
                                        <a class="btn btn-danger mr-2 btn-lg" href="fixation-frais-scolaire.php">
                                            <i class="fas fa-sliders-h"></i> Fixation des frais
                                        </a>

                                        <a class="btn btn-primary btn-lg" href="paiement_cash.php">
                                            <i class="fas fa-money-bill-wave"></i> Paiement cash
                                        </a>
                                    </li> -->

                                </ul>

                                <div class="tab-content">

                                    <!-- Onglet: Inscription -->
                                    <div class="tab-pane fade show active" id="tabInscription" role="tabpanel">
                                        <div class="col-12-xxxl col-12">
                                            <div class="heading-layout1">
                                                <div class="item-title">
                                                    <h3>Inscription</h3>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table display data-table text-nowrap">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Photo</th>
                                                            <th>Nom</th>
                                                            <th>Classe</th>
                                                            <th>A payer</th>
                                                            <th>Payé</th>
                                                            <th class="text-danger">Solde</th>
                                                            <th>Date de paiement</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($rowsInscription): ?>
                                                        <?php foreach ($rowsInscription as $r):
                                                        $fullName  = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                                        $classeLbl = trim(
                                                            ($r['classe'] ?? '').
                                                            (($r['classe_desc'] ?? '') ? ' '.$r['classe_desc'] : '').
                                                            (($r['niveau_label'] ?? '') ? ' '.$r['niveau_label'] : '').
                                                            (($r['section_label'] ?? '') ? ' '.$r['section_label'] : '').
                                                            (($r['option_label'] ?? '') ? ' '.$r['option_label'] : '')
                                                        );
                                                        if ($classeLbl === '') $classeLbl = '—';
                                                        $img = (strtolower((string)$r['gender']) === 'femme') ? '../img/figure/student.png' : '../img/figure/student1.png';
                                                    ?>
                                                        <tr>
                                                            <td><?= (int)$r['students_id'] ?></td>
                                                            <td class="text-center"><img class="img-avatar"
                                                                    src="<?= h($img) ?>" alt="student"></td>
                                                            <td><?= h($fullName) ?></td>
                                                            <td><?= h($classeLbl) ?></td>
                                                            <td>
                                                                <?= money($r['montant_a_payer'] ?? null) ?></td>
                                                            <td><?= money($r['montant_paye'] ?? null) ?></td>
                                                            <td class="text-danger"><?= money($r['solde'] ?? null) ?></td>
                                                            <td><?= h($r['date_paiement'] ?? '—') ?></td>
                                                            <td class="text-center">
                                                                <button class="btn btn-md btn-primary btn-print"
                                                                    data-toggle="modal" data-target="#recuModal"
                                                                    data-id="<?= (int)$r['id'] ?>"
                                                                    data-statut="Inscription">
                                                                    <i class="fas fa-print"></i> Imprimer
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <?php else: ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center muted">Aucune donnée.
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tr class="bg-light font-weight-bold">
                                                        <td colspan="5" class="text-right">TOTAL DU JOUR</td>
                                                        <td><?= money($totalInscription) ?></td>
                                                        <td colspan="3"></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Onglet: Minerval -->
                                    <div class="tab-pane fade" id="tabMinerval" role="tabpanel">
                                        <div class="col-12-xxxl col-12">
                                            <div class="heading-layout1">
                                                <div class="item-title">
                                                    <h3>Minerval</h3>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table display data-table text-nowrap">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Photo</th>
                                                            <th>Nom</th>
                                                            <th>Classe</th>
                                                            <th>A payer</th>
                                                            <th>Payé</th>
                                                            <th class="text-danger">Solde</th>
                                                            <th>Date de paiement</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($rowsMinerval): ?>
                                                        <?php foreach ($rowsMinerval as $r):
                                                        $fullName  = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                                        $classeLbl = trim(
                                                            ($r['classe'] ?? '').
                                                            (($r['classe_desc'] ?? '') ? ' '.$r['classe_desc'] : '').
                                                            (($r['niveau_label'] ?? '') ? ' '.$r['niveau_label'] : '').
                                                            (($r['section_label'] ?? '') ? ' '.$r['section_label'] : '').
                                                            (($r['option_label'] ?? '') ? ' '.$r['option_label'] : '')
                                                        );
                                                        if ($classeLbl === '') $classeLbl = '—';
                                                        $img = (strtolower((string)$r['gender']) === 'femme') ? '../img/figure/student.png' : '../img/figure/student1.png';
                                                    ?>
                                                        <tr>
                                                            <td><?= (int)$r['students_id'] ?></td>
                                                            <td class="text-center"><img class="img-avatar"
                                                                    src="<?= h($img) ?>" alt="student"></td>
                                                            <td><?= h($fullName) ?></td>
                                                            <td><?= h($classeLbl) ?></td>
                                                            <td>
                                                                <?= money($r['montant_a_payer'] ?? null) ?></td>
                                                            <td><?= money($r['montant_paye'] ?? null) ?></td>
                                                            <td class="text-danger"><?= money($r['solde'] ?? null) ?></td>
                                                            <td><?= h($r['date_paiement'] ?? '—') ?></td>
                                                            <td class="text-center">
                                                                <button class="btn btn-md btn-primary btn-print"
                                                                    data-toggle="modal" data-target="#recuModal"
                                                                    data-id="<?= (int)$r['id'] ?>"
                                                                    data-statut="Minerval">
                                                                    <i class="fas fa-print"></i> Imprimer
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <?php else: ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center muted">Aucune donnée.
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tr class="bg-light font-weight-bold">
                                                        <td colspan="5" class="text-right">TOTAL DU JOUR</td>
                                                        <td><?= money($totalMinerval) ?></td>
                                                        <td colspan="3"></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Onglet: Autres frais -->
                                    <div class="tab-pane fade" id="tabAutres" role="tabpanel">
                                        <div class="col-12-xxxl col-12">
                                            <div class="heading-layout1">
                                                <div class="item-title">
                                                    <h3>Autres frais</h3>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table display data-table text-nowrap">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Photo</th>
                                                            <th>Nom</th>
                                                            <th>Classe</th>
                                                            <th>Description</th>
                                                            <th>A payer</th>
                                                            <th>Payé</th>
                                                            <th class="text-danger">Solde</th>
                                                            <th>Date de paiement</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($rowsAutres): ?>
                                                        <?php foreach ($rowsAutres as $r):
                                                        $fullName  = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                                                        $classeLbl = trim(
                                                            ($r['classe'] ?? '').
                                                            (($r['classe_desc'] ?? '') ? ' '.$r['classe_desc'] : '').
                                                            (($r['niveau_label'] ?? '') ? ' '.$r['niveau_label'] : '').
                                                            (($r['section_label'] ?? '') ? ' '.$r['section_label'] : '').
                                                            (($r['option_label'] ?? '') ? ' '.$r['option_label'] : '')
                                                        );
                                                        if ($classeLbl === '') $classeLbl = '—';
                                                        $img = (strtolower((string)$r['gender']) === 'femme') ? '../img/figure/student.png' : '../img/figure/student1.png';
                                                        $desc = $r['autres_frais_description'] ?? '';
                                                    ?>
                                                        <tr>
                                                            <td><?= (int)$r['students_id'] ?></td>
                                                            <td class="text-center"><img class="img-avatar"
                                                                    src="<?= h($img) ?>" alt="student"></td>
                                                            <td><?= h($fullName) ?></td>
                                                            <td><?= h($classeLbl) ?></td>
                                                            <td><?= h($desc ?: '—') ?></td>
                                                            <td>
                                                                <?= money($r['montant_a_payer'] ?? null) ?></td>
                                                            <td><?= money($r['montant_paye'] ?? null) ?></td>
                                                            <td class="text-danger"><?= money($r['solde'] ?? null) ?></td>
                                                            <td><?= h($r['date_paiement'] ?? '—') ?></td>
                                                            <td class="text-center">
                                                                <button class="btn btn-md btn-primary btn-print"
                                                                    data-toggle="modal" data-target="#recuModal"
                                                                    data-id="<?= (int)$r['id'] ?>" data-statut="Autre">
                                                                    <i class="fas fa-print"></i> Imprimer
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <?php else: ?>
                                                        <tr>
                                                            <td colspan="9" class="text-center muted">Aucune donnée.
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tr class="bg-light font-weight-bold">
                                                        <td colspan="6" class="text-right">TOTAL DU JOUR</td>
                                                        <td><?= money($totalAutres) ?></td>
                                                        <td colspan="2"></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /Autres frais -->

                                </div>
                            </div>
                        </div>
                    </div>

                    <?php require_once('layout/footer.php'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL RECU DE PAIEMENT -->
    <div class="modal fade" id="recuModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white">Aperçu du reçu de paiement</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body" id="recuContent">
                    <div class="text-center p-5">
                        Chargement...
                    </div>
                </div>

                <div class="modal-footer">
                    <button onclick="printRecu()" class="btn btn-success">
                        <i class="fas fa-print"></i> Imprimer
                    </button>

                    <button class="btn btn-secondary" data-dismiss="modal">
                        Fermer
                    </button>
                </div>

            </div>

        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
    let recuFrame = null;

    $('.btn-print').on('click', function() {

        let id = $(this).data('id');
        let statut = $(this).data('statut');

        $('#recuContent').html('<div class="text-center p-5">Chargement...</div>');

        recuFrame = {
            id,
            statut
        };

        $('#recuContent').load(
            'service/recu_ajax.php?paiement_id=' + id
        );
    });

    function printRecu() {
        let content = document.getElementById('recuContent').innerHTML;

        let w = window.open('', '', 'width=900,height=700');
        w.document.write(`
            <html>
            <head>
                <title>Impression Reçu</title>
                <link rel="stylesheet" href="../css/bootstrap.min.css">
                <style>
                    body { padding:20px; font-family:Arial; }
                    @media print {
                        button { display:none; }
                    }
                </style>
            </head>
            <body onload="window.print(); window.close();">
                ${content}
            </body>
            </html>
        `);
        w.document.close();
    }
    </script>
</body>

</html>