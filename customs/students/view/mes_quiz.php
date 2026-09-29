<?php
// customs/eleve/view/mes_quiz.php
// Liste des quiz (publiés) de la classe de l'élève + état de sa dernière soumission (score)

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

// Récupérer quiz publiés de la classe
$quizzes=[];
try{
    $sql="SELECT * FROM quizzes WHERE class_id=:cid ".($code_ecole?'AND code_ecole=:ce ':'')." AND is_published=1 ORDER BY created_at DESC, id DESC";
    $st=$pdo->prepare($sql);
    $p=[ ':cid'=>$classId ]; if ($code_ecole) $p[':ce']=$code_ecole;
    $st->execute($p);
    $quizzes=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){ $quizzes=[]; }

// Dernière soumission de l'élève par quiz
$subs=[];
if ($quizzes){
    $ids = array_map(fn($q)=> (int)$q['id'], $quizzes);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    try{
        $sql="SELECT qs.*
              FROM quiz_submissions qs
              WHERE qs.student_id=? AND qs.quiz_id IN ($in)
              ORDER BY qs.quiz_id, qs.submitted_at DESC, qs.id DESC";
        $args = array_merge([$studentId], $ids);
        $st=$pdo->prepare($sql);
        $st->execute($args);
        while($r=$st->fetch(PDO::FETCH_ASSOC)){
            $qid=(int)$r['quiz_id'];
            if (!isset($subs[$qid])) $subs[$qid]=$r; // garder la plus récente
        }
    }catch(Throwable $e){}
}

require_once __DIR__ . '/../layout/check_payment.php';
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mes quiz | Espace élève</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous" />
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <style>
        .muted{color:#6b7280}
        .badge-soft{background:#eef2ff;color:#111827;border-radius:.5rem;padding:.15rem .45rem}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3>Mes quiz</h3>
                <p class="muted">Passez vos quiz ou consultez vos résultats.</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table display text-nowrap">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre</th>
                                    <th>Type</th>
                                    <th>Mode</th>
                                    <th>Questions</th>
                                    <th>Barème</th>
                                    <th>Échéance</th>
                                    <th>État</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$quizzes): ?>
                                <tr><td colspan="9" class="text-center text-muted">Aucun quiz publié.</td></tr>
                            <?php else: $i=1; foreach ($quizzes as $q):
                                // compter questions
                                $nbq=0; $sumPts=0;
                                try{
                                    $st=$pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(points),0) s FROM quiz_questions WHERE quiz_id=:qid");
                                    $st->execute([':qid'=>$q['id']]);
                                    if ($r=$st->fetch(PDO::FETCH_ASSOC)){ $nbq=(int)$r['c']; $sumPts=(int)$r['s']; }
                                }catch(Throwable $e){}

                                $sub = $subs[(int)$q['id']] ?? null;
                                $etat = 'À faire';
                                $badge = '<span class="badge badge-warning">À faire</span>';
                                $action = '<a class="btn btn-sm btn-primary" href="quiz_passer.php?id='.(int)$q['id'].'">Passer le quiz</a>';
                                if ($sub){
                                    $sc = is_null($sub['score_obtained']) ? '—' : ((int)$sub['score_obtained'].'/'.$q['overall_score']);
                                    $etat = 'Soumis';
                                    $badge = '<span class="badge badge-success">Soumis • '.$sc.'</span>';
                                    $action = '<a class="btn btn-sm btn-outline-secondary" href="resultat_quiz.php?submission_id='.(int)$sub['id'].'">Voir le résultat</a>
                                               <a class="btn btn-sm btn-primary ml-1" href="quiz_passer.php?id='.(int)$q['id'].'">Repasser</a>';
                                }
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= h($q['title']) ?></td>
                                    <td><?= h($q['type_eval']) ?></td>
                                    <td><?= h($q['mode_questions']) ?></td>
                                    <td><?= $nbq ?></td>
                                    <td><?= (int)$q['overall_score'] ?></td>
                                    <td><?= $q['due_date'] ?: '—' ?></td>
                                    <td><?= $badge ?></td>
                                    <td><?= $action ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
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
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/jquery.dataTables.min.js"></script>
<script src="../../../js/main.js"></script>
<script>
try {
    $(document).ready(function(){
        $('.table.display').DataTable({
            pageLength: 10,
            order: [[0,'asc']],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json' }
        });
    });
} catch(e){}
</script>
</body>
</html>
