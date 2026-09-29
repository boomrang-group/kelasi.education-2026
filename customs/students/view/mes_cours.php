<?php
// customs/eleve/view/mes_cours.php
// Navigation élève : Cours → Leçons (verrouillées/déverrouillées selon quiz de la leçon précédente)
//
// RÈGLE DE VERROUILLAGE PAR LEÇON
// - Leçon 1 : toujours déverrouillée.
// - Leçon n>1 : si la leçon (n-1) a un quiz publié, alors la leçon n reste verrouillée tant que l'élève n'a pas soumis (ou réussi) ce quiz.
//               sinon (pas de quiz publié sur n-1), la leçon n est déverrouillée.
//
// DÉTECTION QUIZ D’UNE LEÇON
// - On cherche d’abord un quiz publié dont le title LIKE "Quiz — <lecon_titre>%"
// - À défaut, on cherche un quiz publié dont description LIKE "<cours_nom> - <lecon_titre> - %"
//
// PARAMS / URL à adapter selon ton app :
const URL_VIEW_LESSON = 'lecon_detail.php';  // ?id=<lecon_id>
const URL_PASS_QUIZ   = 'quiz_passer.php';   // ?id=<quiz_id>

// Option réussite minimale (sinon, simple soumission suffit pour déverrouiller)
const REQUIRE_MIN_SCORE = false; // false => soumission suffit ; true => exige PASS_MIN_PCT
const PASS_MIN_PCT      = 0.50;  // 50%

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

// --- Trouver l'élève (students.*)
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

// --- Charger Cours + Leçons (triées)
function getCoursesAndLessons(PDO $pdo, int $classId, ?string $codeEcole): array {
    $out=[];
    try{
        // Cours
        $sqlC="SELECT id, nom FROM cours WHERE class=:cid ".($codeEcole?'AND code_ecole=:ce ':'')." ORDER BY nom, id";
        $stC=$pdo->prepare($sqlC);
        $pC=[':cid'=>$classId]; if($codeEcole) $pC[':ce']=$codeEcole;
        $stC->execute($pC);
        $courses=$stC->fetchAll(PDO::FETCH_ASSOC);

        foreach($courses as $c){
            $sqlL="SELECT id, titre, ordre FROM lecons WHERE cours_id=:cid ORDER BY ordre, id";
            $stL=$pdo->prepare($sqlL);
            $stL->execute([':cid'=>$c['id']]);
            $less=$stL->fetchAll(PDO::FETCH_ASSOC);

            $out[]=[
                'course_id'=>(int)$c['id'],
                'course_name'=>$c['nom'],
                'lessons'=>array_map(fn($r)=>[
                    'lecon_id'=>(int)$r['id'],
                    'lecon_titre'=>$r['titre'],
                    'lecon_ordre'=>(int)$r['ordre']
                ],$less)
            ];
        }
    }catch(Throwable $e){}
    return $out;
}

// --- Trouver quiz publié d'une leçon (voir stratégie en haut)
function findPublishedLessonQuiz(PDO $pdo, int $classId, ?string $codeEcole, string $courseName, string $lessonTitle): ?array {
    try {
        // 1) title LIKE "Quiz — <lessonTitle>%"
        $sql1="SELECT * FROM quizzes
               WHERE class_id=:cid ".($codeEcole?'AND code_ecole=:ce ':'')."
                 AND is_published=1 AND title LIKE :t
               ORDER BY created_at DESC, id DESC LIMIT 1";
        $st1=$pdo->prepare($sql1);
        $p1=[':cid'=>$classId, ':t'=>'Quiz — '.$lessonTitle.'%']; if($codeEcole) $p1[':ce']=$codeEcole;
        $st1->execute($p1);
        if ($q=$st1->fetch(PDO::FETCH_ASSOC)) return $q;

        // 2) description LIKE "<cours> - <leçon> - %"
        $prefix=$courseName.' - '.$lessonTitle.' - ';
        $sql2="SELECT * FROM quizzes
               WHERE class_id=:cid ".($codeEcole?'AND code_ecole=:ce ':'')."
                 AND is_published=1 AND description LIKE :d
               ORDER BY created_at DESC, id DESC LIMIT 1";
        $st2=$pdo->prepare($sql2);
        $p2=[':cid'=>$classId, ':d'=>$prefix.'%']; if($codeEole??false){/*typo guard*/} if($codeEcole) $p2[':ce']=$codeEcole;
        $st2->execute($p2);
        if ($q=$st2->fetch(PDO::FETCH_ASSOC)) return $q;

    }catch(Throwable $e){}
    return null;
}

// --- L'élève a-t-il "passé" le quiz ?
function studentPassedQuiz(PDO $pdo, int $quizId, int $studentId, int $overall): array {
    // return ['has_submission'=>bool,'passed'=>bool,'score'=>int|null]
    $res=['has_submission'=>false,'passed'=>false,'score'=>null];
    try{
        $st=$pdo->prepare("SELECT STATUS, score_obtained FROM quiz_submissions WHERE quiz_id=:qid AND student_id=:sid ORDER BY submitted_at DESC, id DESC LIMIT 1");
        $st->execute([':qid'=>$quizId, ':sid'=>$studentId]);
        if ($row=$st->fetch(PDO::FETCH_ASSOC)){
            $res['has_submission']=in_array(strtolower((string)$row['STATUS']),['submitted','graded'],true);
            $res['score']= is_null($row['score_obtained'])?null:(int)$row['score_obtained'];
            if (!REQUIRE_MIN_SCORE){
                $res['passed']=$res['has_submission'];
            }else{
                if ($res['has_submission'] && !is_null($res['score']) && $overall>0){
                    $res['passed']= ($res['score'] >= ceil($overall*PASS_MIN_PCT));
                }
            }
        }
    }catch(Throwable $e){}
    return $res;
}

// =====================================================================
//                             CHARGEMENT
// =====================================================================
$alerts='';
$student=findStudent($pdo, $email, $username, $code_ecole);
$courses=[];

if(!$student){
    $alerts='<div class="alert alert-danger">Impossible de trouver votre dossier élève.</div>';
}elseif(empty($student['class_id'])){
    $alerts='<div class="alert alert-warning">Aucune classe n’est associée à votre compte.</div>';
}else{
    $classId=(int)$student['class_id'];
    $studentId=(int)$student['id'];
    $courses=getCoursesAndLessons($pdo, $classId, $code_ecole);

    // Pour chaque cours, associer quiz et état de verrouillage
    foreach($courses as &$course){
        $lessons=&$course['lessons'];
        // Pré-charger quiz de chaque leçon
        foreach($lessons as &$L){
            $quiz=findPublishedLessonQuiz($pdo, $classId, $code_ecole, $course['course_name'], $L['lecon_titre']);
            if ($quiz){
                $st=studentPassedQuiz($pdo, (int)$quiz['id'], $studentId, (int)$quiz['overall_score']);
                $L['quiz']=[
                    'id'=>(int)$quiz['id'],
                    'overall'=>(int)$quiz['overall_score'],
                    'title'=>$quiz['title'],
                    'has_submission'=>$st['has_submission'],
                    'passed'=>$st['passed'],
                    'score'=>$st['score']
                ];
            }else{
                $L['quiz']=null;
            }
        }
        unset($L);
        // Calcul du verrouillage séquentiel (par leçon)
        // Leçon 1 => unlocked.
        for($i=0;$i<count($lessons);$i++){
            if ($i===0){
                $lessons[$i]['locked']=false;
                $lessons[$i]['lock_reason']=null;
                continue;
            }
            $prev=$lessons[$i-1];
            if ($prev['quiz']){ // quiz publié sur leçon précédente
                $lessons[$i]['locked']= !($prev['quiz']['passed']);
                $lessons[$i]['lock_reason']= $lessons[$i]['locked']
                    ? 'Quiz de la leçon précédente non passé'
                    : null;
                // stocker quiz précédent pour bouton
                $lessons[$i]['prev_quiz_id']=$prev['quiz']['id'];
            }else{
                // pas de quiz sur n-1 => déverrouillée
                $lessons[$i]['locked']=false;
                $lessons[$i]['lock_reason']=null;
                $lessons[$i]['prev_quiz_id']=null;
            }
        }
        unset($lessons);
    }
    unset($course);
}
require_once __DIR__ . '/../layout/check_payment.php';
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mes cours | Espace élève</title>
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
    .course-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 16px
    }

    .course-card .card-header {
        background: #f9fafb
    }

    .lesson-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        border: 1px dashed #e5e7eb;
        border-radius: 10px;
        padding: .75rem 1rem;
        margin-bottom: .5rem;
        background: #fff
    }

    .lesson-title {
        font-weight: 600
    }

    .lesson-locked {
        opacity: .7
    }

    .muted {
        color: #6b7280
    }

    .page-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: center
    }

    .search-input {
        max-width: 360px
    }

    .badge-soft {
        background: #eef2ff;
        color: #1f2937;
        border-radius: .5rem;
        padding: .15rem .45rem
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
                <div class="breadcrumbs-area">
                    <h3>Mes cours</h3>
                    <p class="muted">Chaque leçon se débloque après le quiz de la leçon précédente (s’il existe).</p>
                </div>

                <?php if ($alerts) { echo $alerts; } ?>

                <?php if ($courses && !$alerts): ?>
                <div class="card mb-3">
                    <div class="card-body page-actions">
                        <input id="search" class="form-control search-input"
                            placeholder="Rechercher une leçon ou un cours…">
                        <div class="muted"><span class="badge-soft">Astuce</span> Passer un quiz débloque la leçon
                            suivante.</div>
                    </div>
                </div>

                <?php foreach ($courses as $course): ?>
                <div class="card course-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><?= h($course['course_name']) ?></h5>
                        <span class="muted"><?= count($course['lessons']) ?> leçon(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (!$course['lessons']): ?>
                        <div class="muted">Aucune leçon pour ce cours.</div>
                        <?php else: ?>
                        <?php foreach ($course['lessons'] as $idx=>$L): 
                                    $locked = !empty($L['locked']);
                                    $hasQuiz = (bool)$L['quiz'];
                                    $quizId  = $hasQuiz ? (int)$L['quiz']['id'] : null;
                                    $passed  = $hasQuiz ? (bool)$L['quiz']['passed'] : false;
                                    $score   = $hasQuiz ? $L['quiz']['score'] : null;
                                    $overall = $hasQuiz ? $L['quiz']['overall'] : null;
                                    $prevQuizId = $L['prev_quiz_id'] ?? null;

                                    $lessonUrl = URL_VIEW_LESSON.'?id='.(int)$L['lecon_id'];
                                ?>
                        <div class="lesson-row <?= $locked?'lesson-locked':'' ?>"
                            data-search="<?= h(strtolower($course['course_name'].' '.$L['lecon_titre'])) ?>">
                            <div class="lesson-title">
                                <?= h(($idx+1).'. '.$L['lecon_titre']) ?>
                                <?php if ($hasQuiz): ?>
                                <?php if ($passed): ?>
                                <span class="badge badge-success ml-2"><i class="fa fa-check-circle"></i> Quiz
                                    passé<?= is_null($score)?'':(' • '.$score.'/'.$overall) ?></span>
                                <?php else: ?>
                                <span class="badge badge-warning ml-2"><i class="fa fa-exclamation-circle"></i> Quiz à
                                    faire</span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="badge badge-secondary ml-2"><i class="fa fa-question-circle"></i> Aucun
                                    quiz</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex" style="gap:.5rem">
                                <?php if ($locked): ?>
                                <?php if ($prevQuizId): ?>
                                <a class="btn btn-sm btn-primary" href="<?= h(URL_PASS_QUIZ.'?id='.$prevQuizId) ?>">
                                    Passer le quiz de la leçon précédente
                                </a>
                                <?php else: ?>
                                <span class="btn btn-sm btn-outline-secondary disabled">En attente de
                                    déverrouillage</span>
                                <?php endif; ?>
                                <?php else: ?>
                                <a class="btn btn-sm btn-outline-success" href="<?= h($lessonUrl) ?>">
                                    Voir la leçon
                                </a>
                                <?php if ($hasQuiz): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= h(URL_PASS_QUIZ.'?id='.$quizId) ?>">
                                    <?= $passed ? 'Repasser / Voir le quiz' : 'Passer le quiz' ?>
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php elseif(!$alerts): ?>
                <div class="alert alert-info">Aucun cours trouvé pour votre classe.</div>
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
    (function() {
        // Recherche rapide côté client
        var input = document.getElementById('search');
        if (!input) return;
        input.addEventListener('input', function() {
            var q = (this.value || '').toLowerCase();
            document.querySelectorAll('.lesson-row').forEach(function(r) {
                var hay = r.getAttribute('data-search') || '';
                r.style.display = (q === '' || hay.indexOf(q) >= 0) ? '' : 'none';
            });
        });
    })();
    </script>
</body>

</html>