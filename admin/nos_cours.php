<?php
// mykelasi/admin/nos_cours.php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ===============================
// DB
// ===============================
$pdo = null;

foreach (
    [
        __DIR__ . '/../database/db_connect.php',
        __DIR__ . '/../../database/db_connect.php',
        __DIR__ . '/database/db_connect.php'
    ] as $dbFile
) {
    if (file_exists($dbFile)) {
        require_once $dbFile;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur connexion DB');
}

// ===============================
// SESSION
// ===============================
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ===============================
// HELPERS
// ===============================
function e($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function classeLabel(array $r): string
{
    return trim(
        ($r['classe'] ?? '') . ' ' .
        ($r['classe_desc'] ?? '') . ' - ' .
        ($r['niveau'] ?? '') . ' ' .
        ($r['section'] ?? '') . ' ' .
        ($r['opt'] ?? '')
    );
}

// ===============================
// DELETE COURS
// ===============================
if (
    isset($_GET['delete_cours']) &&
    ctype_digit($_GET['delete_cours'])
) {

    $deleteId = (int) $_GET['delete_cours'];

    try {

        $pdo->beginTransaction();

        // Supprimer contenus des leçons
        $sql = "
        DELETE lc
        FROM lecon_contenus lc
        INNER JOIN lecons l ON l.id = lc.lecon_id
        WHERE l.cours_id = :cours
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cours' => $deleteId]);

        // Supprimer leçons
        $stmt = $pdo->prepare("DELETE FROM lecons WHERE cours_id = :cours");
        $stmt->execute([':cours' => $deleteId]);

        // Supprimer quiz liés
        $stmtCoursInfo = $pdo->prepare("SELECT class, teacher_user_id FROM cours WHERE id = :id LIMIT 1");
        $stmtCoursInfo->execute([':id' => $deleteId]);
        $coursInfo = $stmtCoursInfo->fetch(PDO::FETCH_ASSOC);

        if ($coursInfo) {
            $stmtQuiz = $pdo->prepare("
                DELETE FROM quizzes
                WHERE class_id = :class_id AND teacher_user_id = :teacher
            ");
            $stmtQuiz->execute([
                ':class_id' => $coursInfo['class'],
                ':teacher'  => $coursInfo['teacher_user_id']
            ]);
        }

        // Supprimer cours
        $stmt = $pdo->prepare("DELETE FROM cours WHERE id = :id AND code_ecole = :code_ecole");
        $stmt->execute([
            ':id'          => $deleteId,
            ':code_ecole' => $code_ecole
        ]);

        $pdo->commit();

        header('Location: nos_cours.php?success=deleted');
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        die($e->getMessage());
    }
}

// ===============================
// RÉCUPÉRATION COMPLÈTE DES DONNÉES (COURS + LEÇONS + CONTENUS)
// ===============================
$sqlCours = "
SELECT
    crs.id,
    crs.nom,
    crs.created_at,
    crs.teacher_user_id,
    u.first_name,
    u.last_name,
    c.classe,
    c.description AS classe_desc,
    n.description AS niveau,
    s.description AS section,
    o.description AS opt
FROM cours crs
INNER JOIN classes c ON c.id = crs.class
LEFT JOIN users u ON u.id = crs.teacher_user_id
LEFT JOIN niveau n ON n.id = c.niveau
LEFT JOIN section s ON s.id = c.section
LEFT JOIN options o ON o.id = c.options
WHERE crs.code_ecole = :code_ecole
ORDER BY crs.id DESC
";

$stmtCours = $pdo->prepare($sqlCours);
$stmtCours->execute([':code_ecole' => $code_ecole]);
$coursList = $stmtCours->fetchAll(PDO::FETCH_ASSOC);

$fullData = [];

foreach ($coursList as $c) {
    $coursId = (int)$c['id'];
    
    // Récupérer les leçons du cours
    $stmtL = $pdo->prepare("SELECT * FROM lecons WHERE cours_id = :cours ORDER BY ordre ASC");
    $stmtL->execute([':cours' => $coursId]);
    $lecons = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    $leconsData = [];

    foreach ($lecons as $l) {
        $leconId = (int)$l['id'];
        
        // Récupérer les contenus de la leçon
        $stmtC = $pdo->prepare("SELECT * FROM lecon_contenus WHERE lecon_id = :lecon ORDER BY ordre ASC");
        $stmtC->execute([':lecon' => $leconId]);
        $links = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $contenus = [];

        foreach ($links as $link) {
            $type = $link['type_contenu'];
            $cid  = (int)$link['contenu_id'];

            if ($type === 'pdf') {
                $s = $pdo->prepare("SELECT id, title, description FROM pdfs WHERE id = :id");
                $s->execute([':id' => $cid]);
                if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                    $contenus[] = [
                        'type' => 'PDF', 'icon' => 'fa-file-pdf text-danger',
                        'title' => $row['title'], 'desc' => $row['description'],
                        'url' => '../view_pdf.php?id=' . $cid
                    ];
                }
            } elseif ($type === 'video') {
                $s = $pdo->prepare("SELECT id, title, description FROM videos WHERE id = :id");
                $s->execute([':id' => $cid]);
                if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                    $contenus[] = [
                        'type' => 'VIDEO', 'icon' => 'fa-video text-primary',
                        'title' => $row['title'], 'desc' => $row['description'],
                        'url' => '../view_video.php?id=' . $cid
                    ];
                }
            } elseif ($type === 'audio') {
                $s = $pdo->prepare("SELECT id, titre, description FROM audios WHERE id = :id");
                $s->execute([':id' => $cid]);
                if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                    $contenus[] = [
                        'type' => 'AUDIO', 'icon' => 'fa-headphones text-success',
                        'title' => $row['titre'], 'desc' => $row['description'],
                        'url' => '../view_audio.php?id=' . $cid
                    ];
                }
            } elseif ($type === 'image') {
                $s = $pdo->prepare("SELECT id, title, description FROM images WHERE id = :id");
                $s->execute([':id' => $cid]);
                if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                    $contenus[] = [
                        'type' => 'IMAGE', 'icon' => 'fa-image text-warning',
                        'title' => $row['title'], 'desc' => $row['description'],
                        'url' => '../view_image.php?id=' . $cid
                    ];
                }
            }
        }

        $l['contenus'] = $contenus;
        $leconsData[] = $l;
    }

    $c['lecons'] = $leconsData;
    $fullData[] = $c;
}
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Nos cours | MyKelasi</title>
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

    <style>
    /* Tailles de texte augmentées & Adaptations UI */
    body {
        font-size: 1.05rem;
        /* Augmentation globale */
    }

    .accordion-course .card {
        border: 1px solid #e2e8f0;
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        margin-bottom: 18px;
        overflow: hidden;
    }

    .accordion-course .card-header {
        background-color: #ffffff;
        border-bottom: 1px solid #eef2f5;
        padding: 18px 24px;
    }

    .btn-accordion-toggle {
        width: 100%;
        text-align: left;
        padding: 0;
        color: #1e293b;
        font-weight: 700;
        text-decoration: none !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .btn-accordion-toggle:focus {
        box-shadow: none;
    }

    .course-title {
        font-size: 1.25rem;
        /* Augmentation titre cours */
        font-weight: 700;
        color: #0f172a;
    }

    .lesson-item {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .lesson-header {
        padding: 15px 20px;
        font-size: 1.1rem;
        /* Augmentation titre leçon */
        font-weight: 600;
        background: #f8fafc;
    }

    .content-box {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 16px;
        transition: transform 0.2s;
    }

    .content-title {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .content-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.06);
    }

    .badge-soft {
        background-color: #f1f5f9;
        color: #334155;
        font-weight: 600;
        font-size: 0.9rem;
        /* Badge agrandi */
        padding: 6px 12px;
    }

    .search-input-group .form-control {
        border-radius: 10px 0 0 10px;
        font-size: 1.1rem;
        padding: 12px 20px;
    }

    .search-input-group .input-group-text {
        border-radius: 0 10px 10px 0;
        background-color: #0d6efd;
        color: #ffffff;
        padding: 12px 20px;
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

                    <!-- HEADER -->
                    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="mb-1 fw-bold text-dark">
                                <i class="fas fa-layer-group text-primary me-2"></i>
                                Programme des cours
                            </h2>
                            <p class="text-muted mb-0">
                                Consulez et gérez vos cours, leçons et supports pédagogiques
                            </p>
                        </div>

                        <a href="add-edit-cours.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="fas fa-plus-circle me-2"></i>
                            Nouveau cours
                        </a>
                    </div>

                    <?php if(isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 fs-6"
                        role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Suppression effectuée avec succès.
                    </div>
                    <?php endif; ?>

                    <!-- INPUT DE RECHERCHE DYNAMIQUE -->
                    <div class="row mb-4">
                        <div class="col-md-8 col-lg-6">
                            <div class="input-group search-input-group">
                                <input type="text" id="searchCoursInput" class="form-control"
                                    placeholder="Rechercher un cours, une classe ou un prof..." onkeyup="filterCours()">
                                <!-- <span class="input-group-text">
                                    <i class="fas fa-search fa-lg"></i>
                                </span> -->
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION COURS -->
                    <div class="accordion accordion-course" id="accordionCours">

                        <?php if(empty($fullData)): ?>
                        <div class="card p-5 text-center text-muted">
                            <i class="fas fa-folder-open fa-4x mb-3"></i>
                            <h4>Aucun cours disponible</h4>
                        </div>
                        <?php else: ?>

                        <?php foreach($fullData as $index => $c): ?>

                        <div class="card course-card-item"
                            data-search="<?= e(strtolower($c['nom'] . ' ' . classeLabel($c) . ' ' . $c['first_name'] . ' ' . $c['last_name'])) ?>">

                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
                                id="headingCours<?= $c['id'] ?>">

                                <button class="btn btn-accordion-toggle flex-grow-1 collapsed" type="button"
                                    data-toggle="collapse" data-target="#collapseCours<?= $c['id'] ?>"
                                    aria-expanded="false" aria-controls="collapseCours<?= $c['id'] ?>">

                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fas fa-book text-primary fa-2x me-2"></i>
                                        <div>
                                            <div class="course-title"><?= e($c['nom']) ?></div>
                                            <div class="mt-1">
                                                <span class="badge badge-soft me-2">
                                                    <i class="fas fa-graduation-cap me-1"></i><?= e(classeLabel($c)) ?>
                                                </span>
                                                <small class="text-muted fs-6">
                                                    <i class="fas fa-user-tie me-1"></i>Prof :
                                                    <strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <i class="fas fa-chevron-down text-muted fa-lg"></i>

                                </button>

                                <div class="action-btns d-flex gap-2 ms-3">
                                    <!-- EDIT -->
                                    <a href="add-edit-cours.php?id=<?= (int)$c['id'] ?>"
                                        class="btn btn-md btn-light border" title="Modifier">
                                        <i class="fas fa-pen text-secondary fa-lg"></i>
                                    </a>

                                    <!-- DELETE -->
                                    <a href="?delete_cours=<?= (int)$c['id'] ?>" class="btn btn-md btn-light border"
                                        title="Supprimer"
                                        onclick="return confirm('Supprimer ce cours, ses leçons et quiz ?')">
                                        <i class="fas fa-trash-alt text-danger fa-lg"></i>
                                    </a>
                                </div>

                            </div>

                            <!-- LEÇONS DU COURS -->
                            <div id="collapseCours<?= $c['id'] ?>" class="collapse"
                                aria-labelledby="headingCours<?= $c['id'] ?>" data-parent="#accordionCours">

                                <div class="card-body bg-light p-4">

                                    <?php if(empty($c['lecons'])): ?>

                                    <div class="alert alert-warning border-0 mb-0 fs-6">
                                        <i class="fas fa-exclamation-circle me-1"></i> Aucune leçon enregistrée dans ce
                                        cours.
                                    </div>

                                    <?php else: ?>

                                    <h5 class="fw-bold mb-3 text-secondary">
                                        <i class="fas fa-stream me-2"></i>Leçons du cours :
                                    </h5>

                                    <!-- ACCORDION INTERNE LEÇONS -->
                                    <div class="accordion" id="accordionLecons<?= $c['id'] ?>">

                                        <?php foreach($c['lecons'] as $l): ?>

                                        <div class="lesson-item">

                                            <div class="lesson-header d-flex justify-content-between align-items-center"
                                                data-toggle="collapse" data-target="#collapseLecon<?= $l['id'] ?>"
                                                style="cursor: pointer;">

                                                <div class="d-flex align-items-center">
                                                    <span class="badge badge-primary me-3 fs-6">N°
                                                        <?= (int)$l['ordre'] ?></span>
                                                    <span class="text-dark fw-bold"><?= e($l['titre']) ?></span>
                                                </div>

                                                <div class="text-primary fw-bold fs-6">
                                                    <?= count($l['contenus']) ?> contenu(s) <i
                                                        class="fas fa-chevron-down ms-1"></i>
                                                </div>

                                            </div>

                                            <!-- CONTENUS DE LA LEÇON -->
                                            <div class="collapse p-3 border-top" id="collapseLecon<?= $l['id'] ?>"
                                                data-parent="#accordionLecons<?= $c['id'] ?>">

                                                <?php if(empty($l['contenus'])): ?>

                                                <div class="alert alert-info border-0 mb-0 fs-6">
                                                    <i class="fas fa-info-circle me-1"></i> Aucun contenu multimédia
                                                    (PDF, Vidéo, Audio...) dans cette leçon.
                                                </div>

                                                <?php else: ?>

                                                <div class="row">

                                                    <?php foreach($l['contenus'] as $cnt): ?>

                                                    <div class="col-md-6 col-lg-4 mb-3">

                                                        <div class="content-box">

                                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                                <i class="fas <?= e($cnt['icon']) ?> fa-2x me-2"></i>
                                                                <div>
                                                                    <div class="content-title text-dark">
                                                                        <?= e($cnt['title']) ?></div>
                                                                    <span
                                                                        class="badge badge-soft mt-1"><?= e($cnt['type']) ?></span>
                                                                </div>
                                                            </div>

                                                            <p class="text-muted fs-6 mb-3 text-truncate">
                                                                <?= e($cnt['desc']) ?>
                                                            </p>

                                                            <a href="<?= e($cnt['url']) ?>" target="_blank"
                                                                class="btn btn-md btn-outline-primary w-100 fw-bold">
                                                                <i class="fas fa-external-link-alt me-1"></i> Ouvrir le
                                                                document
                                                            </a>

                                                        </div>

                                                    </div>

                                                    <?php endforeach; ?>

                                                </div>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                        <?php endforeach; ?>

                                    </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                        <?php endforeach; ?>

                        <?php endif; ?>

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

    <!-- SCRIPT FILTRE DE RECHERCHE JS -->
    <script>
    function filterCours() {
        const input = document.getElementById('searchCoursInput');
        const filter = input.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.course-card-item');

        cards.forEach(card => {
            const searchData = card.getAttribute('data-search');
            if (searchData.includes(filter)) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });
    }
    </script>

</body>

</html>