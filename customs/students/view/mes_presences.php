<?php
// customs/students/view/mes_presences.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../database/db_connect.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Base de données indisponible.');
}

// =======================
// SECURITE
// =======================
$role = strtolower($_SESSION['role'] ?? '');

if ($role !== 'eleve') {
    header('Location: ../../../login/index.php');
    exit;
}

// =======================
// HELPERS
// =======================
function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// =======================
// SESSION
// =======================
$studentId  = (int)($_SESSION['student_id'] ?? 0);
$classId    = (int)($_SESSION['class_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

// =======================
// RECUP ELEVE SI VIDE
// =======================
if (!$studentId) {

    $email = $_SESSION['email'] ?? null;
    $username = $_SESSION['username'] ?? null;

    try {

        if ($email) {

            $sql = "SELECT id, class_id 
                    FROM students 
                    WHERE email = :email
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':email' => $email
            ]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {

                $studentId = (int)$student['id'];
                $classId   = (int)$student['class_id'];

                $_SESSION['student_id'] = $studentId;
                $_SESSION['class_id']   = $classId;
            }
        }

        if (!$studentId && $username) {

            $sql = "SELECT id, class_id 
                    FROM students 
                    WHERE username = :username
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username' => $username
            ]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {

                $studentId = (int)$student['id'];
                $classId   = (int)$student['class_id'];

                $_SESSION['student_id'] = $studentId;
                $_SESSION['class_id']   = $classId;
            }
        }

    } catch(Throwable $e) {

    }
}

// =======================
// MOIS
// =======================
$selectedMonth = $_GET['month'] ?? date('Y-m');

// =======================
// STATS PRESENCE
// =======================
$stats = [
    'present' => 0,
    'absent'  => 0,
    'retard'  => 0
];

$presences = [];

if ($studentId && $classId) {

    try {

        // =======================
        // STATS
        // =======================
        $sql = "
            SELECT
                COUNT(CASE WHEN statut='present' THEN 1 END) AS present,
                COUNT(CASE WHEN statut='absent' THEN 1 END) AS absent,
                COUNT(CASE WHEN statut='retard' THEN 1 END) AS retard
            FROM presence_eleve
            WHERE eleve_id = :student
            AND class_id = :class
            AND DATE_FORMAT(date_presence,'%Y-%m') = :month
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':student' => $studentId,
            ':class'   => $classId,
            ':month'   => $selectedMonth
        ]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

        // =======================
        // DETAILS
        // =======================
        $sql = "
            SELECT *
            FROM presence_eleve
            WHERE eleve_id = :student
            AND class_id = :class
            AND DATE_FORMAT(date_presence,'%Y-%m') = :month
            ORDER BY date_presence DESC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':student' => $studentId,
            ':class'   => $classId,
            ':month'   => $selectedMonth
        ]);

        $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(Throwable $e) {

    }
}

require_once __DIR__ . '/../layout/check_payment.php';
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mes présences | MyKelasi</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
    .card-box {
        border-radius: 15px;
        border: 0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .05);
    }

    .stat-box {
        border-radius: 15px;
        padding: 20px;
        color: white;
    }

    .bg-present {
        background: linear-gradient(135deg, #198754, #20c997);
    }

    .bg-absent {
        background: linear-gradient(135deg, #dc3545, #ff6b6b);
    }

    .bg-retard {
        background: linear-gradient(135deg, #ffc107, #ffcd39);
        color: #212529;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
    }

    .badge-pres {
        padding: 6px 12px;
        border-radius: 30px;
        font-size: .75rem;
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php include '../layout/navbar.php'; ?>

        <div class="dashboard-page-one">

            <?php include '../layout/sidebar.php'; ?>

            <div class="dashboard-content-one">

                <div class="container-fluid py-4">

                    <!-- HEADER -->
                    <div class="d-flex justify-content-between align-items-center mb-4">

                        <div>
                            <h3 class="mb-1">
                                <i class="bi bi-calendar-check text-primary"></i>
                                Mes présences
                            </h3>

                            <p class="text-muted mb-0">
                                Historique de mes présences scolaires
                            </p>
                        </div>

                        <form method="GET" class="d-flex">

                            <input type="month" name="month" value="<?= e($selectedMonth) ?>" class="form-control mr-2">

                            <button class="btn btn-primary">
                                Filtrer
                            </button>

                        </form>

                    </div>

                    <!-- STATS -->
                    <div class="row mb-4">

                        <div class="col-md-4 mb-3">
                            <div class="stat-box bg-present">
                                <div class="small">Présences</div>
                                <div class="stat-number">
                                    <?= (int)$stats['present'] ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="stat-box bg-absent">
                                <div class="small">Absences</div>
                                <div class="stat-number">
                                    <?= (int)$stats['absent'] ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="stat-box bg-retard">
                                <div class="small">Retards</div>
                                <div class="stat-number">
                                    <?= (int)$stats['retard'] ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- TABLE -->
                    <div class="card card-box">

                        <div class="card-body">

                            <h5 class="mb-4">
                                <i class="bi bi-list-check"></i>
                                Détails des présences
                            </h5>

                            <?php if (!$presences): ?>

                            <div class="alert alert-info mb-0">
                                Aucune présence enregistrée pour ce mois.
                            </div>

                            <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover">

                                    <thead class="thead-light">

                                        <tr>
                                            <th>Date</th>
                                            <th>Statut</th>
                                            <th>Commentaire</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach($presences as $p): ?>

                                        <tr>

                                            <td>
                                                <?= e(date('d/m/Y', strtotime($p['date_presence']))) ?>
                                            </td>

                                            <td>

                                                <?php if($p['statut']==='present'): ?>

                                                <span class="badge badge-success badge-pres">
                                                    Présent
                                                </span>

                                                <?php elseif($p['statut']==='absent'): ?>

                                                <span class="badge badge-danger badge-pres">
                                                    Absent
                                                </span>

                                                <?php else: ?>

                                                <span class="badge badge-warning badge-pres">
                                                    Retard
                                                </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>
                                                <?= e($p['commentaire'] ?? '—') ?>
                                            </td>

                                        </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <?php include '../layout/footer.php'; ?>

            </div>

        </div>

    </div>

    <!-- JS -->
    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/plugins.js"></script>
    <script src="../../../js/popper.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script src="../../../js/jquery.counterup.min.js"></script>
    <script src="../../../js/jquery.waypoints.min.js"></script>
    <script src="../../../js/jquery.scrollUp.min.js"></script>
    <script src="../../../js/jquery.dataTables.min.js"></script>
    <script src="../../../js/Chart.min.js"></script>
    <script src="../../../js/main.js"></script>

</body>

</html>