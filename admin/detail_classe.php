<?php
// admin/detail_classe.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Connexion DB (chemins robustes) ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_url(string $s): bool { return (bool)preg_match('~^https?://~i', $s); }
function build_public_href(string $type, string $filename): string {
    $filename = trim($filename ?? '');
    if ($filename === '') return '#';
    if (is_url($filename)) return $filename;
    if (preg_match('~^(/|\.{1,2}/|uploads/|assets/)~i', $filename)) return $filename;

    $base = [
        'pdf'   => '../uploads/pdfs/',
        'image' => '../uploads/images/',
        'video' => '../uploads/videos/',
        'audio' => '../uploads/audios/',
        'default' => '../uploads/'
    ];
    $prefix = $base[strtolower($type)] ?? $base['default'];
    return $prefix . rawurlencode(basename($filename));
}

// --- Contexte ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
$classeId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($classeId <= 0) { header("Location: all-class.php?msg=invalid_id"); exit; }

// --- Classe + labels ---
$classe = null;
try {
    $sql = "
        SELECT c.*,
               n.description AS niveau_label,
               s.description AS section_label,
               o.description AS options_label
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau  = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE c.id = :id
    ";
    $params = [':id'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND c.code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $classe = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $classe = null; }
if (!$classe) { header("Location: all-class.php?msg=not_found"); exit; }

// --- Élèves ---
$eleves = [];
try {
    $sql = "SELECT id, first_name, last_name, gender, username, email, created_at
            FROM students
            WHERE class_id = :cid";
    $params = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " ORDER BY id DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $eleves = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// --- Cours ---
$cours = [];
try {
    $sql = "SELECT id, nom, created_at
            FROM cours
            WHERE `class` = :cid";
    $params = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " ORDER BY id DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $cours = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// --- Leçons & contenus ---
$leconsParCours = [];
$leconIds = [];
if ($cours) {
    $ids = array_map(fn($r)=> (int)$r['id'], $cours);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = $pdo->prepare("SELECT id, cours_id, titre, ordre, is_published, created_at
                             FROM lecons
                             WHERE cours_id IN ($in)
                             ORDER BY cours_id, ordre ASC");
        $st->execute($ids);
        $lecons = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($lecons as $L) {
            $leconsParCours[(int)$L['cours_id']][] = $L;
            $leconIds[] = (int)$L['id'];
        }
    } catch (Throwable $e) {}
}
$contenusParLecon = [];
if ($leconIds) {
    $inL = implode(',', array_fill(0, count($leconIds), '?'));
    $rowsLC = [];
    try {
        $st = $pdo->prepare("SELECT id, lecon_id, type_contenu, contenu_id, ordre
                             FROM lecon_contenus
                             WHERE lecon_id IN ($inL)
                             ORDER BY lecon_id, ordre ASC");
        $st->execute($leconIds);
        $rowsLC = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}

    $idsByType = ['pdf'=>[], 'image'=>[], 'video'=>[], 'audio'=>[]];
    foreach ($rowsLC as $r) {
        $t = strtolower((string)$r['type_contenu']);
        if (isset($idsByType[$t])) $idsByType[$t][] = (int)$r['contenu_id'];
    }

    $pdfById = $imgById = $vidById = $audById = [];

    if ($idsByType['pdf']) {
        $in = implode(',', array_fill(0, count($idsByType['pdf']), '?'));
        try {
            $st = $pdo->prepare("SELECT id, title, filename, description, uploaded_at FROM pdfs WHERE id IN ($in)");
            $st->execute($idsByType['pdf']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $pdfById[(int)$row['id']] = $row;
        } catch (Throwable $e) {}
    }
    if ($idsByType['image']) {
        $in = implode(',', array_fill(0, count($idsByType['image']), '?'));
        try {
            $st = $pdo->prepare("SELECT id, title, filename, description, uploaded_at FROM images WHERE id IN ($in)");
            $st->execute($idsByType['image']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $imgById[(int)$row['id']] = $row;
        } catch (Throwable $e) {}
    }
    if ($idsByType['video']) {
        $in = implode(',', array_fill(0, count($idsByType['video']), '?'));
        try {
            $st = $pdo->prepare("SELECT id, title, filename, description, uploaded_at FROM videos WHERE id IN ($in)");
            $st->execute($idsByType['video']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $vidById[(int)$row['id']] = $row;
        } catch (Throwable $e) {}
    }
    if ($idsByType['audio']) {
        $in = implode(',', array_fill(0, count($idsByType['audio']), '?'));
        try {
            $st = $pdo->prepare("SELECT id, titre AS title, fichier AS filename, description, uploaded_at FROM audios WHERE id IN ($in)");
            $st->execute($idsByType['audio']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $audById[(int)$row['id']] = $row;
        } catch (Throwable $e) {}
    }

    foreach ($rowsLC as $r) {
        $lid = (int)$r['lecon_id'];
        $t   = strtolower((string)$r['type_contenu']);
        $cid = (int)$r['contenu_id'];
        $item = null;

        if     ($t==='pdf'   && isset($pdfById[$cid])) $item=$pdfById[$cid];
        elseif ($t==='image' && isset($imgById[$cid])) $item=$imgById[$cid];
        elseif ($t==='video' && isset($vidById[$cid])) $item=$vidById[$cid];
        elseif ($t==='audio' && isset($audById[$cid])) $item=$audById[$cid];

        if ($item) {
            $contenusParLecon[$lid][] = [
                'type'       => $t,
                'id'         => $cid,
                'title'      => (string)($item['title'] ?? ''),
                'filename'   => (string)($item['filename'] ?? ''),
                'uploaded_at'=> (string)($item['uploaded_at'] ?? ''),
                'description'=> (string)($item['description'] ?? ''),
            ];
        }
    }
}

// --- Quizzes ---
$quizzes = [];
try {
    $sql = "SELECT q.id, q.title, q.type_eval, q.mode_questions, q.overall_score,
                   q.due_date, q.is_published, q.created_at, q.updated_at,
                   q.teacher_user_id,
                   u.first_name, u.last_name, u.email
            FROM quizzes q
            LEFT JOIN users u ON u.id = q.teacher_user_id
            WHERE q.class_id = :cid";
    $params = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND q.code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " ORDER BY q.id DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $quizzes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

/* ============================================================
   ENSEIGNANT (source UNIQUE : class_subject_teacher)
   ============================================================ */
$enseignant = null;
$enseignant_source = null;

// Compte d’enseignants pour KPI
$nbTeachers = 0;
try {
    $sqlCnt = "SELECT COUNT(*) FROM class_subject_teacher WHERE class_id = :cid";
    $paramsCnt = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sqlCnt .= " AND code_ecole = :ce"; $paramsCnt[':ce']=$codeEcole; }
    $stCnt = $pdo->prepare($sqlCnt);
    $stCnt->execute($paramsCnt);
    $nbTeachers = (int)$stCnt->fetchColumn();
} catch (Throwable $e) {
    $nbTeachers = 0;
}

// Détail de l’enseignant (onglet)
try {
    $sql = "SELECT id, class_id, teacher_user_id, code_ecole
            FROM class_subject_teacher
            WHERE class_id = :cid";
    $params = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute($params);
    $cst = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($cst && !empty($cst['teacher_user_id'])) {
        $teacherUserId = (int)$cst['teacher_user_id'];

        // 1) teacher.id = teacher_user_id
        $bind = [':tid'=>$teacherUserId];
        $sqlT = "SELECT id, first_name, last_name, email, 'prof' AS role
                 FROM teacher
                 WHERE id = :tid";
        if ($codeEcole !== '') { $sqlT .= " AND code_ecole = :ce"; $bind[':ce'] = $codeEcole; }

        $st2 = $pdo->prepare($sqlT);
        $st2->execute($bind);
        $tRow = $st2->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($tRow && !empty($tRow['first_name'])) {
            $enseignant = $tRow;
            $enseignant_source = 'Teacher';
        } else {
            // 2) users.id = teacher_user_id
            $st3 = $pdo->prepare("SELECT id, first_name, last_name, email, role
                                  FROM users
                                  WHERE id = :uid");
            $st3->execute([':uid'=>$teacherUserId]);
            $uRow = $st3->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($uRow && !empty($uRow['first_name'])) {
                $enseignant = $uRow;
                $enseignant_source = 'Users';
            }
        }
    }
} catch (Throwable $e) {
    // laisser $enseignant = null
}

// KPIs
$nbEleves = count($eleves);
$nbCours  = count($cours);
$nbQuiz   = count($quizzes);

?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Détail Classe</title>
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
    <!-- Chart.js (déjà présent en local minifié, mais on garde le tien) -->
    <script src="../js/Chart.min.js"></script>
    <style>
    .dashboard-summery-one {
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 18px
    }

    .item-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 12px
    }

    .bg-light-green {
        background: #e8f8ef
    }

    .text-green {
        color: #27ae60
    }

    .bg-light-red {
        background: #ffe5e7
    }

    .text-red {
        color: #e74c3c
    }

    .bg-light-yellow {
        background: #fff5dd
    }

    .text-orange {
        color: #f39c12
    }

    .bg-light-blue {
        background: #e7f0ff
    }

    .text-blue {
        color: #1e6bd6
    }

    .item-title {
        font-weight: 600;
        color: #6b7280
    }

    .item-number {
        font-size: 1.4rem;
        font-weight: 700
    }

    .nav-tabs .nav-link {
        border: 0;
        border-bottom: 2px solid transparent
    }

    .nav-tabs .nav-link.active {
        border-color: #1e6bd6;
        font-weight: 600
    }

    .table-sm td,
    .table-sm th {
        padding: .4rem .5rem;
    }

    .kbd {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 2px 6px;
        font-family: ui-monospace, Menlo, Consolas, monospace;
    }

    .lesson-pill {
        display: block;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: .5rem .75rem;
        margin: .35rem 0;
        background: #fafafa
    }

    .badge-pub {
        background: #e7f7ed;
        color: #1b7d3c;
        border: 1px solid #bfe9cf
    }

    .badge-brouillon {
        background: #f9f1e6;
        color: #9a6700;
        border: 1px solid #f3ddba
    }

    .accordion .card-header {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb
    }

    .accordion .btn-link {
        font-weight: 600;
        text-decoration: none
    }

    .badge-soft {
        border: 1px solid rgba(0, 0, 0, .08);
        background: #f6f7f9
    }

    .chip {
        display: inline-flex;
        align-items: center;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: .2rem .55rem;
        margin: .18rem .18rem 0 0;
        background: #fff
    }

    .chip i {
        font-size: .85rem;
        margin-right: .4rem
    }

    .chip a {
        margin-left: .35rem
    }

    .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    .modal-header {
        background: #e53935 !important;
    }

    .modal-body h5 {
        font-weight: 600;
        color: #333;
    }

    .btn-danger {
        background: #e53935;
        border: none;
    }

    .btn-danger:hover {
        background: #c62828;
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
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <h3 style="text-transform: uppercase;">Détail de la classe</h3>
                    <div>
                        <a href="#" class="btn btn-<?= ((int)$classe['status'] === 1) ? 'warning' : 'success' ?> btn-lg"
                            data-id="<?= (int)$classe['id'] ?>"
                            data-label="<?= h(($classe['classe'] ?? '').' '.($classe['description'] ?? '')) ?>"
                            onclick="openStatusModal(this)">
                            <?= ((int)$classe['status'] === 1) ? 'Désactiver' : 'Activer' ?>
                        </a>

                        <a href="add-class.php?id=<?= (int)$classe['id'] ?>" class="btn btn-secondary btn-lg">
                            Modifier cette classe
                        </a>

                        <a href="all-class.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fa fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>

                <!-- Entête classe -->
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                        <div class="mb-2">
                            <h5 class="mb-1"><?= h(($classe['classe'] ?? '').' '.($classe['description'] ?? '')) ?>
                            </h5>
                            <div class="text-muted">
                                Niveau: <?= h($classe['niveau_label'] ?? '—') ?> •
                                Section: <?= h($classe['section_label'] ?? '—') ?> •
                                Option: <?= h($classe['options_label'] ?? '—') ?>
                            </div>
                        </div>
                        <div>
                            <!-- <span class="badge bg-light text-dark">ID #<?= (int)$classe['id'] ?></span> -->
                            <?php if (!empty($classe['code_ecole'])): ?>
                            <span class="badge bg-primary text-white">École: <?= h($classe['code_ecole']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- KPIs -->
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="dashboard-summery-one">
                            <div class="row g-0 align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-green"><i class="fas fa-users text-green"></i>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-title">Élèves</div>
                                    <div class="item-number">
                                        <span><?= number_format($nbEleves, 0, ',', ' ') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="dashboard-summery-one">
                            <div class="row g-0 align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-blue"><i class="fas fa-book-open text-blue"></i>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-title">Cours</div>
                                    <div class="item-number">
                                        <span><?= number_format($nbCours, 0, ',', ' ') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="dashboard-summery-one">
                            <div class="row g-0 align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-yellow"><i
                                            class="fas fa-question-circle text-orange"></i></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-title">Quiz</div>
                                    <div class="item-number"><span><?= number_format($nbQuiz, 0, ',', ' ') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ICI : KPI "Enseignants" = COMPTE -->
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="dashboard-summery-one">
                            <div class="row g-0 align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-red"><i
                                            class="fas fa-chalkboard-teacher text-red"></i></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="item-title">Enseignants</div>
                                    <div class="item-number">
                                        <span><?= number_format($nbTeachers, 0, ',', ' ') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-eleves"
                            role="tab">Élèves</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-cours" role="tab">Cours &
                            Leçons</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-quiz" role="tab">Quiz</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-prof"
                            role="tab">Enseignant</a></li>
                    <li class="d-none nav-item"><a class="nav-link" data-toggle="tab" href="#tab-medias"
                            role="tab">Médias</a></li>
                </ul>

                <div class="tab-content">
                    <!-- ÉLÈVES -->
                    <div class="tab-pane fade show active" id="tab-eleves" role="tabpanel">
                        <div class="card">
                            <div class="card-body table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nom</th>
                                            <th>Sexe</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Créé le</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($eleves): foreach ($eleves as $i=>$el): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= h(($el['first_name'] ?? '').' '.($el['last_name'] ?? '')) ?>
                                            </td>
                                            <td><?= h($el['gender'] ?? '') ?></td>
                                            <td><span class="kbd"><?= h($el['username'] ?? '') ?></span></td>
                                            <td><?= h($el['email'] ?? '') ?></td>
                                            <td><?= h($el['created_at'] ?? '') ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Aucun élève.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- COURS & LECONS — ACCORDÉON + contenus cliquables -->
                    <div class="tab-pane fade" id="tab-cours" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <?php if (!$cours): ?>
                                <div class="text-center text-muted">Aucun cours.</div>
                                <?php else: ?>
                                <div id="accordionCours" class="accordion">
                                    <?php foreach ($cours as $idx=>$c):
                                        $cid = (int)$c['id'];
                                        $Ls  = $leconsParCours[$cid] ?? [];
                                        $pub = 0; foreach ($Ls as $row) { if ((int)($row['is_published'] ?? 0) === 1) $pub++; }
                                        $panelId = 'collapse_course_'.$cid;
                                        $headingId = 'heading_course_'.$cid;
                                        $isFirst = ($idx === 0);
                                    ?>
                                    <div class="card mb-2">
                                        <div class="card-header" id="<?= h($headingId) ?>">
                                            <h5 class="mb-0 d-flex align-items-center justify-content-between">
                                                <button class="btn btn-link" type="button" data-toggle="collapse"
                                                    data-target="#<?= h($panelId) ?>"
                                                    aria-expanded="<?= $isFirst ? 'true':'false' ?>"
                                                    aria-controls="<?= h($panelId) ?>">
                                                    <i class="fas fa-book mr-2"></i>
                                                    <?= h($c['nom']) ?>
                                                </button>
                                                <span class="ml-2">
                                                    <span class="badge badge-soft"><?= h($c['created_at']) ?></span>
                                                    <span class="badge badge-primary"><?= $pub ?> pub.</span>
                                                    <span class="badge badge-secondary"><?= count($Ls) ?>
                                                        leçons</span>
                                                </span>
                                            </h5>
                                        </div>

                                        <div id="<?= h($panelId) ?>" class="collapse<?= $isFirst ? ' show':'' ?>"
                                            aria-labelledby="<?= h($headingId) ?>" data-parent="#accordionCours">
                                            <div class="card-body">
                                                <?php if (empty($Ls)): ?>
                                                <div class="text-muted">Aucune leçon pour ce cours.</div>
                                                <?php else: ?>
                                                <?php foreach ($Ls as $k=>$L):
                            $lid = (int)$L['id'];
                            $items = $contenusParLecon[$lid] ?? [];
                        ?>
                                                <div class="lesson-pill">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong>#<?= (int)$L['ordre'] ?></strong>
                                                            — <?= h($L['titre']) ?>
                                                            <?php if ((int)$L['is_published'] === 1): ?>
                                                            <span class="badge badge-pub ml-1">publiée</span>
                                                            <?php else: ?>
                                                            <span class="badge badge-brouillon ml-1">brouillon</span>
                                                            <?php endif; ?>
                                                            <small
                                                                class="text-muted ml-1"><?= h($L['created_at'] ?? '') ?></small>
                                                        </div>
                                                    </div>

                                                    <?php if ($items): ?>
                                                    <div class="mt-2">
                                                        <?php foreach ($items as $it):
                                        $icon = 'fa-file';
                                        if ($it['type']==='pdf')   $icon='fa-file-pdf';
                                        if ($it['type']==='image') $icon='fa-image';
                                        if ($it['type']==='video') $icon='fa-video';
                                        if ($it['type']==='audio') $icon='fa-headphones';
                                        $fname = $it['filename'];
                                        $href  = build_public_href($it['type'], $fname);
                                    ?>
                                                        <span class="chip" title="<?= h($it['description']) ?>">
                                                            <i class="fas <?= h($icon) ?>"></i>
                                                            <strong><?= strtoupper(h($it['type'])) ?>:</strong>&nbsp;
                                                            <?php if ($href !== '#'): ?>
                                                            <a class="ml-1" target="_blank" rel="noopener"
                                                                href="<?= h($href) ?>">
                                                                <?= h($it['title'] ?: $fname) ?>
                                                            </a>
                                                            <?php else: ?>
                                                            <span
                                                                class="kbd ml-1"><?= h($it['title'] ?: $fname) ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="text-muted mt-2">Aucun contenu (pdf / audio / vidéo
                                                        /
                                                        image) lié.</div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- QUIZ -->
                    <div class="tab-pane fade" id="tab-quiz" role="tabpanel">
                        <div class="card">
                            <div class="card-body table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Titre</th>
                                            <th>Type</th>
                                            <th>Mode</th>
                                            <th>Barème</th>
                                            <th>Échéance</th>
                                            <th>Publié</th>
                                            <th>Prof</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($quizzes): foreach ($quizzes as $i=>$q): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= h($q['title'] ?? '') ?></td>
                                            <td><?= h($q['type_eval'] ?? '') ?></td>
                                            <td><?= h($q['mode_questions'] ?? '') ?></td>
                                            <td><?= h((string)($q['overall_score'] ?? '')) ?></td>
                                            <td><?= h($q['due_date'] ?? '') ?></td>
                                            <td><?= ((int)($q['is_published'] ?? 0) === 1) ? 'Oui' : 'Non' ?></td>
                                            <td><?= h(trim(($q['first_name'] ?? '').' '.($q['last_name'] ?? ''))) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Aucun quiz.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ENSEIGNANT (détail pour l' onglet) -->
                    <div class="tab-pane fade" id="tab-prof" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <?php if ($enseignant): ?>
                                <p class="mb-1"><strong>Enseignant affecté :</strong>
                                    <?= h(trim(($enseignant['first_name'] ?? '').' '.($enseignant['last_name'] ?? ''))) ?>
                                    <?php if (!empty($enseignant['email'])): ?>
                                    <small class="text-muted">(<?= h($enseignant['email']) ?>)</small>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($enseignant['role'])): ?>
                                <p class="text-muted mb-0">Rôle utilisateur : <?= h($enseignant['role']) ?></p>
                                <?php endif; ?>
                                <?php if ($enseignant_source): ?>
                                <!-- <p class="text-muted mt-1">Source: <?= h($enseignant_source) ?> via <code>class_subject_teacher</code></p> -->
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="text-muted mb-0">Aucun enseignant affecté pour cette classe.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MEDIAS — ACCORDÉON + liens cliquables -->
                    <div class="tab-pane fade" id="tab-medias" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <?php
$mediaSections = [
    'PDFs'   => ['key'=>'pdfs',   'icon'=>'fa-file-pdf',  'type'=>'pdf'],
    'Images' => ['key'=>'images', 'icon'=>'fa-image',     'type'=>'image'],
    'Vidéos' => ['key'=>'videos', 'icon'=>'fa-video',     'type'=>'video'],
    'Audios' => ['key'=>'audios', 'icon'=>'fa-headphones','type'=>'audio'],
];
$medias = ['pdfs'=>[], 'images'=>[], 'videos'=>[], 'audios'=>[]];
try {
    foreach (['pdfs'=>'title', 'images'=>'title', 'videos'=>'title'] as $tbl=>$titleCol) {
        $sql = "SELECT id, {$titleCol} AS title, filename, description, uploaded_at
                FROM {$tbl} WHERE `class` = :cid";
        $params = [':cid'=>$classeId];
        if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
        $sql .= " ORDER BY id DESC";
        $st = $pdo->prepare($sql); $st->execute($params);
        $medias[$tbl] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $sql = "SELECT id, titre AS title, fichier AS filename, description, uploaded_at
            FROM audios WHERE `class` = :cid";
    $params = [':cid'=>$classeId];
    if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
    $sql .= " ORDER BY id DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $medias['audios'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

if (empty($medias['pdfs']) && empty($medias['images']) && empty($medias['videos']) && empty($medias['audios'])) {
    echo '<div class="text-center text-muted">Aucun média pour cette classe.</div>';
} else {
    echo '<div id="accordionMedias" class="accordion">';
    $firstDone = false;
    foreach ($mediaSections as $label => $conf) {
        $list = $medias[$conf['key']] ?? [];
        $panelId  = 'collapse_media_'.strtolower($conf['key']);
        $headingId= 'heading_media_'.strtolower($conf['key']);
        $open = !$firstDone && !empty($list);
        if (!empty($list)) { $firstDone = true; }

        echo '<div class="card mb-2">';
        echo '  <div class="card-header" id="'.h($headingId).'">';
        echo '    <h5 class="mb-0 d-flex align-items-center justify-content-between">';
        echo '      <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#'.h($panelId).'" aria-expanded="'.($open?'true':'false').'" aria-controls="'.h($panelId).'">';
        echo '        <i class="fas '.h($conf['icon']).' mr-2"></i>'.h($label).' ('.count($list).')';
        echo '      </button>';
        echo '    </h5>';
        echo '  </div>';
        echo '  <div id="'.h($panelId).'" class="collapse'.($open?' show':'').'" aria-labelledby="'.h($headingId).'" data-parent="#accordionMedias">';
        echo '    <div class="card-body">';

        if (empty($list)) {
            echo '<div class="text-muted">Aucun élément.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-striped">';
            echo '<thead><tr><th>#</th><th>Titre</th><th>Description</th><th>Fichier</th><th>Upload</th></tr></thead><tbody>';
            foreach ($list as $i=>$m) {
                $title = (string)($m['title'] ?? '');
                $file  = (string)($m['filename'] ?? '');
                $href  = build_public_href($conf['type'], $file);
                echo '<tr>';
                echo '  <td>'.($i+1).'</td>';
                echo '  <td>'.h($title !== '' ? $title : $file).'</td>';
                echo '  <td>'.h($m['description'] ?? '').'</td>';
                echo '  <td>';
                if ($href !== '#') {
                    echo '<a target="_blank" rel="noopener" href="'.h($href).'">'.h($file).'</a>';
                } else {
                    echo '<span class="kbd">'.h($file).'</span>';
                }
                echo '  </td>';
                echo '  <td>'.h($m['uploaded_at'] ?? '').'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }
    echo '</div>';
}
?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $foot=__DIR__.'/layout/footer.php'; if(file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="modal fade" id="statusClassModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">⚙️ Changement de statut</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body text-center p-4">
                    <h5 id="classLabel" class="mb-3"></h5>

                    <p class="text-muted">
                        Voulez-vous vraiment changer le statut de cette classe ?
                    </p>
                </div>

                <div class="modal-footer justify-content-center">
                    <a id="confirmStatusBtn" href="#" class="btn btn-warning btn-lg px-4">
                        Confirmer
                    </a>

                    <button type="button" class="btn btn-secondary btn-lg px-4" data-dismiss="modal">
                        Annuler
                    </button>
                </div>

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
    function openStatusModal(el) {
        const id = el.getAttribute('data-id');
        const label = el.getAttribute('data-label');

        document.getElementById('classLabel').innerText = label;

        document.getElementById('confirmStatusBtn').href =
            'all-class.php?toggle_id=' + encodeURIComponent(id);

        $('#statusClassModal').modal('show');
    }
    </script>
</body>

</html>