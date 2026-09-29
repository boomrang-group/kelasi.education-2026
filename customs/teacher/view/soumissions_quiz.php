<?php
// customs/teacher/view/soumissions_quiz.php
// Liste des copies pour un quiz du prof (sa classe)

header('Content-Type: text/html; charset=utf-8');
if (session_status()===PHP_SESSION_NONE) session_start();
require_once '../../../database/db_connect.php';

if (empty($_SESSION['role']) || strtolower($_SESSION['role'])!=='prof') {
    header('Location: ../../../login/index.php'); exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$userId     = $_SESSION['user_id']    ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

$quizId = isset($_GET['quiz_id']) && ctype_digit($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// trouver classe affectée au prof
function findClassIdForTeacher(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): ?int {
    if ($usersId && $codeEcole) {
        $st=$pdo->prepare("SELECT class_id FROM class_subject_teacher WHERE teacher_user_id=:uid AND code_ecole=:ce ORDER BY id DESC LIMIT 1");
        $st->execute([':uid'=>$usersId, ':ce'=>$codeEcole]);
        if ($cid=$st->fetchColumn()) return (int)$cid;
    }
    if ($usersId) {
        $st=$pdo->prepare("SELECT class_id FROM class_subject_teacher WHERE teacher_user_id=:uid ORDER BY id DESC LIMIT 1");
        $st->execute([':uid'=>$usersId]);
        if ($cid=$st->fetchColumn()) return (int)$cid;
    }
    if ($sessionUsername && $codeEcole) {
        $st=$pdo->prepare("SELECT class_id FROM class_subject_teacher WHERE username=:un AND code_ecole=:ce ORDER BY id DESC LIMIT 1");
        $st->execute([':un'=>$sessionUsername, ':ce'=>$codeEcole]);
        if ($cid=$st->fetchColumn()) return (int)$cid;
    }
    if ($sessionUsername) {
        $st=$pdo->prepare("SELECT class_id FROM class_subject_teacher WHERE username=:un ORDER BY id DESC LIMIT 1");
        $st->execute([':un'=>$sessionUsername]);
        if ($cid=$st->fetchColumn()) return (int)$cid;
    }
    return null;
}
$classId = findClassIdForTeacher($pdo, $userId, $username, $email, $code_ecole);

// sécurité : le quiz doit appartenir au prof/classe
$quiz = null;
if ($quizId && $classId) {
    $sql="SELECT * FROM quizzes WHERE id=:id AND class_id=:cid ".($code_ecole?'AND code_ecole=:ce ':'')." AND teacher_user_id=:tid LIMIT 1";
    $st=$pdo->prepare($sql);
    $params=[':id'=>$quizId, ':cid'=>$classId, ':tid'=>$userId]; if ($code_ecole) $params[':ce']=$code_ecole;
    $st->execute($params);
    $quiz=$st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$subs=[];
if ($quiz) {
    $sql="SELECT s.*, st.first_name, st.last_name, st.username AS stu_username
          FROM quiz_submissions s
          JOIN students st ON st.id=s.student_id
          WHERE s.quiz_id=:qid ".($code_ecole?'AND s.code_ecole=:ce ':'')."
          ORDER BY s.submitted_at DESC, s.id DESC";
    $st=$pdo->prepare($sql);
    $params=[':qid'=>$quizId]; if ($code_ecole) $params[':ce']=$code_ecole;
    $st->execute($params);
    $subs=$st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html class="no-js" lang="fr">
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
    <link rel="stylesheet" href="../../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../style.css">
</head>
<body>
<div id="preloader" class="d-none"></div>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3>Soumissions du quiz</h3>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (!$quiz): ?>
                        <div class="alert alert-warning">Quiz introuvable ou non autorisé.</div>
                    <?php else: ?>
                        <h4><?= htmlspecialchars($quiz['title']) ?></h4>
                        <div class="mb-3">
                            <a class="btn btn-sm btn-outline-primary" href="list_des_quiz.php">← Retour aux quiz</a>
                        </div>

                        <?php if (!$subs): ?>
                            <div class="text-center text-muted">Aucune soumission.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table display text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Élève</th>
                                            <th>Statut</th>
                                            <th>Soumis le</th>
                                            <th>Note</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $i=1; foreach ($subs as $s): ?>
                                        <tr>
                                            <td><?= $i++; ?></td>
                                            <td><?= htmlspecialchars(($s['first_name']??'').' '.($s['last_name']??'')) ?> <span class="text-muted">(<?= htmlspecialchars($s['stu_username']??'') ?>)</span></td>
                                            <td><?= htmlspecialchars($s['status']) ?></td>
                                            <td><?= htmlspecialchars($s['submitted_at'] ?? '—') ?></td>
                                            <td><?= is_null($s['score_obtained']) ? '—' : (int)$s['score_obtained'].' / '.(int)$quiz['overall_score'] ?></td>
                                            <td>
                                                <?php if ($quiz['mode_questions']==='QR'): ?>
                                                    <a class="btn btn-sm btn-gradient-yellow" href="corriger_quiz.php?submission_id=<?= (int)$s['id'] ?>">Corriger</a>
                                                <?php else: ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="corriger_quiz.php?submission_id=<?= (int)$s['id'] ?>">Voir</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
<script src="../../../js/jquery.dataTables.min.js"></script>
<script src="../../../js/main.js"></script>
</body>
</html>
