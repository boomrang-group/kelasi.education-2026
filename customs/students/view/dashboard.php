<?php
// customs/students/view/dashboard.php
// Tableau de bord élève — version améliorée (UI + Chart.js + respect structure)

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur: DB indisponible'); }

// --------- ACCÈS ----------
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($role !== 'eleve') { header('Location: ../../../login/index.php'); exit; }

// --------- HELPERS ----------
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function up(?string $s): string { return mb_strtoupper(trim((string)$s), 'UTF-8'); }
function file_exists_rel(string $relFromThisFile): bool {
    $abs = realpath(__DIR__ . '/' . $relFromThisFile);
    if ($abs === false) $abs = __DIR__ . '/' . $relFromThisFile;
    return is_file($abs);
}
function web_path(string $rel): string { return $rel; }

// --------- CONTEXTE SESSION ----------
$code_ecole  = $_SESSION['code_ecole'] ?? null;
$studentId   = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : null;
$classId     = isset($_SESSION['class_id'])   ? (int)$_SESSION['class_id']   : null;
$studentName = $_SESSION['username'] ?? 'Élève';
$email       = $_SESSION['email'] ?? null;
$username    = $_SESSION['username'] ?? null;

// Détection de l'ID utilisateur (users.id) selon les différentes clés de session possibles
$userId = $_SESSION['user_id'] 
          ?? $_SESSION['id'] 
          ?? $_SESSION['id_user'] 
          ?? ($_SESSION['user']['id'] ?? null);

// Récupérer l'élève et Mettre à jour id_user dans la table students
try {
    // 1. Si on n'a pas encore studentId, on le cherche d'abord par id_user
    if (!$studentId && $userId) {
        $st = $pdo->prepare("SELECT id, class_id, first_name, last_name FROM students WHERE id_user = :uid LIMIT 1");
        $st->execute([':uid' => (int)$userId]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$r['id']; 
            $_SESSION['student_id'] = $studentId;
            if (!$classId && !empty($r['class_id'])) { $classId = (int)$r['class_id']; $_SESSION['class_id'] = $classId; }
            $studentName = trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: $studentName;
        }
    }

    // 2. Recherche par Email
    if (!$studentId && $email) {
        $sql = "SELECT id, class_id, first_name, last_name FROM students WHERE email=:em";
        if ($code_ecole) $sql .= " AND code_ecole=:ce";
        $sql .= " LIMIT 1";
        $st = $pdo->prepare($sql);
        $p  = [':em'=>$email]; if ($code_ecole) $p[':ce']=$code_ecole;
        $st->execute($p);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$r['id']; $_SESSION['student_id']=$studentId;
            if (!$classId && !empty($r['class_id'])) { $classId = (int)$r['class_id']; $_SESSION['class_id']=$classId; }
            $studentName = trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: $studentName;
        }
    }

    // 3. Recherche par Username
    if (!$studentId && $username) {
        $sql = "SELECT id, class_id, first_name, last_name FROM students WHERE username=:un";
        if ($code_ecole) $sql .= " AND code_ecole=:ce";
        $sql .= " LIMIT 1";
        $st = $pdo->prepare($sql);
        $p  = [':un'=>$username]; if ($code_ecole) $p[':ce']=$code_ecole;
        $st->execute($p);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$r['id']; $_SESSION['student_id']=$studentId;
            if (!$classId && !empty($r['class_id'])) { $classId=(int)$r['class_id']; $_SESSION['class_id']=$classId; }
            $studentName = trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: $studentName;
        }
    }

    // 4. Si studentId est déjà connu mais pas la classe
    if ($studentId && !$classId) {
        $st=$pdo->prepare("SELECT class_id, first_name, last_name FROM students WHERE id=:sid LIMIT 1");
        $st->execute([':sid'=>$studentId]);
        if ($r=$st->fetch(PDO::FETCH_ASSOC)) {
            if (!$classId && !empty($r['class_id'])) { $classId=(int)$r['class_id']; $_SESSION['class_id']=$classId; }
            $studentName = trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: $studentName;
        }
    }

    // 5. MISE À JOUR SYSTÉMATIQUE DU CHAMP id_user DANS STUDENTS
    if ($studentId && $userId) {
        $stUpdate = $pdo->prepare("UPDATE students SET id_user = :uid WHERE id = :sid");
        $stUpdate->execute([
            ':uid' => (int)$userId,
            ':sid' => (int)$studentId
        ]);
    }
} catch(Throwable $e){ /* silence */ }

// --------- INFOS CLASSE ----------
$classeData = null;
if ($classId) {
    try {
        $sql = "
        SELECT c.id, c.classe, c.description,
               n.description AS niveau,
               s.description AS section,
               o.description AS options
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau  = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE c.id=:cid
        ";
        $st=$pdo->prepare($sql);
        $st->execute([':cid'=>$classId]);
        $classeData = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch(Throwable $e){ /* silence */ }
}

// --------- STATS RAPIDES ----------
$stats = ['cours'=>0, 'lecons'=>0, 'quiz_pub'=>0];
if ($classId) {
    try {
        // cours
        $sql = "SELECT COUNT(*) FROM cours WHERE `class`=:cid"; 
        if ($code_ecole) $sql.=" AND code_ecole=:ce";
        $st=$pdo->prepare($sql);
        $p=[':cid'=>$classId]; if ($code_ecole) $p[':ce']=$code_ecole;
        $st->execute($p); $stats['cours'] = (int)$st->fetchColumn();

        // leçons publiées
        $sql = "SELECT COUNT(*) FROM lecons l JOIN cours c ON c.id=l.cours_id WHERE c.`class`=:cid AND l.is_published=1";
        if ($code_ecole) $sql.=" AND c.code_ecole=:ce";
        $st=$pdo->prepare($sql);
        $st->execute($p); $stats['lecons'] = (int)$st->fetchColumn();

        // quiz publiés
        $sql = "SELECT COUNT(*) FROM quizzes WHERE class_id=:cid AND is_published=1";
        if ($code_ecole) $sql.=" AND code_ecole=:ce";
        $st=$pdo->prepare($sql);
        $st->execute($p); $stats['quiz_pub'] = (int)$st->fetchColumn();
    } catch(Throwable $e){ /* silence */ }
}

// --------- ÉVALUATIONS À VENIR ----------
$upcoming = [];
if ($classId) {
    try {
        $sql = "
        SELECT id, title, type_eval, due_date, created_at
        FROM quizzes
        WHERE class_id=:cid AND is_published=1
          AND (due_date IS NULL OR due_date >= CURDATE())
        ";
        if ($code_ecole) $sql.=" AND code_ecole=:ce";
        $sql .= " ORDER BY (due_date IS NULL) ASC, due_date ASC, created_at DESC LIMIT 8";
        $st=$pdo->prepare($sql);
        $p=[':cid'=>$classId]; if ($code_ecole) $p[':ce']=$code_ecole;
        $st->execute($p);
        $upcoming = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(Throwable $e){ /* silence */ }
}

// --------- DERNIÈRES LEÇONS / FALLBACK CONTENUS ----------
$recentLessons = [];
$recentContents= [];
if ($classId) {
    try {
        $sql="
        SELECT l.id AS lecon_id, l.titre AS lecon_titre, l.created_at,
               c.id AS cours_id, c.nom AS cours_nom
        FROM lecons l
        JOIN cours c ON c.id = l.cours_id
        WHERE c.`class` = :cid AND l.is_published = 1
        ";
        if ($code_ecole) $sql.=" AND c.code_ecole=:ce";
        $sql.=" ORDER BY l.created_at DESC, l.id DESC LIMIT 6";
        $st=$pdo->prepare($sql);
        $p=[':cid'=>$classId]; if ($code_ecole) $p[':ce']=$code_ecole;
        $st->execute($p);
        $recentLessons=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(Throwable $e){ /* silence */ }

    if (!$recentLessons) {
        try {
            $parts=[];
            $parts[]="SELECT id, title AS titre, uploaded_at AS dt, 'PDF' AS typ, 'pdf' AS tname FROM pdfs   WHERE `class`=:cid".($code_ecole?' AND code_ecole=:ce':'');
            $parts[]="SELECT id, title AS titre, uploaded_at AS dt, 'VIDEO' AS typ,'video' AS tname FROM videos WHERE `class`=:cid".($code_ecole?' AND code_ecole=:ce':'');
            $parts[]="SELECT id, titre AS titre, uploaded_at AS dt, 'AUDIO' AS typ,'audio' AS tname FROM audios WHERE `class`=:cid".($code_ecole?' AND code_ecole=:ce':'');
            $sql='('.implode(') UNION ALL (', $parts).') ORDER BY dt DESC LIMIT 6';
            $st=$pdo->prepare($sql);
            $p=[':cid'=>$classId]; if ($code_ecole) $p[':ce']=$code_ecole;
            $st->execute($p);
            $recentContents=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(Throwable $e){ /* silence */ }
    }
}

// --------- ENSEIGNANTS (FIX FINAL, DESIGN INTACT) ----------
$teachers = [];
$nbTeachers = 0;

if ($classId) {
    $sql = "
    SELECT 
        u.id   AS teacher_id,
        u.first_name,
        u.last_name,
        u.phone,
        u.email
    FROM class_subject_teacher cst
    INNER JOIN users u 
        ON u.id = cst.teacher_user_id
       AND u.role = 'prof'
    WHERE cst.class_id = :cid
    ".($code_ecole?" AND cst.code_ecole = :ce":"");

    $st = $pdo->prepare($sql);
    $params = [':cid'=>$classId];
    if ($code_ecole) $params[':ce']=$code_ecole;

    $st->execute($params);
    $teachers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $nbTeachers = count($teachers);
}

// taux de complétion quiz (%)
$stats['quiz_done'] = 0;
if ($studentId && $stats['quiz_pub'] > 0) {
    try {
        $sql = "SELECT COUNT(*) FROM quiz_resultats WHERE student_id=:sid AND score >= 0";
        $st = $pdo->prepare($sql);
        $st->execute([':sid'=>$studentId]);
        $done = (int)$st->fetchColumn();
        $stats['quiz_done'] = round(($done / $stats['quiz_pub']) * 100);
    } catch(Throwable $e){}
}

// mois courant
$month = date('Y-m');
// =====================
// PRESENCE MOIS (ELEVE)
// =====================
$presenceStats = ['present'=>0,'absent'=>0,'retard'=>0];
$presenceDetails = [];

if ($studentId && $classId) {

    // stats mois
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN statut='present' THEN 1 END) present,
            COUNT(CASE WHEN statut='absent' THEN 1 END) absent,
            COUNT(CASE WHEN statut='retard' THEN 1 END) retard
        FROM presence_eleve
        WHERE eleve_id = :e
        AND class_id = :c
        AND DATE_FORMAT(date_presence,'%Y-%m') = :m
    ");

    $stmt->execute([
        ':e'=>$studentId,
        ':c'=>$classId,
        ':m'=>$month
    ]);

    $presenceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $presenceStats;

    // détails mois
    $stmt = $pdo->prepare("
        SELECT date_presence, statut, commentaire
        FROM presence_eleve
        WHERE eleve_id = :e
        AND class_id = :c
        AND DATE_FORMAT(date_presence,'%Y-%m') = :m
        ORDER BY date_presence DESC
    ");

    $stmt->execute([
        ':e'=>$studentId,
        ':c'=>$classId,
        ':m'=>$month
    ]);

    $presenceDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../layout/check_payment.php';
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Tableau de bord élève | MyKelasi</title>
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
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    .bi,
    .fa,
    .fas,
    .far,
    .fab {
        font-family: inherit;
    }

    i[class^="bi-"],
    i[class*=" bi-"] {
        font-family: "bootstrap-icons" !important;
    }

    .fa,
    .fas,
    .far,
    .fab {
        font-family: "Font Awesome 6 Free" !important;
    }

    .dashboard-hero {
        background: linear-gradient(135deg, #0d6efd, #2b8cfd);
        border-radius: 1rem;
        padding: 1.8rem;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .hero-title {
        font-size: 1.6rem;
        font-weight: 700;
    }

    .hero-sub {
        opacity: .85;
    }

    .hero-icon {
        font-size: 4rem;
        opacity: .2;
    }

    .stat-card {
        background: #fff;
        border-radius: .9rem;
        padding: 1.2rem;
        text-align: center;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
    }

    .stat-label {
        font-size: .9rem;
        color: #6c757d;
    }

    .chart-card {
        background: #fff;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    }

    .card-custom {
        border-radius: 1rem;
        border: 0;
        /* box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05); */
    }

    .badge-soft {
        background: #f1f3f5;
        color: #495057;
        border-radius: .5rem;
        padding: .15rem .5rem;
        font-size: .75rem
    }

    .avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>

    <div id="wrapper" class="wrapper bg-ash">
        <?php include '../layout/navbar.php'; ?>
        <div class="dashboard-page-one">
            <?php include '../layout/sidebar.php'; ?>

            <div class="dashboard-content-one">

                <!-- HERO -->
                <div class="dashboard-hero mt-4 mb-4">
                    <div>
                        <div class="hero-title">👋 Salut, <?= e($studentName) ?></div>
                        <div class="hero-sub">Bienvenue sur ton espace MyKelasi</div>
                    </div>
                    <i class="bi bi-mortarboard hero-icon"></i>
                </div>

                <!-- CLASSE + STATS -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-0">
                        <div class="card card-custom h-100">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="bi bi-buildings text-primary"></i> Ma classe</h6>
                                <?php if ($classeData): ?>
                                <div class="fw-bold">
                                    <?php
                                        $parts = array_filter([
                                            $classeData['classe'] ?? '',
                                            !empty($classeData['section']) ? up($classeData['section']) : '',
                                            !empty($classeData['niveau'])  ? up($classeData['niveau'])  : '',
                                            !empty($classeData['options']) ? up($classeData['options']) : ''
                                        ]);
                                        echo e(implode(' • ', $parts));
                                    ?>
                                </div>
                                <div class="small text-muted mt-1">
                                    ID classe: #<?= e((string)($classeData['id'] ?? $classId)) ?>
                                </div>
                                <?php else: ?>
                                <div class="text-muted small">
                                    Classe non définie. Contactez l’administration.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8 mb-3">
                        <div class="row">
                            <div class="col-sm-6 col-lg-3 mb-3">
                                <div class="stat-card">
                                    <i class="bi bi-book fs-2 text-primary"></i>
                                    <div class="stat-number"><?= (int)$stats['cours'] ?></div>
                                    <div class="stat-label">Cours</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3 mb-3">
                                <div class="stat-card">
                                    <i class="bi bi-journal-text fs-2 text-warning"></i>
                                    <div class="stat-number"><?= (int)$stats['lecons'] ?></div>
                                    <div class="stat-label">Leçons</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3 mb-3">
                                <div class="stat-card">
                                    <i class="bi bi-ui-checks fs-2 text-success"></i>
                                    <div class="stat-number"><?= (int)$stats['quiz_pub'] ?></div>
                                    <div class="stat-label">Quiz</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3 mb-3">
                                <div class="stat-card">
                                    <i class="bi bi-people fs-2 text-danger"></i>
                                    <div class="stat-number"><?= (int)$nbTeachers ?></div>
                                    <div class="stat-label">Enseignants</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHART -->
                <div class="chart-card mb-5">
                    <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Progression de ma classe</h6>
                    <canvas id="statsChart" height="95"></canvas>
                </div>

                <div class="chart-card mb-5">
                    <h6 class="mb-2"><i class="bi bi-graph-up-arrow text-success"></i> Progression des quiz</h6>
                    <div class="progress" style="height: 14px;">
                        <div class="progress-bar bg-success" role="progressbar"
                            style="width: <?= (int)$stats['quiz_done'] ?>%;"
                            aria-valuenow="<?= (int)$stats['quiz_done'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <?= (int)$stats['quiz_done'] ?>%
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">
                        Quiz complétés : <?= (int)$stats['quiz_done'] ?>% sur <?= (int)$stats['quiz_pub'] ?> quiz
                        disponibles
                    </small>
                </div>

                <!-- EVALUATIONS + ACTIONS -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card card-custom h-100">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="bi bi-clipboard-check text-primary"></i> Évaluations à venir
                                </h6>
                                <?php if ($upcoming): ?>
                                <ul class="list-group list-group-flush mt-2">
                                    <?php foreach ($upcoming as $q): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= e($q['title'] ?: 'Évaluation') ?></strong>
                                            <div class="small text-muted">
                                                <span class="badge-soft me-2">
                                                    <?= e($q['type_eval'] ?? 'Quiz') ?>
                                                </span>
                                                <?php if (!empty($q['due_date'])): ?>
                                                <i class="bi bi-calendar"></i>
                                                <?= e(date('d/m', strtotime($q['due_date']))) ?>
                                                <?php else: ?>
                                                Sans date
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a class="btn btn-sm btn-primary"
                                            href="quiz_passer.php?id=<?= (int)$q['id'] ?>">Passer</a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a class="small d-inline-block mt-2" href="mes_quiz.php">Voir tous mes quiz</a>
                                <?php else: ?>
                                <div class="small text-muted mt-2">Aucune évaluation imminente.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card card-custom h-100">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="bi bi-bolt text-warning"></i> Actions rapides</h6>
                                <div class="d-grid gap-2 mt-2">
                                    <a class="btn btn-primary" href="mes_cours.php">
                                        <i class="bi bi-journal-bookmark"></i> Voir mes cours
                                    </a>
                                    <a class="btn btn-outline-primary" href="mes_quiz.php">
                                        <i class="bi bi-patch-question"></i> Mes quiz
                                    </a>
                                    <a class="btn btn-outline-dark" href="finances.php">
                                        <i class="bi bi-wallet2"></i> Mes paiements
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LEÇONS / CONTENUS -->
                <div class="row mb-4">
                    <div class="col-lg-12 mb-4">
                        <div class="card card-custom h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><i class="bi bi-book text-primary"></i> Dernières leçons
                                        publiées</h6>
                                    <span class="badge-soft">source:
                                        <?= e($recentLessons ? 'leçons' : 'contenus') ?></span>
                                </div>

                                <?php if ($recentLessons): ?>
                                <ul class="list-group list-group-flush mt-2">
                                    <?php foreach ($recentLessons as $L):
                                        $when = $L['created_at'] ? date('d/m/Y H:i', strtotime($L['created_at'])) : '';
                                        $href = 'lecon_detail.php?id='.(int)$L['lecon_id'];
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="<?= e($href) ?>">
                                                <?= e($L['lecon_titre'] ?: ('Leçon #'.$L['lecon_id'])) ?>
                                            </a>
                                            <div class="small text-muted">
                                                <?= e($L['cours_nom'] ?? '') ?>
                                                <?php if ($when): ?> • <?= e($when) ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e($href) ?>">Voir</a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a class="small d-inline-block mt-2" href="mes_cours.php">Voir tous les cours</a>

                                <?php elseif ($recentContents): ?>
                                <ul class="list-group list-group-flush mt-2">
                                    <?php foreach ($recentContents as $C):
                                        $icon = $C['typ']==='PDF'?'fa-file-pdf':($C['typ']==='VIDEO'?'fa-video':'fa-volume-up');
                                        $href = 'voir_contenu.php?type='.strtolower($C['tname']).'&id='.(int)$C['id'];
                                        $when = $C['dt'] ? date('d/m/Y H:i', strtotime($C['dt'])) : '';
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="<?= e($href) ?>">
                                                <i class="fas <?= e($icon) ?> me-2"></i>
                                                <?= e($C['titre'] ?: ucfirst(strtolower($C['typ'])).' #'.$C['id']) ?>
                                            </a>
                                            <div class="small text-muted">
                                                <span class="badge-soft me-2"><?= e($C['typ']) ?></span>
                                                <?php if ($when): ?><?= e($when) ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e($href) ?>">Voir</a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a class="small d-inline-block mt-2" href="mes_cours.php">Voir tous les cours</a>

                                <?php else: ?>
                                <div class="small text-muted">Aucun contenu récent détecté pour votre classe.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRESENCE MOIS -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card card-custom">
                            <div class="card-body d-flex justify-content-between align-items-center">

                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-calendar-check text-primary"></i>
                                        Ma présence du mois (<?= e($month) ?>)
                                    </h6>

                                    <div class="small text-muted">
                                        Présents: <b class="text-success"><?= (int)$presenceStats['present'] ?></b> |
                                        Absents: <b class="text-danger"><?= (int)$presenceStats['absent'] ?></b> |
                                        Retards: <b class="text-warning"><?= (int)$presenceStats['retard'] ?></b>
                                    </div>
                                </div>

                                <button class="btn btn-outline-primary btn-sm" data-toggle="modal"
                                    data-target="#presenceModal">
                                    Voir détails
                                </button>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ENSEIGNANTS -->
                <div class="card card-custom mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-person-badge"></i> Mes enseignants</h6>
                            <span class="badge-soft"><?=$nbTeachers?> enseignant(s)</span>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table display text-nowrap w-100" id="teachersTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom</th>
                                        <th>Téléphone</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($teachers): foreach($teachers as $t): ?>
                                    <tr>
                                        <td><?=$t['teacher_id']?></td>
                                        <td><?=e(trim($t['first_name'].' '.$t['last_name']))?></td>
                                        <td><?=e($t['phone'] ?: '—')?></td>
                                        <td><?=e($t['email'] ?: '—')?></td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Aucun enseignant trouvé</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php include '../layout/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- MODAL PRESENCE -->
    <div class="modal fade" id="presenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Détails de ma présence</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <?php if(!$presenceDetails): ?>
                    <p class="text-muted">Aucune présence enregistrée ce mois.</p>
                    <?php else: ?>

                    <div class="accordion" id="presenceAccordion">

                        <?php 
                    $i = 0;
                    foreach($presenceDetails as $p): 
                    $i++;
                    ?>

                        <div class="card mb-2">

                            <div class="card-header" id="h<?= $i ?>">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#c<?= $i ?>">
                                    <?= e(date('d/m/Y', strtotime($p['date_presence']))) ?>
                                    —
                                    <?php if($p['statut']==='present'): ?>
                                    <span class="text-success">Présent</span>
                                    <?php elseif($p['statut']==='absent'): ?>
                                    <span class="text-danger">Absent</span>
                                    <?php else: ?>
                                    <span class="text-warning">Retard</span>
                                    <?php endif; ?>
                                </button>
                            </div>

                            <div id="c<?= $i ?>" class="collapse" data-parent="#presenceAccordion">
                                <div class="card-body">
                                    <p><b>Statut:</b> <?= e($p['statut']) ?></p>
                                    <p><b>Commentaire:</b> <?= e($p['commentaire'] ?: '—') ?></p>
                                </div>
                            </div>

                        </div>

                        <?php endforeach; ?>

                    </div>

                    <?php endif; ?>

                </div>

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

    <script>
    // DataTables enseignants
    $(function() {
        if ($.fn.DataTable) {
            $('#teachersTable').DataTable({
                pageLength: 5,
                lengthChange: false,
                order: [
                    [2, 'asc']
                ],
                language: {
                    search: "Rechercher :",
                    info: "Affichage _START_ à _END_ sur _TOTAL_",
                    infoEmpty: "Aucun résultat",
                    infoFiltered: "(filtré de _MAX_ au total)",
                    zeroRecords: "Aucun résultat trouvé",
                    paginate: {
                        previous: "Préc.",
                        next: "Suiv."
                    }
                }
            });
        }
    });

    // Chart.js stats
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('statsChart');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ["Cours", "Leçons", "Quiz", "Enseignants"],
                datasets: [{
                    label: "Statistiques actuelles",
                    data: [
                        <?= (int)$stats['cours'] ?>,
                        <?= (int)$stats['lecons'] ?>,
                        <?= (int)$stats['quiz_pub'] ?>,
                        <?= (int)$nbTeachers ?>
                    ],
                    backgroundColor: ["#0d6efd", "#ffc107", "#198754", "#dc3545"],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    </script>
</body>

</html>