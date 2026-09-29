<?php
// customs/teacher/view/corriger_quiz.php
// Correction manuelle d’une soumission QR : attribuer points par question, calcul du total, statut "graded".

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

 $DEBUG = DEBUG; // DEBUG handled in db_connect.php
if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';

// Sécurité prof
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId     = (int)($_SESSION['user_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

// Charger soumission + quiz
function loadSubmission(PDO $pdo, int $sid): ?array {
    $st = $pdo->prepare("
        SELECT s.*, q.title AS quiz_title, q.mode_questions, q.overall_score, q.teacher_user_id,
               st.first_name, st.last_name, st.username
          FROM quiz_submissions s
          JOIN quizzes q ON q.id=s.quiz_id
          JOIN students st ON st.id=s.student_id
         WHERE s.id=:id
         LIMIT 1
    ");
    $st->execute([':id'=>$sid]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}

function loadQuestionsAndAnswers(PDO $pdo, int $sid): array {
    // On veut les QR exclusivement, mais on affichera ce qui existe (par sécurité)
    $st = $pdo->prepare("
        SELECT qa.id AS qa_id, qa.question_id, qa.answer_text, qa.selected_option_id,
               qa.is_correct, qa.points_awarded,
               qq.question_text, qq.`type`, qq.points, qq.expected_answer
          FROM quiz_answers qa
          JOIN quiz_questions qq ON qq.id = qa.question_id
         WHERE qa.submission_id=:sid
         ORDER BY qq.id
    ");
    $st->execute([':sid'=>$sid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$errors=[]; $success='';

$sub = $submissionId ? loadSubmission($pdo, $submissionId) : null;
if (!$sub) { $errors[]="Soumission introuvable."; }

if ($sub && (int)$sub['teacher_user_id'] !== $userId) {
    $errors[] = "Accès refusé à cette soumission.";
}

$isQR = $sub && strtoupper($sub['mode_questions'])==='QR';

// POST: correction
if ($_SERVER['REQUEST_METHOD']==='POST' && $sub && $isQR) {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $errors[]='CSRF invalide.';
    } else {
        $pts = $_POST['pts'] ?? []; // pts[qa_id] = points attribués
        if (!is_array($pts)) $pts = [];

        try {
            $pdo->beginTransaction();
            $total = 0;
            foreach ($pts as $qaId => $val) {
                if (!ctype_digit((string)$qaId)) continue;
                $qaId = (int)$qaId;
                $p = is_numeric($val) ? (int)$val : 0;

                // Récup max points pour cette question
                $rq = $pdo->prepare("
                    SELECT qq.points
                      FROM quiz_answers qa JOIN quiz_questions qq ON qq.id=qa.question_id
                     WHERE qa.id=:qaid AND qa.submission_id=:sid
                     LIMIT 1
                ");
                $rq->execute([':qaid'=>$qaId, ':sid'=>$submissionId]);
                $max = (int)($rq->fetchColumn() ?: 0);
                if ($p < 0) $p = 0;
                if ($p > $max) $p = $max;

                $up = $pdo->prepare("UPDATE quiz_answers SET points_awarded=:p, is_correct=NULL WHERE id=:id AND submission_id=:sid");
                $up->execute([':p'=>$p, ':id'=>$qaId, ':sid'=>$submissionId]);

                $total += $p;
            }

            $upS = $pdo->prepare("UPDATE quiz_submissions SET score_obtained=:s, status='graded', graded_at=NOW() WHERE id=:sid");
            $upS->execute([':s'=>$total, ':sid'=>$submissionId]);

            $pdo->commit();
            $success = "Correction enregistrée. Score total: $total";
            // PRG
            header('Location: corriger_quiz.php?submission_id='.$submissionId.'&msg=ok'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[]='Erreur lors de l’enregistrement.';
            if ($DEBUG) $errors[]=e($e->getMessage());
        }
    }
}

// Charger Q/A
$qas = ($sub && $isQR) ? loadQuestionsAndAnswers($pdo, $submissionId) : [];

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Correction du quiz (QR)</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <style>
        .card{border:0;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        .muted{color:#6b7280}
        pre.answer{white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area">
                <h3>Correction manuelle — QR</h3>
            </div>

            <?php if (!empty($_GET['msg']) && $_GET['msg']==='ok'): ?>
                <div class="alert alert-success">Correction enregistrée.</div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
            <?php endif; ?>

            <?php if ($sub && $isQR): ?>
                <?php
                    $eleveNom = trim(($sub['first_name']??'').' '.($sub['last_name']??'')) ?: ($sub['username'] ?? ('Élève #'.$sub['student_id']));
                ?>
                <div class="card">
                    <div class="card-body">
                        <a href="list_des_quiz.php?class_id=<?= (int)$sub['class_id'] ?>&quiz_id=<?= (int)$sub['quiz_id'] ?>" class="btn btn-sm btn-outline-secondary mb-2">← Retour au quiz</a>
                        <h5><?= e($sub['quiz_title']) ?></h5>
                        <div class="muted mb-2">
                            Élève : <strong><?= e($eleveNom) ?></strong> •
                            Soumis le : <strong><?= e($sub['submitted_at'] ?? $sub['started_at'] ?? '—') ?></strong> •
                            Barème : <strong><?= (int)$sub['overall_score'] ?></strong>
                        </div>

                        <form method="post" action="">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Question</th>
                                            <th>Réponse élève</th>
                                            <th>Points (0..max)</th>
                                            <th>Max</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i=1; $sum=0; foreach ($qas as $row): ?>
                                            <?php
                                                $max = (int)$row['points'];
                                                $sum += max(0, min($max, (int)($row['points_awarded'] ?? 0)));
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= e($row['question_text']) ?></td>
                                                <td><pre class="answer"><?= e($row['answer_text'] ?? '') ?></pre></td>
                                                <td style="width:120px">
                                                    <input type="number" name="pts[<?= (int)$row['qa_id'] ?>]" class="form-control form-control-sm"
                                                           min="0" max="<?= $max ?>" value="<?= (int)($row['points_awarded'] ?? 0) ?>">
                                                </td>
                                                <td><?= $max ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-right">Total actuel</th>
                                            <th><?= (int)$sum ?></th>
                                            <th><?= (int)$sub['overall_score'] ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <button class="btn btn-primary">Enregistrer la correction</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($sub && !$isQR): ?>
                <div class="alert alert-info">Ce quiz n’est pas en mode QR.</div>
            <?php endif; ?>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
</body>
</html>
