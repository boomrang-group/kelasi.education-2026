<?php
// customs/teacher/view/list_des_quiz.php
// Liste des quiz du professeur (filtre par classe) + Détail d'un quiz (?quiz_id=)
// Bouton "Soumissions" amélioré (compteur, état désactivé si 0) + passage de class_id

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
if (!($pdo instanceof PDO)) { http_response_code(500); exit('Erreur DB'); }

// ---- Sécurité : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username   = $_SESSION['username'] ?? null;
$email      = $_SESSION['email'] ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --------- Helpers "classe du prof" ----------
function findClassIdsForTeacher(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): array {
    $ids=[];
    if ($usersId && $codeEcole){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:u AND code_ecole=:ec");
        $st->execute([':u'=>$usersId,':ec'=>$codeEcole]);
        $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($usersId){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:u");
        $st->execute([':u'=>$usersId]);
        $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername && $codeEcole){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un AND code_ecole=:ec");
        $st->execute([':un'=>$sessionUsername,':ec'=>$codeEcole]);
        $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un");
        $st->execute([':un'=>$sessionUsername]);
        $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionEmail){
        if ($codeEcole){
            $st=$pdo->prepare("SELECT id FROM teacher WHERE email=:em AND code_ecole=:ec");
            $st->execute([':em'=>$sessionEmail,':ec'=>$codeEcole]);
            $tids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
            if ($tids){
                $in=implode(',', array_fill(0,count($tids),'?')); $p=$tids; $p[]=$codeEcole;
                $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in) AND code_ecole=?");
                $st->execute($p);
                $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
            }
        }
        $st=$pdo->prepare("SELECT id FROM teacher WHERE email=:em");
        $st->execute([':em'=>$sessionEmail]);
        $tids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
        if ($tids){
            $in=implode(',', array_fill(0,count($tids),'?'));
            $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in)");
            $st->execute($tids);
            $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
        }
    }
    $ids=array_values(array_unique(array_filter($ids,fn($v)=>$v>0)));
    return $ids;
}

function buildClassLabel(array $ci): string {
    $label = trim(($ci['classe']??'').' '.($ci['description']??'').' '.($ci['niveau']??'').' '.($ci['section']??'').' '.($ci['options']??''));
    return $label !== '' ? $label : ('Classe #'.(int)($ci['id'] ?? 0));
}

function resolveActiveClassContext(PDO $pdo, array $classIds): array {
    // Priorité : GET class_id valide
    if (!empty($_GET['class_id']) && ctype_digit((string)$_GET['class_id'])) {
        $cid=(int)$_GET['class_id'];
        if (in_array($cid, $classIds, true)) {
            $st=$pdo->prepare("SELECT c.id, c.classe, c.description, n.description AS niveau, s.description AS section, o.description AS options
                               FROM classes c
                               LEFT JOIN niveau  n ON c.niveau=n.id
                               LEFT JOIN section s ON c.section=s.id
                               LEFT JOIN options o ON c.options=o.id
                               WHERE c.id=? LIMIT 1");
            $st->execute([$cid]);
            $ci=$st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$cid];
            return [$cid, buildClassLabel($ci)];
        }
    }
    // Sinon variables de session (bascule)
    $pairs = [
        ['id'=>'bascule_class_id','label'=>'bascule_class_label'],
        ['id'=>'active_class_id','label'=>'active_class_label'],
        ['id'=>'classe_active_id','label'=>'classe_active_libelle'],
    ];
    foreach ($pairs as $p){
        if (!empty($_SESSION[$p['id']])){
            $cid=(int)$_SESSION[$p['id']];
            if (in_array($cid,$classIds,true)){
                $lbl = (string)($_SESSION[$p['label']] ?? '');
                if ($lbl===''){
                    $st=$pdo->prepare("SELECT c.id, c.classe, c.description, n.description AS niveau, s.description AS section, o.description AS options
                                       FROM classes c
                                       LEFT JOIN niveau  n ON c.niveau=n.id
                                       LEFT JOIN section s ON c.section=s.id
                                       LEFT JOIN options o ON c.options=o.id
                                       WHERE c.id=? LIMIT 1");
                    $st->execute([$cid]);
                    $ci=$st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$cid];
                    $lbl=buildClassLabel($ci);
                }
                return [$cid,$lbl];
            }
        }
    }
    // Dernier recours : première classe
    if ($classIds){
        $cid=(int)$classIds[0];
        $st=$pdo->prepare("SELECT c.id, c.classe, c.description, n.description AS niveau, s.description AS section, o.description AS options
                           FROM classes c
                           LEFT JOIN niveau  n ON c.niveau=n.id
                           LEFT JOIN section s ON c.section=s.id
                           LEFT JOIN options o ON c.options=o.id
                           WHERE c.id=? LIMIT 1");
        $st->execute([$cid]);
        $ci=$st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$cid];
        return [$cid, buildClassLabel($ci)];
    }
    return [null, null];
}

// --------- Data access ----------
function fetchQuizzes(PDO $pdo, int $classId, int $teacherId, ?string $codeEcole): array {
    $sql = "SELECT id, title, description, type_eval, mode_questions, overall_score, is_published, created_at, due_date
            FROM quizzes
            WHERE class_id = :cid AND teacher_user_id = :tid";
    $p = [':cid'=>$classId, ':tid'=>$teacherId];
    if ($codeEcole){
        $sql .= " AND code_ecole = :ce";
        $p[':ce'] = $codeEcole;
    }
    $sql .= " ORDER BY created_at DESC, id DESC";
    $st=$pdo->prepare($sql); $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function fetchQuizOne(PDO $pdo, int $quizId, int $teacherId, ?string $codeEcole): ?array {
    $sql="SELECT id, class_id, title, description, type_eval, mode_questions, overall_score, is_published, created_at, due_date
          FROM quizzes
          WHERE id=:id AND teacher_user_id=:tid";
    $p=[':id'=>$quizId,':tid'=>$teacherId];
    if ($codeEcole){ $sql.=" AND code_ecole=:ce"; $p[':ce']=$codeEcole; }
    $st=$pdo->prepare($sql); $st->execute($p);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchQuizQuestions(PDO $pdo, int $quizId): array {
    $st=$pdo->prepare("SELECT id, question_text, `type`, points, choices_json, correct_json, expected_answer, sort_order
                       FROM quiz_questions
                       WHERE quiz_id = ?
                       ORDER BY sort_order ASC, id ASC");
    $st->execute([$quizId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Stats de soumissions pour un quiz (total, moyenne, date dernière) */
function fetchSubmissionStats(PDO $pdo, int $quizId, ?string $codeEcole): array {
    $sql = "SELECT 
                COUNT(*) AS n, 
                AVG(score_obtained) AS avg_score,
                MAX(submitted_at) AS last_dt
            FROM quiz_submissions
            WHERE quiz_id = :qid";
    $p = [':qid'=>$quizId];
    if ($codeEcole){
        $sql .= " AND code_ecole = :ce";
        $p[':ce'] = $codeEcole;
    }
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['n'=>0,'avg_score'=>null,'last_dt'=>null];
    return [
        'n' => (int)($r['n'] ?? 0),
        'avg' => $r['avg_score'] !== null ? round((float)$r['avg_score'], 2) : null,
        'last' => $r['last_dt'] ?? null,
    ];
}

// --------- Exécution ----------
$flash = '';
$alert = '';

try {
    $classIds = findClassIdsForTeacher($pdo, $userId, $username, $email, $code_ecole);
    if (!$classIds) throw new RuntimeException("Aucune classe associée à votre profil.");

    [$classId, $classLabel] = resolveActiveClassContext($pdo, $classIds);
    if (!$classId) throw new RuntimeException("Aucune classe active.");

    $quizId = isset($_GET['quiz_id']) && ctype_digit((string)$_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
    $quiz   = $quizId ? fetchQuizOne($pdo, $quizId, $userId, $code_ecole) : null;

    // Si un quiz_id est demandé, vérifier qu'il appartient bien à la classe active pour cohérence d'URL
    if ($quiz && (int)$quiz['class_id'] !== (int)$classId) {
        // On autorise l'affichage si le prof en est l'auteur, mais on ajuste le label de classe
        $classId = (int)$quiz['class_id'];
        $st=$pdo->prepare("SELECT c.id, c.classe, c.description, n.description AS niveau, s.description AS section, o.description AS options
                           FROM classes c
                           LEFT JOIN niveau  n ON c.niveau=n.id
                           LEFT JOIN section s ON c.section=s.id
                           LEFT JOIN options o ON c.options=o.id
                           WHERE c.id=? LIMIT 1");
        $st->execute([$classId]);
        $ci=$st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$classId];
        $classLabel = buildClassLabel($ci);
    }

    $quizzes = fetchQuizzes($pdo, $classId, $userId, $code_ecole);

} catch (Throwable $e) {
    $alert = '<div class="alert alert-danger">'.h($e->getMessage()).'</div>';
    $classId = null; $classLabel = null; $quizzes = []; $quiz = null;
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mes quiz | MyKelasi</title>
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
        .badge-soft{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:999px;padding:.25rem .6rem}
        .muted{color:#6b7280}
        .q-card{border:1px solid #e5e7eb;border-radius:10px}
        .q-header{background:#f8fafc;padding:10px 14px;border-radius:10px 10px 0 0}
        .q-body{padding:12px 14px}
        .pill{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem}
        .pill-pub{background:#e8fff1;border:1px solid #22c55e;color:#14532d}
        .pill-unpub{background:#fff7ed;border:1px solid #fb923c;color:#7c2d12}
        .table td, .table th{vertical-align:middle}
        .small-muted{font-size:.85rem;color:#6b7280}
        pre.wrap{white-space:pre-wrap}
    </style>
</head>
<body>
<div id="preloader" class="d-none"></div>

<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area">
                <h3>Mes quiz</h3>
                <?php if ($classLabel): ?>
                    <ul><li>Classe</li><li><?= h($classLabel) ?></li></ul>
                <?php endif; ?>
            </div>

            <?= $alert ?>

            <?php if ($classId): ?>
            <div class="row">
                <div class="col-lg-7">
                    <div class="q-card mb-3">
                        <div class="q-header">
                            <strong>Liste des quiz</strong>
                        </div>
                        <div class="q-body">
                            <?php if (!$quizzes): ?>
                                <div class="small-muted">Aucun quiz pour cette classe.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Titre</th>
                                                <th>Type</th>
                                                <th>Mode</th>
                                                <th>Score</th>
                                                <th>Statut</th>
                                                <th>Soumissions</th>
                                                <th>Créé le</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($quizzes as $q):
                                            $stats = fetchSubmissionStats($pdo, (int)$q['id'], $code_ecole);
                                        ?>
                                            <tr>
                                                <td><?= (int)$q['id'] ?></td>
                                                <td>
                                                    <div><?= h($q['title']) ?></div>
                                                    <?php if (!empty($q['description'])): ?>
                                                        <div class="small-muted"><?= h($q['description']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= h($q['type_eval']) ?></td>
                                                <td><?= h($q['mode_questions']) ?></td>
                                                <td><?= (int)$q['overall_score'] ?></td>
                                                <td>
                                                    <?php if ((int)$q['is_published']===1): ?>
                                                        <span class="pill pill-pub">publié</span>
                                                    <?php else: ?>
                                                        <span class="pill pill-unpub">non publié</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?= (int)$stats['n'] ?> élève(s)</div>
                                                    <?php if ($stats['avg'] !== null): ?>
                                                        <div class="small-muted">Moy.: <?= (float)$stats['avg'] ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= h((new DateTime($q['created_at']))->format('Y-m-d')) ?></td>
                                                <td class="text-nowrap">
                                                    <a class="btn btn-md btn-outline-primary mb-1"
                                                       href="list_des_quiz.php?class_id=<?= (int)$classId ?>&quiz_id=<?= (int)$q['id'] ?>">
                                                        Voir
                                                    </a>

                                                    <?php if ((int)$stats['n'] > 0): ?>
                                                        <a class="btn btn-md btn-success mb-1"
                                                           href="quiz_submissions.php?quiz_id=<?= (int)$q['id'] ?>&class_id=<?= (int)$classId ?>"
                                                           title="Voir les <?= (int)$stats['n'] ?> soumission(s) de ce quiz">
                                                            Soumissions
                                                            <span class="badge bg-light text-dark">
                                                                <?= (int)$stats['n'] ?>
                                                            </span>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button"
                                                                class="btn btn-md btn-outline-secondary mb-1"
                                                                disabled
                                                                title="Aucune soumission pour l'instant">
                                                            Soumissions (0)
                                                        </button>
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

                <div class="col-lg-5">
                    <div class="q-card mb-3">
                        <div class="q-header d-flex justify-content-between align-items-center">
                            <strong>Détail du quiz</strong>
                            <?php if (!empty($quiz['id'])): ?>
                                <?php $headerStats = fetchSubmissionStats($pdo, (int)$quiz['id'], $code_ecole); ?>
                                <a class="btn btn-sm btn-success"
                                   href="quiz_submissions.php?quiz_id=<?= (int)$quiz['id'] ?>&class_id=<?= (int)$classId ?>">
                                    Voir les soumissions (<?= (int)($headerStats['n'] ?? 0) ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="q-body">
                            <?php if (!$quizId): ?>
                                <div class="small-muted">Sélectionnez “Voir” dans la liste pour afficher le détail.</div>
                            <?php elseif (!$quiz): ?>
                                <div class="alert alert-warning">Quiz introuvable ou non autorisé.</div>
                            <?php else: ?>
                                <?php $qStats = fetchSubmissionStats($pdo, (int)$quiz['id'], $code_ecole); ?>
                                <div class="mb-2">
                                    <div><strong><?= h($quiz['title']) ?></strong></div>
                                    <?php if (!empty($quiz['description'])): ?>
                                        <div class="small-muted"><?= h($quiz['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-2">
                                    <span class="badge-soft"><?= h($quiz['type_eval']) ?></span>
                                    <span class="badge-soft"><?= h($quiz['mode_questions']) ?></span>
                                    <span class="badge-soft">Score total: <?= (int)$quiz['overall_score'] ?></span>
                                    <span class="badge-soft"><?= (int)$quiz['is_published']===1?'publié':'non publié' ?></span>
                                </div>
                                <?php if (!empty($quiz['due_date'])): ?>
                                    <div class="small-muted mb-2">Échéance: <?= h($quiz['due_date']) ?></div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <div><strong>Soumissions :</strong> <?= (int)$qStats['n'] ?></div>
                                    <?php if ($qStats['avg'] !== null): ?>
                                        <div class="small-muted">Moyenne: <?= (float)$qStats['avg'] ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($qStats['last'])): ?>
                                        <div class="small-muted">Dernière: <?= h(date('Y-m-d H:i', strtotime($qStats['last']))) ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                    $qs = fetchQuizQuestions($pdo, (int)$quiz['id']);
                                    if (!$qs) {
                                        echo '<div class="small-muted">Aucune question enregistrée.</div>';
                                    } else {
                                        echo '<ol class="pl-3">';
                                        foreach ($qs as $qq) {
                                            $t = strtoupper((string)$qq['type']);
                                            $pts = (int)$qq['points'];
                                            echo '<li class="mb-2">';
                                            echo '<div><strong>'.h($qq['question_text']).'</strong> <span class="small-muted">('.$t.', '.$pts.' pt'.($pts>1?'s':'').')</span></div>';
                                            if ($t==='QCM') {
                                                $choices = [];
                                                if (!empty($qq['choices_json'])) {
                                                    $tmp = json_decode((string)$qq['choices_json'], true);
                                                    if (is_array($tmp)) $choices = $tmp;
                                                }
                                                $correct = [];
                                                if (!empty($qq['correct_json'])) {
                                                    $tmp = json_decode((string)$qq['correct_json'], true);
                                                    if (is_array($tmp)) $correct = $tmp;
                                                }
                                                if ($choices) {
                                                    echo '<ul class="mb-1">';
                                                    foreach ($choices as $i=>$c) {
                                                        $isGood = in_array($i, $correct, true);
                                                        echo '<li>'.($isGood?'<strong>':'').h($c).($isGood?'</strong>':'').'</li>';
                                                    }
                                                    echo '</ul>';
                                                }
                                            } else { // QR
                                                if (!empty($qq['expected_answer'])) {
                                                    echo '<div class="small-muted">Réponse attendue :</div>';
                                                    echo '<pre class="wrap">'.h($qq['expected_answer']).'</pre>';
                                                }
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ol>';
                                    }
                                ?>
                                <div class="mt-3">
                                    <?php if ((int)$qStats['n'] > 0): ?>
                                        <a class="btn btn-success"
                                           href="quiz_submissions.php?quiz_id=<?= (int)$quiz['id'] ?>&class_id=<?= (int)$classId ?>">
                                            Voir les soumissions des élèves (<?= (int)$qStats['n'] ?>)
                                        </a>
                                    <?php else: ?>
                                        <button type="button"
                                                class="btn btn-outline-secondary"
                                                disabled>
                                            Aucune soumission pour l'instant
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
