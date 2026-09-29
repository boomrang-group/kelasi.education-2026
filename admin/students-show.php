<?php
// students-show.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../database/db_connect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$codeEcole = $_SESSION['code_ecole'] ?? '';

$id = isset($_GET['eleve']) && ctype_digit($_GET['eleve']) ? (int)$_GET['eleve'] : 0;

if ($id <= 0) {
    exit("ID invalide.");
}

// ============================
// ÉLÈVE
// ============================
$sql = "
SELECT s.*, TRIM(CONCAT_WS(' ',
    c.classe,
    c.description,
    n.description,
    sec.description,
    opt.description
)) AS class_name
FROM students s
LEFT JOIN classes c ON c.id = s.class_id
LEFT JOIN niveau   n   ON c.niveau  = n.id
LEFT JOIN section  sec ON c.section = sec.id
LEFT JOIN options  opt ON c.options = opt.id
WHERE s.id = :id
";

$params = [':id' => $id];

if ($codeEcole) {
    $sql .= " AND s.code_ecole = :ce";
    $params[':ce'] = $codeEcole;
}

$sql .= " LIMIT 1";

$st = $pdo->prepare($sql);
$st->execute($params);
$student = $st->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    exit("Élève introuvable.");
}

// ============================
// FINANCES (STRUCTURE PRÊTE)
// ============================
// ⚠️ adapte selon ta table (payments / fees / transactions)
$payments = [];

try {
    $st = $pdo->prepare("
        SELECT 
            p.id,
            p.montant_a_payer AS payement,
            p.montant_paye AS amount,
            p.solde AS solde,
            p.date_paiement AS created_at,
            p.statut,
            p.solde
        FROM paiement p
        WHERE p.eleve = :id
        ORDER BY p.id DESC
    ");

    $st->execute([':id' => $id]);
    $payments = $st->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {
    $payments = [];
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | All Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- CSS existants -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php require_once('layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">

                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <div>
                        <h3><?= h($student['first_name'].' '.$student['last_name']) ?></h3>
                        <p class="muted mb-0">Voir les informations détaillées de l'élève</p>
                    </div>
                    <a href="all-students.php" class="btn btn-md btn-secondary">← Retour</a>
                </div>
                
                <div class="row">
                    <!-- ===================== -->
                <!-- INFOS GÉNÉRALES -->
                <!-- ===================== -->
                    <div class="col-lg-6 col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5>Informations générales</h5>
                                <hr>

                                <p><span class="label">Username :</span> <?= h($student['username']) ?></p>
                                <p><span class="label">Email :</span> <?= h($student['email']) ?></p>
                                <p><span class="label">Téléphone :</span> <?= h($student['phone']) ?></p>
                                <p><span class="label">Genre :</span> <?= h($student['gender']) ?></p>
                                <p><span class="label">Date naissance :</span> <?= h($student['date_of_birth']) ?></p>
                                <p><span class="label">Classe :</span> <?= h($student['class_name'] ?? '') ?></p>
                                <p><span class="label">École provenance :</span> <?= h($student['ecole_provenance']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-sm-12">
                        <!-- ===================== -->
                        <!-- RESPONSABLE -->
                        <!-- ===================== -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5>Responsables</h5>
                                <hr>

                                <p><span class="label">Père :</span> <?= h($student['father']) ?></p>
                                <p><span class="label">Mère :</span> <?= h($student['mother']) ?></p>
                                <p><span class="label">Téléphone :</span> <?= h($student['phone_responsable']) ?></p>
                                <p><span class="label">Email :</span> <?= h($student['email_responsable']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- ===================== -->
                <!-- SITUATION FINANCIÈRE -->
                <!-- ===================== -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>💰 Situation financière</h5>
                        <hr>

                        <?php if (!$payments): ?>
                        <p class="text-muted">Aucun paiement enregistré.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>A Payer</th>
                                        <th>Payé</th>
                                        <th class="text-danger">Solde (Reste)</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $p): ?>
                                    <tr>
                                        <td><?= h($p['created_at'] ?? '') ?></td>
                                        <td><?= number_format((float)($p['payement'] ?? 0), 0, ',', ' ') ?> USD</td>
                                        <td><?= number_format((float)($p['amount'] ?? 0), 0, ',', ' ') ?> USD</td>
                                        <td class="text-danger">
                                            <?= number_format((float)($p['solde'] ?? 0), 0, ',', ' ') ?> USD</td>
                                        <td><?= h($p['statut'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once('layout/footer.php'); ?>


    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>

</body>

</html>