<?php
// customs/teacher/view/quiz_submissions.php
// Liste des soumissions des élèves pour un quiz donné

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
if (!($pdo instanceof PDO)) { http_response_code(500); exit('Erreur DB'); }

// ---- Sécurité : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username   = $_SESSION['username'] ?? null;
$email      = $_SESSION['email'] ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --------- Récupération des paramètres GET ----------
$quizId  = isset($_GET['quiz_id'])  && ctype_digit((string)$_GET['quiz_id'])  ? (int)$_GET['quiz_id']  : 0;
$classId = isset($_GET['class_id']) && ctype_digit((string)$_GET['class_id']) ? (int)$_GET['class_id'] : null;
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['draft','submitted','graded'], true)
    ? $_GET['status']
    : null;

if ($quizId <= 0) {
    http_response_code(400);
    exit('Paramètre quiz_id manquant ou invalide.');
}

// --------- Chargement du quiz + vérification appartenance prof ----------
$sqlQuiz = "
    SELECT 
        q.id,
        q.class_id,
        q.code_ecole,
        q.title,
        q.description,
        q.type_eval,
        q.mode_questions,
        q.overall_score,
        q.is_published,
        q.created_at,
        q.due_date,
        c.classe,
        c.description AS classe_description,
        n.description AS niveau,
        s.description AS section,
        o.description AS options
    FROM quizzes q
    LEFT JOIN classes c ON q.class_id = c.id
    LEFT JOIN niveau  n ON c.niveau  = n.id
    LEFT JOIN section s ON c.section = s.id
    LEFT JOIN options o ON c.options = o.id
    WHERE q.id = :qid
      AND q.teacher_user_id = :tid
";

$paramsQuiz = [
    ':qid' => $quizId,
    ':tid' => $userId,
];

if ($code_ecole) {
    $sqlQuiz .= " AND q.code_ecole = :ce";
    $paramsQuiz[':ce'] = $code_ecole;
}

$st = $pdo->prepare($sqlQuiz);
$st->execute($paramsQuiz);
$quiz = $st->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    http_response_code(403);
    exit('Quiz introuvable ou vous n\'êtes pas autorisé à le consulter.');
}

// Si class_id non fourni, on prend la classe du quiz
if (!$classId) {
    $classId = (int)$quiz['class_id'];
}

// --------- Chargement des soumissions ----------
$sqlSub = "
    SELECT 
        qs.id,
        qs.student_id,
        qs.class_id,
        qs.code_ecole,
        qs.STATUS,
        qs.score_obtained,
        qs.started_at,
        qs.submitted_at,
        qs.graded_at,
        s.first_name,
        s.last_name,
        s.username AS student_username,
        s.email AS student_email,
        s.phone    AS student_phone,
        c.classe,
        c.description AS classe_description
    FROM quiz_submissions qs
    LEFT JOIN students s ON qs.student_id = s.id
    LEFT JOIN classes  c ON qs.class_id  = c.id
    WHERE qs.quiz_id = :qid
";

$paramsSub = [
    ':qid' => $quizId,
];

if ($code_ecole) {
    $sqlSub .= " AND qs.code_ecole = :ce";
    $paramsSub[':ce'] = $code_ecole;
}

if ($classId) {
    $sqlSub .= " AND qs.class_id = :cid";
    $paramsSub[':cid'] = $classId;
}

if ($statusFilter) {
    $sqlSub .= " AND qs.STATUS = :st";
    $paramsSub[':st'] = $statusFilter;
}

$sqlSub .= " ORDER BY qs.submitted_at IS NULL, qs.submitted_at DESC, qs.id DESC";

$st = $pdo->prepare($sqlSub);
$st->execute($paramsSub);
$submissions = $st->fetchAll(PDO::FETCH_ASSOC);

// --------- Stats globales ----------
$total = count($submissions);
$graded = 0;
$sumScore = 0.0;
$maxScore = null;
$lastSubmitted = null;

foreach ($submissions as $sub) {
    if ($sub['STATUS'] === 'graded' && $sub['score_obtained'] !== null) {
        $graded++;
        $score = (float)$sub['score_obtained'];
        $sumScore += $score;
        if ($maxScore === null || $score > $maxScore) {
            $maxScore = $score;
        }
    }
    if (!empty($sub['submitted_at'])) {
        $dt = $sub['submitted_at'];
        if ($lastSubmitted === null || $dt > $lastSubmitted) {
            $lastSubmitted = $dt;
        }
    }
}

$avgScore = ($graded > 0) ? round($sumScore / $graded, 2) : null;

// Label classe
$classLabel = trim(
    ($quiz['classe'] ?? '') . ' ' .
    ($quiz['classe_description'] ?? '') . ' ' .
    ($quiz['niveau'] ?? '') . ' ' .
    ($quiz['section'] ?? '') . ' ' .
    ($quiz['options'] ?? '')
);
if ($classLabel === '') {
    $classLabel = 'Classe #'.(int)$quiz['class_id'];
}

// Badge statut
function renderStatusBadge(string $status): string {
    switch ($status) {
        case 'graded':
            return '<span class="badge bg-success">Corrigé</span>';
        case 'submitted':
            return '<span class="badge bg-primary">Remis</span>';
        case 'draft':
        default:
            return '<span class="badge bg-secondary">Brouillon</span>';
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Soumissions du quiz | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <style>
        body{font-family:system-ui}
        .q-card{border:1px solid #e5e7eb;border-radius:10px}
        .q-header{background:#f8fafc;padding:10px 14px;border-radius:10px 10px 0 0}
        .q-body{padding:12px 14px}
        .small-muted{font-size:.85rem;color:#6b7280}
        .pill{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem}
        .pill-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
        .table td, .table th{vertical-align:middle}
        .badge-soft{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:999px;padding:.25rem .6rem}
    </style>
</head>
<body>
<div id="preloader" class="d-none"></div>

<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area d-flex justify-content-between align-items-center">
                <div>
                    <h3>Soumissions du quiz</h3>
                    <ul>
                        <li>Classe</li>
                        <li><?= h($classLabel) ?></li>
                    </ul>
                </div>
                <div>
                    <a href="list_des_quiz.php?class_id=<?= (int)$classId ?>&quiz_id=<?= (int)$quiz['id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                        ← Retour à mes quiz
                    </a>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-lg-7">
                    <div class="q-card mb-3">
                        <div class="q-header">
                            <strong><?= h($quiz['title']) ?></strong>
                        </div>
                        <div class="q-body">
                            <?php if (!empty($quiz['description'])): ?>
                                <div class="mb-2 small-muted"><?= nl2br(h($quiz['description'])) ?></div>
                            <?php endif; ?>
                            <div class="mb-2">
                                <span class="badge-soft"><?= h($quiz['type_eval']) ?></span>
                                <span class="badge-soft"><?= h($quiz['mode_questions']) ?></span>
                                <span class="badge-soft">Score total: <?= (int)$quiz['overall_score'] ?></span>
                                <span class="badge-soft"><?= (int)$quiz['is_published']===1?'publié':'non publié' ?></span>
                            </div>
                            <?php if (!empty($quiz['due_date'])): ?>
                                <div class="small-muted">Échéance : <?= h($quiz['due_date']) ?></div>
                            <?php endif; ?>
                            <div class="small-muted">Créé le : <?= h((new DateTime($quiz['created_at']))->format('Y-m-d H:i')) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="q-card mb-3">
                        <div class="q-header">
                            <strong>Résumé des soumissions</strong>
                        </div>
                        <div class="q-body">
                            <div class="mb-1">
                                <strong>Total soumissions :</strong> <?= (int)$total ?>
                            </div>
                            <div class="mb-1">
                                <strong>Soumissions corrigées :</strong> <?= (int)$graded ?>
                            </div>
                            <div class="mb-1">
                                <strong>Score moyen :</strong>
                                <?php if ($avgScore !== null): ?>
                                    <?= (float)$avgScore ?> / <?= (int)$quiz['overall_score'] ?>
                                <?php else: ?>
                                    <span class="small-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-1">
                                <strong>Meilleur score :</strong>
                                <?php if ($maxScore !== null): ?>
                                    <?= (float)$maxScore ?> / <?= (int)$quiz['overall_score'] ?>
                                <?php else: ?>
                                    <span class="small-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-1">
                                <strong>Dernière soumission :</strong>
                                <?php if (!empty($lastSubmitted)): ?>
                                    <?= h(date('Y-m-d H:i', strtotime($lastSubmitted))) ?>
                                <?php else: ?>
                                    <span class="small-muted">Aucune</span>
                                <?php endif; ?>
                            </div>

                            <hr>
                            <form method="get" class="form-inline">
                                <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                <?php if ($classId): ?>
                                    <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                <?php endif; ?>
                                <label class="mr-2 small-muted">Filtrer par statut :</label>
                                <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                                    <option value="">Tous</option>
                                    <option value="draft"     <?= $statusFilter==='draft'?'selected':'' ?>>Brouillon</option>
                                    <option value="submitted" <?= $statusFilter==='submitted'?'selected':'' ?>>Remis</option>
                                    <option value="graded"    <?= $statusFilter==='graded'?'selected':'' ?>>Corrigé</option>
                                </select>
                                <?php if ($statusFilter): ?>
                                    <a href="quiz_submissions.php?quiz_id=<?= (int)$quizId ?>&class_id=<?= (int)$classId ?>"
                                       class="btn btn-sm btn-link">Réinitialiser</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des soumissions -->
            <div class="row">
                <div class="col-12">
                    <div class="q-card">
                        <div class="q-header">
                            <strong>Liste des soumissions des élèves</strong>
                        </div>
                        <div class="q-body">
                            <?php if (!$submissions): ?>
                                <div class="small-muted">
                                    Aucune soumission pour ce quiz pour l'instant.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Élève</th>
                                                <th>Classe</th>
                                                <th>Statut</th>
                                                <th>Score obtenu</th>
                                                <th>Commencé le</th>
                                                <th>Remis le</th>
                                                <th>Corrigé le</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($submissions as $sub): ?>
                                            <tr>
                                                <td><?= (int)$sub['id'] ?></td>
                                                <td>
                                                    <?php
                                                        $fullName = trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''));
                                                        if ($fullName === '') $fullName = $sub['student_username'] ?? 'Élève #'.$sub['student_id'];
                                                    ?>
                                                    <div><?= h($fullName) ?></div>
                                                    <?php if (!empty($sub['student_username'])): ?>
                                                        <div class="small-muted">@<?= h($sub['student_username']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $clLbl = trim(($sub['classe'] ?? '') . ' ' . ($sub['classe_description'] ?? ''));
                                                        echo h($clLbl !== '' ? $clLbl : ('Classe #'.(int)$sub['class_id']));
                                                    ?>
                                                </td>
                                                <td><?= renderStatusBadge((string)$sub['STATUS']) ?></td>
                                                <td>
                                                    <?php if ($sub['score_obtained'] !== null): ?>
                                                        <?= (float)$sub['score_obtained'] ?> / <?= (int)$quiz['overall_score'] ?>
                                                    <?php else: ?>
                                                        <span class="small-muted">Non noté</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($sub['started_at'])): ?>
                                                        <?= h(date('Y-m-d H:i', strtotime($sub['started_at']))) ?>
                                                    <?php else: ?>
                                                        <span class="small-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($sub['submitted_at'])): ?>
                                                        <?= h(date('Y-m-d H:i', strtotime($sub['submitted_at']))) ?>
                                                    <?php else: ?>
                                                        <span class="small-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($sub['graded_at'])): ?>
                                                        <?= h(date('Y-m-d H:i', strtotime($sub['graded_at']))) ?>
                                                    <?php else: ?>
                                                        <span class="small-muted">—</span>
                                                    <?php endif; ?>
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
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/main.js"></script>
</body>
</html>
