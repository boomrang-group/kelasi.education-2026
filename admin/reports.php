<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = null;

// ================= DB =================
foreach ([
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
] as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Erreur DB");
}

// ================= SECURITY =================
$userId  = $_SESSION['user_id'] ?? null;
$role    = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin', 'administrateur'], true);

$codeEcole = $_SESSION['code_ecole'] ?? '';

if (!$codeEcole && $userId) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=?");
    $st->execute([$userId]);
    $codeEcole = (string)$st->fetchColumn();
    $_SESSION['code_ecole'] = $codeEcole;
}

if (!$isAdmin || !$codeEcole) {
    http_response_code(403);
    exit("Accès refusé");
}

// ================= FILTER =================
$mois = $_GET['mois'] ?? date('Y-m');

// ================= HELPERS =================
function fetchSum(PDO $pdo, string $sql, array $params): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

function fetchAll(PDO $pdo, string $sql, array $params): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================= DATA =================
$startDate = $mois . '-01';
$endDate   = date("Y-m-t", strtotime($startDate));

$recette = fetchSum($pdo,
    "SELECT COALESCE(SUM(montant_paye),0)
     FROM paiement
     WHERE code_ecole=:ce
     AND DATE(date_paiement) BETWEEN :d1 AND :d2",
    [':ce'=>$codeEcole, ':d1'=>$startDate, ':d2'=>$endDate]
);

$depense = fetchSum($pdo,
    "SELECT COALESCE(SUM(montant),0)
     FROM depenses
     WHERE code_ecole=:ce
     AND date_depense BETWEEN :d1 AND :d2",
    [':ce'=>$codeEcole, ':d1'=>$startDate, ':d2'=>$endDate]
);

$solde = $recette - $depense;

$paiements = fetchAll($pdo,
    "SELECT * FROM paiement
     WHERE code_ecole=:ce
     AND DATE(date_paiement) BETWEEN :d1 AND :d2
     ORDER BY date_paiement DESC",
    [':ce'=>$codeEcole, ':d1'=>$startDate, ':d2'=>$endDate]
);

$depenses = fetchAll($pdo,
    "SELECT * FROM depenses
     WHERE code_ecole=:ce
     AND date_depense BETWEEN :d1 AND :d2
     ORDER BY date_depense DESC",
    [':ce'=>$codeEcole, ':d1'=>$startDate, ':d2'=>$endDate]
);
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Rapport Financier</title>

    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../style.css">

    <style>
    /* ===== GLOBAL ===== */
    body {
        background: #f4f7f6;
    }

    .stat-card {
        border-radius: 18px;
        padding: 18px 20px;
        color: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        transition: 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    /* icon circle */
    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
    }

    .icon-box i {
        font-size: 22px;
    }

    /* colors */
    .green {
        background: linear-gradient(135deg, #0064b0, #0064b0);
    }

    .red {
        background: linear-gradient(135deg, #e68139, #e68139);
    }

    .dark {
        background: linear-gradient(135deg, #222728, #222728);
    }

    /* ===== TABLE ===== */
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
        transition: 0.3s;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .card-header {
        font-weight: 600;
    }

    /* ===== LAYOUT CLEAN ===== */
    .page-container {
        padding: 20px;
    }
    </style>

</head>

<body>

    <div class="wrapper">

        <!-- ================= NAVBAR ================= -->
        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">

            <!-- ================= SIDEBAR ================= -->
            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one page-container">

                <!-- TITLE -->
                <div class="breadcrumbs-area">
                    <h3><i class="fas fa-chart-line"></i> Rapport Financier</h3>
                </div>

                <!-- FILTER -->
                <div class="card p-3 mb-4">
                    <form method="GET" class="d-flex gap-2">
                        <input type="month" name="mois" value="<?= htmlspecialchars($mois) ?>" class="form-control"
                            style="max-width:200px;">
                        <button class="btn btn-success">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </form>
                </div>

                <!-- STATS -->
                <div class="row mb-4">

                    <!-- RECETTES -->
                    <div class="col-md-4 mb-3">
                        <div class="stat-card green d-flex align-items-center justify-content-between">

                            <div class="d-flex align-items-center">
                                <div class="icon-box">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="ml-3">
                                    <h6 class="mb-1 text-white">Recettes</h6>
                                    <h4 class="mb-0 text-white"><?= number_format($recette,2) ?> $</h4>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- DEPENSES -->
                    <div class="col-md-4 mb-3">
                        <div class="stat-card red d-flex align-items-center justify-content-between">

                            <div class="d-flex align-items-center">
                                <div class="icon-box">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="ml-3">
                                    <h6 class="mb-1 text-white">Dépenses</h6>
                                    <h4 class="mb-0 text-white"><?= number_format($depense,2) ?> $</h4>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- SOLDE -->
                    <div class="col-md-4 mb-3">
                        <div class="stat-card dark d-flex align-items-center justify-content-between">

                            <div class="d-flex align-items-center">
                                <div class="icon-box">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="ml-3">
                                    <h6 class="mb-1 text-white">Solde</h6>
                                    <h4 class="mb-0 text-white"><?= number_format($solde,2) ?> $</h4>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- TABLES -->
                <div class="row mb-3">

                    <!-- RECETTES -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-arrow-down"></i> Recettes du mois encours
                            </div>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                    </tr>
                                    <?php foreach ($paiements as $p): ?>
                                    <tr>
                                        <td><?= $p['date_paiement'] ?></td>
                                        <td><?= number_format($p['montant_paye'],2) ?> $</td>
                                        <td><?= htmlspecialchars($p['mode']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row mt-2">
                    <!-- DEPENSES -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-arrow-up"></i> Dépenses du mois encours
                            </div>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <tr>
                                        <th>Date</th>
                                        <th>Bénéficiaire</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                    </tr>
                                    <?php foreach ($depenses as $d): ?>
                                    <tr>
                                        <td><?= $d['date_depense'] ?></td>
                                        <td><?= $d['beneficiaire'] ?></td>
                                        <td><?= $d['description'] ?> </td>
                                        <td><?= $d['montant'] ?> $</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>

            </div>
        </div>
    </div>

    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/all.min.js"></script>

</body>

</html>