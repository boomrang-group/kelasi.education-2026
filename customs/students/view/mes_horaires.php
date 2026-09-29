<?php
// customs/students/view/mes_horaires.php

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
if (($_SESSION['role'] ?? '') !== 'eleve') {
    header('Location: ../../../login/index.php');
    exit;
}

// =======================
// HELPERS
// =======================
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =======================
// SESSION
// =======================
$studentId  = (int)($_SESSION['student_id'] ?? 0);
$classId    = (int)($_SESSION['class_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

// =======================
// RECUP CLASS SI VIDE
// =======================
if (!$classId && $studentId) {
    try {
        $st = $pdo->prepare("SELECT class_id FROM students WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$studentId]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $classId = (int)$r['class_id'];
            $_SESSION['class_id'] = $classId;
        }
    } catch(Throwable $e) {}
}

// =======================
// HORAIRES
// =======================
$horaires = [];

if ($classId) {
    try {
        $sql = "
            SELECT *
            FROM horaires
            WHERE class_id = :cid
        ";

        if ($code_ecole) {
            $sql .= " AND code_ecole = :ce";
        }

        $sql .= " ORDER BY heure_debut ASC";

        $stmt = $pdo->prepare($sql);

        $params = [':cid'=>$classId];
        if ($code_ecole) $params[':ce'] = $code_ecole;

        $stmt->execute($params);
        $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(Throwable $e) {
        $horaires = [];
    }
}
require_once __DIR__ . '/../layout/check_payment.php';
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mon horaire | MyKelasi</title>
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
    .card-custom {
        border-radius: 1rem;
        border: 0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05);
    }

    .time-badge {
        font-weight: 600;
        color: #0d6efd;
    }

    .course-badge {
        background: #f1f3f5;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .85rem;
        display: inline-block;
    }

    .empty {
        color: #adb5bd;
        font-style: italic;
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php include '../layout/navbar.php'; ?>

        <div class="dashboard-page-one">

            <?php include '../layout/sidebar.php'; ?>

            <div class="dashboard-content-one">

                <!-- HERO STYLE IDENTIQUE -->
                <div class="dashboard-hero mt-4 mb-4">
                    <div>
                        <div class="hero-title">
                            <i class="bi bi-clock-history"></i> Mon horaire de cours
                        </div>
                        <div class="hero-sub">Emploi du temps de ma classe</div>
                    </div>
                    <i class="bi bi-calendar-week hero-icon"></i>
                </div>

                <!-- TABLE CARD -->
                <div class="card card-custom">

                    <div class="card-body">

                        <h6 class="mb-3">
                            <i class="bi bi-table text-primary"></i>
                            Horaire hebdomadaire
                        </h6>

                        <?php if (!$horaires): ?>

                        <div class="text-muted">
                            Aucun horaire disponible pour votre classe.
                        </div>

                        <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-bordered text-center align-middle">

                                <thead class="table-dark">
                                    <tr>
                                        <th>Heure</th>
                                        <th>Lundi</th>
                                        <th>Mardi</th>
                                        <th>Mercredi</th>
                                        <th>Jeudi</th>
                                        <th>Vendredi</th>
                                        <th>Samedi</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach($horaires as $h): ?>

                                    <tr>

                                        <td class="time-badge">
                                            <?= e(substr($h['heure_debut'],0,5)) ?>
                                            -
                                            <?= e(substr($h['heure_fin'],0,5)) ?>
                                        </td>

                                        <?php
                                        $jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi'];
                                        foreach($jours as $j):
                                        ?>

                                        <td>
                                            <?php if(!empty($h[$j])): ?>
                                            <span class="course-badge">
                                                <?= e($h[$j]) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="empty">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <?php endforeach; ?>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php endif; ?>

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