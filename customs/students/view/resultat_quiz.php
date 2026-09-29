<?php
// customs/eleve/view/resultat_quiz.php
// Affiche le détail d'une soumission élève

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';
if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); } catch(Throwable $e){} }

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$code_ecole   = $_SESSION['code_ecole'] ?? null;
$username     = $_SESSION['username']   ?? null;
$email        = $_SESSION['email']      ?? null;
$submissionId = (isset($_GET['submission_id']) && ctype_digit($_GET['submission_id'])) ? (int)$_GET['submission_id'] : 0;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($submissionId<=0){ echo "Soumission invalide."; exit; }

// Élève
function findStudent(PDO $pdo, ?string $email, ?string $username, ?string $codeEcole): ?array {
    try {
        if ($email) {
            $st=$pdo->prepare("SELECT * FROM students WHERE email=:em ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':em'=>$email]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($row=$st->fetch(PDO::FETCH_ASSOC)) return $row;
        }
        if ($username) {
            $st=$pdo->prepare("SELECT * FROM students WHERE username=:un ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':un'=>$username]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($row=$st->fetch(PDO::FETCH_ASSOC)) return $row;
        }
    } catch(Throwable $e){}
    return null;
}

$student=findStudent($pdo, $email, $username, $code_ecole);
if (!$student){ echo "Élève introuvable."; exit; }
$studentId=(int)$student['id'];

// Lire soumission (vérifier propriétaire)
$sub=null;
try{
    $sql="SELECT * FROM quiz_submissions WHERE id=:id AND student_id=:sid ".($code_ecole?'AND code_ecole=:ce ':'')." LIMIT 1";
    $st=$pdo->prepare($sql);
    $p=[ ':id'=>$submissionId, ':sid'=>$studentId ]; if ($code_ecole) $p[':ce']=$code_ecole;
    $st->execute($p);
    $sub=$st->fetch(PDO::FETCH_ASSOC);
}catch(Throwable $e){}

if(!$sub){ echo "Soumission introuvable."; exit; }

// Quiz
$quiz=null;
try{
    $st=$pdo->prepare("SELECT * FROM quizzes WHERE id=:id");
    $st->execute([':id'=>$sub['quiz_id']]);
    $quiz=$st->fetch(PDO::FETCH_ASSOC);
}catch(Throwable $e){}

if(!$quiz){ echo "Quiz introuvable."; exit; }

// Questions + réponses
$questions=[];
$answers=[];
try{
    $st=$pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=:qid ORDER BY sort_order, id");
    $st->execute([':qid'=>$quiz['id']]);
    $questions=$st->fetchAll(PDO::FETCH_ASSOC);

    $st=$pdo->prepare("SELECT * FROM quiz_answers WHERE submission_id=:sid");
    $st->execute([':sid'=>$submissionId]);
    while($r=$st->fetch(PDO::FETCH_ASSOC)){
        $answers[(int)$r['question_id']]=$r;
    }
}catch(Throwable $e){}

$totalPossible = 0;
foreach ($questions as $Q) $totalPossible += (int)$Q['points'];

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>Résultat | <?= h($quiz['title']) ?></title>
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
        .q-item{border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:10px;background:#fff}
        .muted{color:#6b7280}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3>Résultat — <?= h($quiz['title']) ?></h3>
                <p class="muted">
                    Score obtenu :
                    <strong><?= is_null($sub['score_obtained']) ? '—' : ((int)$sub['score_obtained'].' / '.$totalPossible) ?></strong>
                    &nbsp;•&nbsp; Soumis le : <?= h($sub['submitted_at'] ?? $sub['started_at']) ?>
                </p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (!$questions): ?>
                        <div class="alert alert-warning">Aucune question trouvée.</div>
                    <?php else: foreach ($questions as $i=>$Q):
                        $qid=(int)$Q['id']; $type=strtolower((string)$Q['type']);
                        $ans=$answers[$qid] ?? null;

                        $choices = []; $corrIdx = null; $correctText = null;
                        if ($type==='qcm') {
                            $choices = json_decode((string)$Q['choices_json'], true) ?: [];
                            $correct = json_decode((string)$Q['correct_json'], true) ?: [];
                            if (!empty($correct)) {
                                $corrIdx = (int)$correct[0];
                                $correctText = $choices[$corrIdx] ?? null;
                            }
                        }
                        $isOk = ($ans && !is_null($ans['is_correct'])) ? ((int)$ans['is_correct']===1) : null;
                        $awarded = $ans ? $ans['points_awarded'] : null;
                    ?>
                        <div class="q-item">
                            <div class="d-flex justify-content-between">
                                <h5 class="mb-1">Question <?= $i+1 ?> (<?= (int)$Q['points'] ?> pt<?= ((int)$Q['points'])>1?'s':'' ?>)</h5>
                                <span class="muted"><?= strtoupper($type) ?></span>
                            </div>
                            <div class="mb-2"><?= nl2br(h($Q['question_text'])) ?></div>

                            <?php if ($type==='qcm'): ?>
                                <ul class="mb-2">
                                    <?php foreach ($choices as $idx=>$label): ?>
                                        <li>
                                            <?= h($label) ?>
                                            <?php if ($idx === $corrIdx): ?>
                                                <span class="badge badge-success">Bonne réponse</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div>
                                    <strong>Votre réponse :</strong>
                                    <?= $ans && $ans['answer_text'] ? h($ans['answer_text']) : '<em>Non répondu</em>' ?>
                                    <?php if (!is_null($isOk)): ?>
                                        <?php if ($isOk): ?>
                                            <span class="badge badge-success ml-2">Correct</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger ml-2">Incorrect</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="muted">Points obtenus : <?= is_null($awarded)?'—':(int)$awarded ?></div>

                            <?php else: // QR ?>
                                <div class="mb-2">
                                    <strong>Votre réponse :</strong><br>
                                    <?= $ans && $ans['answer_text'] ? nl2br(h($ans['answer_text'])) : '<em>Non répondu</em>' ?>
                                </div>
                                <?php if (!empty($Q['expected_answer'])): ?>
                                    <div class="muted">
                                        <strong>Réponse attendue :</strong><br>
                                        <?= nl2br(h($Q['expected_answer'])) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="muted">Points : à correction manuelle</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>

                    <div class="mt-3">
                        <a class="btn btn-outline-secondary" href="mes_quiz.php">Retour à mes quiz</a>
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
