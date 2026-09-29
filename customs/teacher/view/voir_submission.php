<?php
// customs/teacher/view/voir_submission.php
// Affiche le détail d’une soumission (QCM auto-corrigée ou QR corrigée)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

// Sécurité prof
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

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

function loadQA(PDO $pdo, int $sid): array {
    $st = $pdo->prepare("
        SELECT qa.id AS qa_id, qa.question_id, qa.answer_text, qa.selected_option_id, qa.is_correct, qa.points_awarded,
               qq.question_text, qq.`type`, qq.points, qq.choices_json, qq.correct_json
          FROM quiz_answers qa
          JOIN quiz_questions qq ON qq.id=qa.question_id
         WHERE qa.submission_id=:sid
         ORDER BY qq.id
    ");
    $st->execute([':sid'=>$sid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$sub = $submissionId ? loadSubmission($pdo, $submissionId) : null;
if (!$sub) { echo "<div class='alert alert-danger'>Soumission introuvable.</div>"; exit; }
if ((int)$sub['teacher_user_id'] !== $userId) { echo "<div class='alert alert-danger'>Accès refusé.</div>"; exit; }

$qas = loadQA($pdo, $submissionId);

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Soumission — Détails</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <style>
        .card{border:0;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        pre.answer{white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px}
        .ok{color:#15803d} .ko{color:#b91c1c}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area">
                <h3>Détails de la soumission</h3>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php
                        $eleveNom = trim(($sub['first_name']??'').' '.($sub['last_name']??'')) ?: ($sub['username'] ?? ('Élève #'.$sub['student_id']));
                    ?>
                    <a href="list_des_quiz.php?class_id=<?= (int)$sub['class_id'] ?>&quiz_id=<?= (int)$sub['quiz_id'] ?>" class="btn btn-sm btn-outline-secondary mb-2">← Retour au quiz</a>
                    <h5><?= e($sub['quiz_title']) ?></h5>
                    <div class="mb-2">
                        Élève : <strong><?= e($eleveNom) ?></strong> •
                        Mode : <strong><?= e($sub['mode_questions']) ?></strong> •
                        Score : <strong><?= is_null($sub['score_obtained'])?'—':((int)$sub['score_obtained'].' / '.(int)$sub['overall_score']) ?></strong> •
                        Statut : <strong><?= strtoupper(e($sub['status'])) ?></strong>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Question</th>
                                    <th>Réponse</th>
                                    <th>Résultat / Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($qas as $row): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= e($row['question_text']) ?></td>
                                        <td>
                                            <?php if ($row['type']==='qcm'): ?>
                                                <?php
                                                    $choices = json_decode((string)$row['choices_json'], true) ?: [];
                                                    $sel = is_null($row['selected_option_id']) ? null : (int)$row['selected_option_id'];
                                                    echo 'Choix de l’élève : <strong>'.e($sel!==null && isset($choices[$sel]) ? $choices[$sel] : '—').'</strong><br>';
                                                    echo '<small class="text-muted">Options : '.e(implode(' | ', $choices)).'</small>';
                                                ?>
                                            <?php else: ?>
                                                <pre class="answer"><?= e($row['answer_text'] ?? '') ?></pre>
                                            <?php endif; ?>
                                        </td>
                                        <td style="width:200px">
                                            <?php
                                                $pa = is_null($row['points_awarded']) ? '—' : (int)$row['points_awarded'];
                                                if ($row['type']==='qcm') {
                                                    $ic = is_null($row['is_correct']) ? '—' : ((int)$row['is_correct']===1?'<span class="ok">✔ correct</span>':'<span class="ko">✘ faux</span>');
                                                    echo $ic.'<br>Points: '.$pa.' / '.(int)$row['points'];
                                                } else {
                                                    echo 'Points: '.$pa.' / '.(int)$row['points'];
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
</body>
</html>
