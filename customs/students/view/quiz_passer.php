<?php
// customs/eleve/view/quiz_passer.php
// Affiche et enregistre une tentative de quiz (QCM réponse unique / QR)

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

$code_ecole = $_SESSION['code_ecole'] ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$quizId = (isset($_GET['id']) && ctype_digit($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) { echo "Quiz invalide."; exit; }

// Trouver l'élève
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

$student = findStudent($pdo, $email, $username, $code_ecole);
if (!$student) { echo "Élève introuvable."; exit; }
$classId   = (int)$student['class_id'];
$studentId = (int)$student['id'];

// Charger quiz (et vérifier même classe)
$quiz = null;
try {
    $sql="SELECT * FROM quizzes WHERE id=:id ".($code_ecole?'AND code_ecole=:ce ':'')." LIMIT 1";
    $st=$pdo->prepare($sql);
    $p=[ ':id'=>$quizId ]; if ($code_ecole) $p[':ce']=$code_ecole;
    $st->execute($p);
    $quiz=$st->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

if (!$quiz || (int)$quiz['class_id'] !== $classId || !(int)$quiz['is_published']) {
    echo "Quiz non disponible.";
    exit;
}

// Questions
$questions=[];
try{
    $st=$pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=:qid ORDER BY sort_order, id");
    $st->execute([':qid'=>$quizId]);
    $questions=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){ $questions=[]; }

// Soumission
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $answers = $_POST['q'] ?? []; // q[<question_id>] => index choisi (QCM) ou texte (QR)
    $now = date('Y-m-d H:i:s');

    // Corriger / Evaluer
    $totalScore = 0;
    $rowsToInsert = []; // pour quiz_answers
    foreach ($questions as $Q) {
        $qid   = (int)$Q['id'];
        $type  = strtolower((string)$Q['type']);
        $pts   = (int)$Q['points'];
        $val   = $answers[$qid] ?? null;
        $isCorr = null; $ptsAward = null; $ansText=null;

        if ($type === 'qcm') {
            $choices = json_decode((string)$Q['choices_json'], true) ?: [];
            $correct = json_decode((string)$Q['correct_json'], true) ?: [];
            if ($val !== null && $val !== '' && ctype_digit((string)$val)) {
                $idx = (int)$val;
                $ansText = isset($choices[$idx]) ? (string)$choices[$idx] : null;
                $isCorrect = (!empty($correct) && $idx === (int)$correct[0]);
                $isCorr = $isCorrect ? 1 : 0;
                $ptsAward = $isCorrect ? $pts : 0;
                $totalScore += $ptsAward;
            } else {
                $ansText = null;
                $isCorr = 0;
                $ptsAward = 0;
            }
        } else { // QR
            $ansText = is_string($val) ? trim($val) : null;
            $isCorr = null; // corrigé plus tard
            $ptsAward = null;
        }

        $rowsToInsert[] = [
            'question_id'=>$qid,
            'answer_text'=>$ansText,
            'is_correct'=>$isCorr,
            'points_awarded'=>$ptsAward
        ];
    }

    // Enregistrer submission + answers
    try {
        $pdo->beginTransaction();

        $insS = $pdo->prepare("INSERT INTO quiz_submissions
            (quiz_id, student_id, class_id, code_ecole, STATUS, score_obtained, started_at, submitted_at)
            VALUES (:qid,:sid,:cid,:ce,'submitted',:score,:start,:sub)");
        $insS->execute([
            ':qid'=>$quizId, ':sid'=>$studentId, ':cid'=>$classId,
            ':ce'=>$code_ecole, ':score'=>$totalScore, ':start'=>$now, ':sub'=>$now
        ]);
        $subId = (int)$pdo->lastInsertId();

        $insA = $pdo->prepare("INSERT INTO quiz_answers
            (submission_id, question_id, selected_option_id, answer_text, is_correct, points_awarded)
            VALUES (:sub,:qid,NULL,:txt,:ok,:pts)");
        foreach ($rowsToInsert as $r) {
            $insA->execute([
                ':sub'=>$subId, ':qid'=>$r['question_id'],
                ':txt'=>$r['answer_text'], ':ok'=>$r['is_correct'], ':pts'=>$r['points_awarded']
            ]);
        }

        $pdo->commit();

        header('Location: resultat_quiz.php?submission_id='.$subId);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Erreur : '.h($e->getMessage());
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= h($quiz['title']) ?> | Quiz</title>
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
        /* Carte question */
        .q-item{border:1px solid #e5e7eb;border-radius:12px;padding:14px 14px 10px;margin-bottom:12px;background:#fff}
        .muted{color:#6b7280}

        /* ---- Radios stylées (choix QCM) ---- */
        .option-list{display:grid;grid-template-columns:1fr;gap:.5rem;margin-top:.35rem}
        @media (min-width: 576px){ .option-list{grid-template-columns:1fr} }

        .option-tile{
            position:relative; display:flex; align-items:flex-start; gap:.75rem;
            border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px;
            background:#fafafa; cursor:pointer; transition:.15s ease-in-out;
        }
        .option-tile:hover{ background:#f5f7fb; border-color:#cbd5e1 }
        .option-tile:focus-within{ outline:2px solid #2563eb33; outline-offset:2px; }

        /* Radio minimaliste */
        .option-tile input[type="radio"]{
            appearance:none; -webkit-appearance:none; -moz-appearance:none;
            width:18px; height:18px; border:2px solid #9aa5b1; border-radius:50%;
            margin-top:2px; flex:0 0 18px; position:relative; cursor:pointer; background:#fff; transition:.15s;
        }
        .option-tile input[type="radio"]:hover{ border-color:#2563eb }
        .option-tile input[type="radio"]:focus{ outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.25) }
        .option-tile input[type="radio"]:checked{
            border-color:#2563eb; background:#2563eb;
            box-shadow:inset 0 0 0 3px #fff;
        }

        .option-label{line-height:1.4; color:#111827}
        .option-help{font-size:.875rem;color:#6b7280}

        /* Etat sélectionné (carte) */
        .option-tile.selected{
            border-color:#2563eb; background:#eef4ff;
            box-shadow:0 2px 10px rgba(37,99,235,.12);
        }

        /* Zone texte QR */
        .qr-text{border-radius:10px}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3><?= h($quiz['title']) ?></h3>
                <p class="muted">Type : <?= h($quiz['type_eval']) ?> • Mode : <?= h($quiz['mode_questions']) ?> • Barème total : <?= (int)$quiz['overall_score'] ?></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if (!$questions): ?>
                <div class="alert alert-warning">Pas de questions pour ce quiz.</div>
            <?php else: ?>
                <form method="post" id="quizForm">
                    <?php foreach ($questions as $i=>$Q):
                        $qid  = (int)$Q['id'];
                        $type = strtolower((string)$Q['type']);
                    ?>
                        <div class="q-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="mb-1">
                                    Question <?= $i+1 ?>
                                    <small class="muted">• <?= (int)$Q['points'] ?> pt<?= ((int)$Q['points'])>1?'s':'' ?></small>
                                </h5>
                                <span class="muted mt-1"><?= strtoupper($type) ?></span>
                            </div>
                            <div class="mb-2"><?= nl2br(h($Q['question_text'])) ?></div>

                            <?php if ($type === 'qcm'):
                                $choices = json_decode((string)$Q['choices_json'], true) ?: [];
                            ?>
                                <div class="option-list" data-group="q<?= $qid ?>">
                                    <?php foreach ($choices as $idx=>$label):
                                        $inputId = "q{$qid}_{$idx}";
                                    ?>
                                        <label class="option-tile" for="<?= $inputId ?>">
                                            <input
                                                class="opt-radio"
                                                type="radio"
                                                name="q[<?= $qid ?>]"
                                                id="<?= $inputId ?>"
                                                value="<?= $idx ?>"
                                            />
                                            <div class="option-label">
                                                <?= h($label) ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <textarea class="form-control qr-text" name="q[<?= $qid ?>]" rows="3" placeholder="Votre réponse"></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-3">
                        <button class="btn btn-primary">Soumettre mes réponses</button>
                        <a class="btn btn-outline-secondary" href="mes_quiz.php">Annuler</a>
                    </div>
                </form>
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
<script>
/* Ajout d'une classe .selected à la tuile du choix coché (pour style carte sélectionnée) */
$(function(){
    function refreshGroup(group){
        const $wrap = $('[data-group="'+group+'"]');
        $wrap.find('.option-tile').removeClass('selected');
        $wrap.find('input.opt-radio:checked').each(function(){
            $(this).closest('.option-tile').addClass('selected');
        });
    }
    $('.option-list').each(function(){
        const group = $(this).data('group');
        refreshGroup(group);
    });
    $(document).on('change', 'input.opt-radio', function(){
        const group = $(this).closest('.option-list').data('group');
        refreshGroup(group);
    });
});
</script>
</body>
</html>
