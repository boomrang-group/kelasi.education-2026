<?php
// mykelasi/admin/creer_update_cours.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../database/db_connect.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Base de données indisponible.');
}

// =========================
// SECURITE
// =========================
$role = strtolower($_SESSION['role'] ?? '');

if (!in_array($role, ['admin', 'manager'])) {
    header('Location: ../login/index.php');
    exit;
}

// =========================
// HELPERS
// =========================
function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// =========================
// SESSION
// =========================
$code_ecole = $_SESSION['code_ecole'] ?? null;

// =========================
// VARIABLES
// =========================
$success = '';
$error   = '';

$cours_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$nom              = '';
$class_id         = '';
$teacher_user_id  = '';

$editing = false;

// =========================
// CHARGER COURS
// =========================
if ($cours_id > 0) {

    $editing = true;

    try {

        $sql = "
            SELECT *
            FROM cours
            WHERE id = :id
            AND code_ecole = :code_ecole
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':id'          => $cours_id,
            ':code_ecole' => $code_ecole
        ]);

        $cours = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cours) {
            exit('Cours introuvable.');
        }

        $nom             = $cours['nom'];
        $class_id        = $cours['class'];
        $teacher_user_id = $cours['teacher_user_id'];

    } catch(Throwable $e) {

        $error = $e->getMessage();
    }
}

// =========================
// CLASSES
// =========================
$classes = [];

try {

    $sql = "
        SELECT 
            c.id,
            CONCAT(
                c.classe,
                c.description,
                ' - ',
                IFNULL(n.description,''),
                ' ',
                IFNULL(s.description,''),
                ' ',
                IFNULL(o.description,'')
            ) AS classe_label
        FROM classes c
        LEFT JOIN niveau n ON c.niveau = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE c.code_ecole = :code_ecole
        ORDER BY classe_label ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':code_ecole' => $code_ecole
    ]);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {

}

// =========================
// PROFS SELON CLASSE
// =========================
$teachers = [];

if (!empty($class_id)) {

    try {

        $sql = "
            SELECT 
                u.id,
                CONCAT(
                    IFNULL(u.first_name,''), 
                    ' ', 
                    IFNULL(u.last_name,'')
                ) AS professeur

            FROM class_subject_teacher cst

            INNER JOIN users u 
                ON u.id = cst.teacher_user_id

            WHERE cst.class_id = :class_id
            AND cst.code_ecole = :code_ecole

            GROUP BY u.id
            ORDER BY professeur ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':class_id'   => $class_id,
            ':code_ecole' => $code_ecole
        ]);

        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(Throwable $e) {

    }
}

// =========================
// SAVE
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom             = trim($_POST['nom'] ?? '');
    $class_id        = (int)($_POST['class_id'] ?? 0);
    $teacher_user_id = (int)($_POST['teacher_user_id'] ?? 0);

    if (
        empty($nom) ||
        empty($class_id) ||
        empty($teacher_user_id)
    ) {

        $error = "Tous les champs sont obligatoires.";

    } else {

        try {

            if ($editing) {

                $sql = "
                    UPDATE cours SET
                        nom = :nom,
                        class = :class_id,
                        teacher_user_id = :teacher_user_id
                    WHERE id = :id
                    AND code_ecole = :code_ecole
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nom'             => $nom,
                    ':class_id'        => $class_id,
                    ':teacher_user_id' => $teacher_user_id,
                    ':id'              => $cours_id,
                    ':code_ecole'      => $code_ecole
                ]);

                $success = "Cours modifié avec succès.";

            } else {

                $sql = "
                    INSERT INTO cours(
                        nom,
                        class,
                        teacher_user_id,
                        code_ecole
                    ) VALUES(
                        :nom,
                        :class_id,
                        :teacher_user_id,
                        :code_ecole
                    )
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nom'             => $nom,
                    ':class_id'        => $class_id,
                    ':teacher_user_id' => $teacher_user_id,
                    ':code_ecole'      => $code_ecole
                ]);

                $success = "Cours créé avec succès.";
            }

        } catch(Throwable $e) {

            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>

    <meta charset="utf-8">

    <title>
        <?= $editing ? 'Modifier cours' : 'Créer cours' ?>
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/fullcalendar.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">

    <script src="../js/modernizr-3.6.0.min.js"></script>
    <script src="../js/Chart.min.js"></script>

    <style>
    .card-box {
        border-radius: 15px;
        border: 0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .05);
    }
    </style>

</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php include 'layout/navbar.php'; ?>

        <div class="dashboard-page-one">

            <?php include 'layout/sidebar.php'; ?>

            <div class="dashboard-content-one">

                <div class="container-fluid py-4">

                    <div class="d-flex justify-content-between align-items-center mb-4">

                        <div>

                            <h3 class="mb-1">

                                <i class="fas fa-book text-primary"></i>

                                <?= $editing ? 'Modifier le cours' : 'Créer un cours' ?>

                            </h3>

                            <p class="text-muted mb-0">
                                Gestion des cours
                            </p>

                        </div>

                        <a href="nos_cours.php" class="btn btn-secondary btn-md px-4">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour
                        </a>
                    </div>

                    <?php if($success): ?>

                    <div class="alert alert-success">
                        <?= e($success) ?>
                    </div>

                    <?php endif; ?>

                    <?php if($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                    <?php endif; ?>

                    <div class="card card-box">

                        <div class="card-body">

                            <form method="POST">

                                <!-- NOM -->
                                <div class="form-group">

                                    <label>
                                        Nom du cours
                                    </label>

                                    <input type="text" name="nom" class="form-control" required value="<?= e($nom) ?>">

                                </div>

                                <!-- CLASSE -->
                                <div class="form-group">

                                    <label>
                                        Classe
                                    </label>

                                    <select name="class_id" id="class_id" class="form-control" required>

                                        <option value="">
                                            Choisir une classe
                                        </option>

                                        <?php foreach($classes as $c): ?>

                                        <option value="<?= $c['id'] ?>"
                                            <?= ($class_id == $c['id']) ? 'selected' : '' ?>>

                                            <?= e($c['classe_label']) ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <!-- PROF -->
                                <div class="form-group">

                                    <label>
                                        Professeur
                                    </label>

                                    <select name="teacher_user_id" id="teacher_user_id" class="form-control" required>

                                        <option value="">
                                            Choisir un professeur
                                        </option>

                                        <?php foreach($teachers as $t): ?>

                                        <option value="<?= $t['id'] ?>"
                                            <?= ($teacher_user_id == $t['id']) ? 'selected' : '' ?>>

                                            <?= e($t['professeur']) ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <button class="btn btn-success mt-4">

                                    <?= $editing ? 'Modifier' : 'Créer le cours' ?>

                                </button>

                            </form>

                        </div>

                    </div>

                </div>

                <?php include 'layout/footer.php'; ?>

            </div>

        </div>

    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.counterup.min.js"></script>
    <script src="../js/moment.min.js"></script>
    <script src="../js/jquery.waypoints.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/fullcalendar.min.js"></script>
    <script src="../js/Chart.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
    // =============================
    // CHARGER LES PROFS SELON CLASSE
    // =============================
    document.getElementById('class_id').addEventListener('change', function() {

        let classId = this.value;

        fetch('load_teachers_by_class.php?class_id=' + classId)

            .then(response => response.json())

            .then(data => {

                let select = document.getElementById('teacher_user_id');

                select.innerHTML = `
            <option value="">
                Choisir un professeur
            </option>
        `;

                data.forEach(function(item) {

                    select.innerHTML += `
                <option value="${item.id}">
                    ${item.professeur}
                </option>
            `;

                });

            });

    });
    </script>

</body>

</html>