<?php
// customs/prof/view/horaires_cours.php

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

if ($role !== 'prof') {
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
$teacherId  = (int)($_SESSION['user_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

// =======================
// RECUP DES CLASSES DU PROF
// =======================
$classes = [];

try {

    $sql = "
        SELECT DISTINCT
            c.id,
            TRIM(CONCAT(
                COALESCE(c.classe, ''), ' ', 
                COALESCE(c.description, ''), ' ', 
                COALESCE(niv.description, ''), ' ', 
                COALESCE(sec.description, ''), ' ', 
                COALESCE(opt.description, '')
            )) AS classe,
            c.description
        FROM class_subject_teacher cst
        INNER JOIN classes c ON c.id = cst.class_id
        LEFT JOIN options opt ON c.options = opt.id
        LEFT JOIN niveau niv ON c.niveau = niv.id
        LEFT JOIN section sec ON c.section = sec.id
        WHERE cst.teacher_user_id = :teacher
    ";

    if ($code_ecole) {
        $sql .= " AND cst.code_ecole = :code_ecole";
    }

    $sql .= " ORDER BY c.classe ASC";

    $stmt = $pdo->prepare($sql);

    $params = [
        ':teacher' => $teacherId
    ];

    if ($code_ecole) {
        $params[':code_ecole'] = $code_ecole;
    }

    $stmt->execute($params);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {
    $classes = [];
}

// =======================
// CLASSE SELECTIONNEE
// =======================
$selectedClass = (int)($_GET['class_id'] ?? 0);

if (!$selectedClass && !empty($classes)) {
    $selectedClass = (int)$classes[0]['id'];
}

// =======================
// SECURITE CLASSE
// =======================
$classIds = array_column($classes, 'id');

if ($selectedClass && !in_array($selectedClass, array_map('intval', $classIds), true)) {
    exit('Accès refusé.');
}

// =======================
// HORAIRES DE LA CLASSE
// =======================
$horaires = [];

if ($selectedClass) {

    try {

        $sql = "
            SELECT *
            FROM horaires
            WHERE class_id = :class_id
        ";

        if ($code_ecole) {
            $sql .= " AND code_ecole = :code_ecole";
        }

        $sql .= " ORDER BY heure_debut ASC";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':class_id' => $selectedClass
        ];

        if ($code_ecole) {
            $params[':code_ecole'] = $code_ecole;
        }

        $stmt->execute($params);

        $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(Throwable $e) {
        $horaires = [];
    }
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Horaires des cours | MyKelasi</title>

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

    <style>
    .card-box {
        border-radius: 15px;
        border: 0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .05);
    }

    .table th {
        white-space: nowrap;
        vertical-align: middle;
    }

    .table td {
        vertical-align: middle;
    }

    .course-box {
        min-width: 120px;
        padding: 8px;
        border-radius: 10px;
        background: #f8f9fa;
        text-align: center;
        font-size: .9rem;
        font-weight: 600;
    }

    .empty-course {
        color: #adb5bd;
    }

    .time-badge {
        font-size: .85rem;
        padding: 7px 12px;
        border-radius: 30px;
        background: #0d6efd;
        color: white;
        font-weight: 600;
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
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">

                        <div class="mb-3">
                            <h3 class="mb-1">
                                <i class="bi bi-calendar-week text-primary"></i>
                                Horaires des cours
                            </h3>

                            <p class="text-muted mb-0">
                                Consultez les horaires des classes que vous enseignez
                            </p>
                        </div>

                        <!-- FILTRE CLASSE -->
                        <form method="GET">

                            <select name="class_id" class="form-control" onchange="this.form.submit()">

                                <?php foreach($classes as $c): ?>

                                <option value="<?= (int)$c['id'] ?>"
                                    <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>

                                    <?= e($c['classe']) ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                        </form>

                    </div>

                    <!-- TABLE -->
                    <div class="card card-box">

                        <div class="card-body">

                            <h5 class="mb-4">
                                <i class="bi bi-table"></i>
                                Horaire hebdomadaire
                            </h5>

                            <?php if (!$horaires): ?>

                            <div class="alert alert-info mb-0">
                                Aucun horaire disponible pour cette classe.
                            </div>

                            <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover">

                                    <thead class="thead-light">

                                        <tr>
                                            <th>Horaire</th>
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

                                            <td style="min-width:150px">

                                                <span class="time-badge">

                                                    <?= e(substr($h['heure_debut'],0,5)) ?>
                                                    -
                                                    <?= e(substr($h['heure_fin'],0,5)) ?>

                                                </span>

                                            </td>

                                            <td>
                                                <?php if(!empty($h['lundi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['lundi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if(!empty($h['mardi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['mardi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if(!empty($h['mercredi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['mercredi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if(!empty($h['jeudi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['jeudi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if(!empty($h['vendredi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['vendredi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if(!empty($h['samedi'])): ?>
                                                <div class="course-box">
                                                    <?= e($h['samedi']) ?>
                                                </div>
                                                <?php else: ?>
                                                <span class="empty-course">—</span>
                                                <?php endif; ?>
                                            </td>

                                        </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                            <?php endif; ?>

                        </div>
                        <?php include '../layout/footer.php'; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>

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