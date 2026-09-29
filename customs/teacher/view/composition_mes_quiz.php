<?php
// customs/teacher/view/composition_mes_quiz.php
// Création de quiz (manuel + import CSV tolérant) pour un cours du professeur.

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
if (!($pdo instanceof PDO)) { http_response_code(500); exit('DB error'); }

// ---- Sécurité : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username   = $_SESSION['username'] ?? null;
$email      = $_SESSION['email'] ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s){ return trim((string)$s); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

// --------- Normalisation de colonnes (casse/accents/espaces ignorés) ----------
function normalize_col(string $s): string {
    $s = preg_replace('/^\xEF\xBB\xBF/u','', $s); // BOM
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); // retire accents
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/','_', $s);
    $s = trim($s,'_');
    return $s;
}
function build_header_map(array $header): array {
    // Canonique => liste de synonymes
    $syn = [
        'question'  => ['question','questions','enonce','enonce_','intitule','intitule_','intitule_de_la_question','q','prompt','titre','libelle','libelle_'],
        'choice_a'  => ['choice_a','choix_a','option_a','reponse_a','reponse_a_','a'],
        'choice_b'  => ['choice_b','choix_b','option_b','reponse_b','reponse_b_','b'],
        'choice_c'  => ['choice_c','choix_c','option_c','reponse_c','reponse_c_','c'],
        'choice_d'  => ['choice_d','choix_d','option_d','reponse_d','reponse_d_','d'],
        'correct'   => ['correct','bonne_reponse','bonne_reponse_','reponse_correcte','reponse_correcte_','answer','bonne','solution']
    ];
    $normHeader = array_map('normalize_col', $header);
    $map = []; // canonique => index
    foreach ($syn as $canon=>$list) {
        foreach ($list as $alias) {
            $i = array_search($alias, $normHeader, true);
            if ($i !== false) { $map[$canon] = $i; break; }
        }
    }
    return $map;
}

// ---------- Helpers d’accès ----------
function findClassIdsForTeacher(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): array {
    $ids=[];
    if ($usersId && $codeEcole){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:u AND code_ecole=:ec");
        $st->execute([':u'=>$usersId,':ec'=>$codeEcole]); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($usersId){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:u");
        $st->execute([':u'=>$usersId]); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername && $codeEcole){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un AND code_ecole=:ec");
        $st->execute([':un'=>$sessionUsername,':ec'=>$codeEcole]); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername){
        $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un");
        $st->execute([':un'=>$sessionUsername]); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionEmail){
        if ($codeEcole){
            $st=$pdo->prepare("SELECT id FROM teacher WHERE email=:em AND code_ecole=:ec");
            $st->execute([':em'=>$sessionEmail,':ec'=>$codeEcole]);
            $tids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
            if ($tids){
                $in=implode(',', array_fill(0,count($tids),'?')); $p=$tids; $p[]=$codeEcole;
                $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in) AND code_ecole=?");
                $st->execute($p); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
            }
        }
        $st=$pdo->prepare("SELECT id FROM teacher WHERE email=:em");
        $st->execute([':em'=>$sessionEmail]);
        $tids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
        if ($tids){
            $in=implode(',', array_fill(0,count($tids),'?'));
            $st=$pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in)");
            $st->execute($tids); $ids=array_merge($ids, array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
        }
    }
    $ids=array_values(array_unique(array_filter($ids,fn($v)=>$v>0)));
    return $ids;
}
function assertCourseOwnedByTeacher(PDO $pdo, int $coursId, array $classIds, ?string $codeEcole): array {
    if (!$coursId || !$classIds) throw new RuntimeException("Cours invalide ou aucune classe pour ce prof.");
    $in = implode(',', array_fill(0, count($classIds), '?'));
    $sql = "SELECT id, nom, class FROM cours WHERE id=? AND class IN ($in)";
    if ($codeEcole) $sql .= " AND code_ecole=?";
    $params = array_merge([$coursId], $classIds);
    if ($codeEcole) $params[] = $codeEcole;
    $st=$pdo->prepare($sql); $st->execute($params);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException("Accès refusé : ce cours ne vous appartient pas.");
    return $row; // [id, nom, class]
}
function fetchLeconsByCours(PDO $pdo, int $coursId): array {
    $st=$pdo->prepare("SELECT id, titre, ordre FROM lecons WHERE cours_id=? ORDER BY ordre, id");
    $st->execute([$coursId]); return $st->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Contexte ----------
$flash = '';
$alert = '';
$coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
try {
    $classIds = findClassIdsForTeacher($pdo, $userId, $username, $email, $code_ecole);
    if (!$classIds) throw new RuntimeException("Aucune classe associée à votre profil.");
    if (!$coursId) throw new RuntimeException("Paramètre cours_id manquant.");
    $cours = assertCourseOwnedByTeacher($pdo, $coursId, $classIds, $code_ecole);
} catch (Throwable $e){
    $alert = '<div class="alert alert-danger">'.h($e->getMessage()).'</div>';
    $cours = null;
}
$lecons = $cours ? fetchLeconsByCours($pdo, (int)$cours['id']) : [];

// ---------- Import CSV -> Précharger dans le builder (JS) ----------
$questionsFromCSV = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import_csv'])) {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $flash = '<div class="alert alert-danger">CSRF invalide.</div>';
    } elseif (!empty($_FILES['csv_file']['tmp_name'])) {
        $f = $_FILES['csv_file']['tmp_name'];

        // Détection délimiteur (',' ou ';')
        $line = '';
        $h = fopen($f, 'r');
        if ($h) { $line = fgets($h); fclose($h); }
        $delim = (substr_count((string)$line, ';') > substr_count((string)$line, ',')) ? ';' : ',';

        if (($h = fopen($f,'r')) !== false) {
            $header = fgetcsv($h, 10000, $delim);
            if ($header !== false) {
                $map = build_header_map($header);

                // Exigences minimales: question + correct + A + B
                $need = ['question','correct','choice_a','choice_b'];
                $missing = array_values(array_filter($need, fn($k)=>!array_key_exists($k,$map)));
                if ($missing) {
                    $flash = '<div class="alert alert-danger">Colonnes manquantes (min): '.h(implode(', ', $missing)).'</div>';
                } else {
                    $letters = ['A'=>0,'B'=>1,'C'=>2,'D'=>3];
                    while (($row = fgetcsv($h, 100000, $delim)) !== false) {
                        // question
                        $q = clean($row[$map['question']] ?? '');
                        if ($q === '') continue;

                        // choices (C/D facultatives)
                        $choices = [];
                        $choices[0] = isset($map['choice_a']) ? clean($row[$map['choice_a']] ?? '') : '';
                        $choices[1] = isset($map['choice_b']) ? clean($row[$map['choice_b']] ?? '') : '';
                        $choices[2] = isset($map['choice_c']) ? clean($row[$map['choice_c']] ?? '') : '';
                        $choices[3] = isset($map['choice_d']) ? clean($row[$map['choice_d']] ?? '') : '';
                        // au moins A et B non vides
                        $minChoices = array_filter([$choices[0],$choices[1]], fn($v)=>$v!=='');
                        if (count($minChoices) < 2) continue;

                        // correct : lettre / index / texte
                        $cvRaw = clean($row[$map['correct']] ?? '');
                        $correctIdx = null;
                        if ($cvRaw !== '') {
                            $up = strtoupper($cvRaw);
                            if (isset($letters[$up])) {
                                $correctIdx = $letters[$up];
                            } elseif (ctype_digit($cvRaw)) {
                                $i=(int)$cvRaw; // 1..4 ou 0..3
                                if ($i>=1 && $i<=4) $correctIdx = $i-1;
                                elseif ($i>=0 && $i<=3) $correctIdx = $i;
                            } else {
                                // tente correspondance par texte exact (insensible à la casse/trim)
                                for ($i=0;$i<4;$i++){
                                    if ($choices[$i]!=='' && mb_strtolower(trim($choices[$i])) === mb_strtolower(trim($cvRaw))) {
                                        $correctIdx = $i; break;
                                    }
                                }
                            }
                        }
                        // borne
                        if ($correctIdx===null || $correctIdx<0 || $correctIdx>3 || $choices[$correctIdx]==='') {
                            // si on ne trouve pas, on force sur A (pour corriger à la main)
                            $correctIdx = 0;
                        }

                        $questionsFromCSV[] = [
                            'text'=>$q,
                            'points'=>1,
                            'choices'=>$choices,
                            'correct'=>$correctIdx
                        ];
                    }
                    if (!$flash) $flash = '<div class="alert alert-success">Import CSV effectué — questions chargées dans le formulaire.</div>';
                }
            } else {
                $flash = '<div class="alert alert-danger">Entête CSV introuvable.</div>';
            }
            fclose($h);
        } else {
            $flash = '<div class="alert alert-danger">Impossible de lire le fichier CSV.</div>';
        }
    } else {
        $flash = '<div class="alert alert-warning">Aucun fichier sélectionné.</div>';
    }
}

// ---------- Enregistrement ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_quiz'])) {
    try {
        if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
            throw new RuntimeException('CSRF invalide.');
        }
        if (!$cours) throw new RuntimeException('Cours invalide.');

        $title    = clean($_POST['title'] ?? '');
        $descr    = clean($_POST['content_title'] ?? '');
        $typeEval = strtolower(clean($_POST['type_eval'] ?? 'examen'));
        $mode     = strtolower(clean($_POST['mode'] ?? 'qcm'));
        $due      = clean($_POST['due_date'] ?? '');
        $isPub    = isset($_POST['is_published']) ? 1 : 0;

        $mapType = ['examen'=>'Examen','exercice'=>'Exercice','devoir'=>'Devoir','interrogation'=>'Interrogation'];
        $mapMode = ['qcm'=>'QCM','qr'=>'QR'];
        $dbType  = $mapType[$typeEval] ?? 'Examen';
        $dbMode  = $mapMode[$mode] ?? 'QCM';
        $dbDue   = ($due !== '' ? $due : null);

        $questions = isset($_POST['q']) && is_array($_POST['q']) ? $_POST['q'] : [];
        if ($title==='') throw new RuntimeException('Le titre du quiz est requis.');
        if ($descr==='') throw new RuntimeException('Le titre du contenu (affiché aux élèves) est requis.');
        if (!$questions) throw new RuntimeException('Ajoutez au moins une question.');

        $total = 0;
        foreach ($questions as $q) { $pts=(int)($q['points'] ?? 0); if($pts<0) $pts=0; $total += $pts; }

        $pdo->beginTransaction();
        $ins = $pdo->prepare("
            INSERT INTO quizzes
                (class_id, teacher_user_id, code_ecole, title, description, type_eval, mode_questions, overall_score, due_date, is_published)
            VALUES
                (:cid, :tid, :ce, :t, :d, :te, :mo, :score, :due, :pub)
        ");
        $ins->execute([
            ':cid'=>(int)$cours['class'],
            ':tid'=>$userId,
            ':ce'=>$code_ecole,
            ':t'=>$title,
            ':d'=>($descr!==''?$descr:null),
            ':te'=>$dbType,
            ':mo'=>$dbMode,
            ':score'=>$total,
            ':due'=>$dbDue,
            ':pub'=>$isPub
        ]);
        $quizId = (int)$pdo->lastInsertId();

        $sort = 1;
        if ($dbMode==='QCM') {
            $qi = $pdo->prepare("
                INSERT INTO quiz_questions
                    (quiz_id, question_text, `type`, points, choices_json, correct_json, sort_order)
                VALUES
                    (:qid, :qt, 'qcm', :pts, :choices, :correct, :ord)
            ");
            foreach ($questions as $q) {
                $qt  = clean($q['text'] ?? '');
                $pts = (int)($q['points'] ?? 0);
                $choices = [];
                if (isset($q['choices']) && is_array($q['choices'])) {
                    foreach ($q['choices'] as $c) { $c=clean($c); if($c!=='') $choices[]=$c; }
                }
                if ($qt==='' || $pts<0 || count($choices)<2) throw new RuntimeException("Question QCM invalide (min 2 choix).");

                $correct = isset($q['correct']) ? (string)$q['correct'] : '';
                if ($correct==='' || !ctype_digit($correct)) throw new RuntimeException("Bonne réponse manquante.");
                $idx=(int)$correct; if ($idx<0 || $idx>=count($choices)) throw new RuntimeException("Index réponse hors limite.");

                $qi->execute([
                    ':qid'=>$quizId, ':qt'=>$qt, ':pts'=>$pts,
                    ':choices'=>json_encode($choices, JSON_UNESCAPED_UNICODE),
                    ':correct'=>json_encode([$idx], JSON_UNESCAPED_UNICODE),
                    ':ord'=>$sort++
                ]);
            }
        } else {
            $qi = $pdo->prepare("
                INSERT INTO quiz_questions
                    (quiz_id, question_text, `type`, points, expected_answer, sort_order)
                VALUES
                    (:qid, :qt, 'qr', :pts, :exp, :ord)
            ");
            foreach ($questions as $q) {
                $qt  = clean($q['text'] ?? '');
                $pts = (int)($q['points'] ?? 0);
                $exp = clean($q['expected'] ?? '');
                if ($qt==='' || $pts<0) throw new RuntimeException("Question QR invalide.");
                $qi->execute([':qid'=>$quizId,':qt'=>$qt,':pts'=>$pts,':exp'=>($exp!==''?$exp:null),':ord'=>$sort++]);
            }
        }

        $pdo->commit();
        header('Location: list_des_quiz.php?msg=created');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flash = '<div class="alert alert-danger">Erreur enregistrement : '.h($e->getMessage()).'</div>';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Composer un quiz | MyKelasi</title>
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
        .q-item{border:1px solid #e7e7e7;border-radius:12px;padding:12px;margin-bottom:12px;background:#fff}
        .q-item h6{margin-bottom:10px}
        .remove-q{position:absolute;right:14px;top:10px}
        .choice-row{display:flex;gap:.5rem;margin-bottom:.5rem}
        .choice-row input{flex:1}
        .muted{color:#6b7280}
        .k-toolbar{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end}
        .k-toolbar .form-control{min-width:260px}
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
                <h3>Composer un quiz</h3>
                <?php if ($cours): ?>
                    <ul><li>Cours</li><li><?= h($cours['nom']) ?></li></ul>
                <?php endif; ?>
            </div>

            <?= $alert ?>
            <?= $flash ?>

            <?php if ($cours): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <form class="k-toolbar" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="lecon_id"><strong>Leçon concernée</strong></label>
                            <select id="lecon_id" name="lecon_id" class="form-control">
                                <option value="">— Toutes les leçons —</option>
                                <?php foreach ($lecons as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>">Leçon <?= (int)$l['ordre'] ?> — <?= h($l['titre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="muted d-block">Uniquement pour préremplir les titres (non stocké dans `quizzes`).</small>
                        </div>

                        <div class="form-group">
                            <label for="csv_file"><strong>Importer un quiz (CSV)</strong></label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control-file" accept=".csv">
                            <small class="muted d-block">
                                Entêtes acceptées (synonymes) :<br>
                                • <b>question</b> = Énoncé, Intitulé, Q, Prompt…<br>
                                • <b>choice_a..d</b> = A, choix_a, option_a, réponse_a… (C/D facultatifs)<br>
                                • <b>correct</b> = bonne_réponse, answer, solution (valeurs A/B/C/D, 1/2/3/4 ou texte du choix)
                            </small>
                        </div>

                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <button type="submit" name="import_csv" class="btn btn-outline-primary">
                            <i class="fas fa-file-import"></i> Importer CSV
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form id="quizForm" method="post">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="save_quiz" value="1">

                        <div class="row">
                            <div class="col-lg-8 col-12 form-group">
                                <label>Titre du quiz (interne prof)</label>
                                <input type="text" class="form-control" name="title" id="title" placeholder="Ex: Quiz — Leçon X" required>
                                <small class="muted">Non affiché aux élèves.</small>
                            </div>
                            <div class="col-lg-4 col-12 form-group">
                                <label>Échéance (optionnel)</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-8 col-12 form-group">
                                <label>Titre du contenu (affiché aux élèves)</label>
                                <input type="text" class="form-control" name="content_title" id="content_title" placeholder="Ex: Évaluation — Leçon X" required>
                            </div>
                            <div class="col-lg-4 col-12 form-group">
                                <label>Publier</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published">
                                    <label class="form-check-label" for="is_published">Publier immédiatement</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4 col-12 form-group">
                                <label>Type d’évaluation</label>
                                <select name="type_eval" class="form-control">
                                    <option value="examen">Examen</option>
                                    <option value="exercice">Exercice</option>
                                    <option value="devoir">Devoir</option>
                                    <option value="interrogation">Interrogation</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-12 form-group">
                                <label>Mode des questions</label>
                                <select name="mode" id="modeSelect" class="form-control">
                                    <option value="qcm">QCM (choix unique)</option>
                                    <option value="qr">Question - Réponse</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-12 form-group">
                                <label>&nbsp;</label>
                                <div><span class="muted">Total points : <strong id="totalPoints">0</strong></span></div>
                            </div>
                        </div>

                        <hr>
                        <h5>Questions</h5>
                        <div id="questionsContainer"></div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" id="addQuestionBtn">+ Ajouter une question</button>
                            <button type="button" class="btn btn-outline-danger ml-2" id="clearAllBtn">Supprimer toutes les questions</button>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">💾 Enregistrer</button>
                            <a href="list_des_quiz.php" class="btn-fill-lg bg-blue-dark btn-hover-yellow">Annuler</a>
                        </div>
                    </form>
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

<script>
(function(){
    var qIndex = 0;
    var container = document.getElementById('questionsContainer');
    var addBtn = document.getElementById('addQuestionBtn');
    var clearBtn = document.getElementById('clearAllBtn');
    var totalSpan = document.getElementById('totalPoints');
    var modeSelect = document.getElementById('modeSelect');
    var leconSel = document.getElementById('lecon_id');
    var titleInp = document.getElementById('title');
    var contentTitleInp = document.getElementById('content_title');

    if (leconSel){
        leconSel.addEventListener('change', function(){
            var txt = leconSel.options[leconSel.selectedIndex].text || '';
            var base = txt ? txt.replace(/^Leçon\\s*\\d+\\s*—\\s*/i,'') : '';
            if (!titleInp.value) titleInp.value = 'Quiz — ' + (base || 'Toutes les leçons');
            if (!contentTitleInp.value) contentTitleInp.value = 'Évaluation — ' + (base || 'Toutes les leçons');
        });
    }

    function updateTotal(){
        var sum=0;
        container.querySelectorAll('[name$="[points]"]').forEach(function(inp){
            var v=parseInt(inp.value||'0',10); if(!isNaN(v)&&v>0) sum+=v;
        });
        totalSpan.textContent=sum;
    }

    function addQCM(pref){
        var idx=qIndex++;
        var wrap=document.createElement('div');
        wrap.className='q-item position-relative';
        var q = pref || {};
        var text = q.text||'';
        var pts = (q.points!=null)? q.points : 1;
        var choices = Array.isArray(q.choices)? q.choices : ["","","",""];
        // garantir 4 cases
        for (var i=0;i<4;i++){ if (typeof choices[i]==='undefined') choices[i]=''; }
        var correct = (q.correct!=null)? q.correct : '';

        var letters=['A','B','C','D'];
        var choicesHtml='';
        for (var i=0;i<4;i++){
            choicesHtml += '<div class="choice-row">'+
                '<span class="mr-1">'+letters[i]+'.</span>'+
                '<input type="text" class="form-control" name="q['+idx+'][choices][]" value="'+(choices[i]||'')+'" placeholder="Choix '+letters[i]+'" '+(i<2?'required':'')+'>'+
            '</div>';
        }

        wrap.innerHTML =
            '<button type="button" class="btn btn-sm btn-danger remove-q">Supprimer</button>'+
            '<h6>QCM #'+(idx+1)+'</h6>'+
            '<div class="form-group">'+
                '<label>Énoncé</label>'+
                '<textarea class="form-control" name="q['+idx+'][text]" rows="2" required>'+text+'</textarea>'+
            '</div>'+
            '<div class="form-group">'+
                '<label>Points</label>'+
                '<input type="number" class="form-control" name="q['+idx+'][points]" value="'+pts+'" min="0">'+
            '</div>'+
            '<div class="form-group">'+
                '<label>Propositions</label>'+ choicesHtml +
            '</div>'+
            '<div class="form-group">'+
                '<label>Bonne réponse</label>'+
                '<select name="q['+idx+'][correct]" class="form-control" required>'+
                    '<option value="">-- Sélectionner --</option>'+
                    '<option value="0" '+(String(correct)==='0'?'selected':'')+'>A</option>'+
                    '<option value="1" '+(String(correct)==='1'?'selected':'')+'>B</option>'+
                    '<option value="2" '+(String(correct)==='2'?'selected':'')+'>C</option>'+
                    '<option value="3" '+(String(correct)==='3'?'selected':'')+'>D</option>'+
                '</select>'+
            '</div>';

        container.appendChild(wrap);
        bindCommon(wrap);
    }

    function addQR(pref){
        var idx=qIndex++;
        var wrap=document.createElement('div');
        wrap.className='q-item position-relative';
        var q=pref||{};
        var text=q.text||'';
        var pts=(q.points!=null)? q.points : 1;
        var exp=q.expected||'';

        wrap.innerHTML =
            '<button type="button" class="btn btn-sm btn-danger remove-q">Supprimer</button>'+
            '<h6>Question-Réponse #'+(idx+1)+'</h6>'+
            '<div class="form-group">'+
                '<label>Énoncé</label>'+
                '<textarea class="form-control" name="q['+idx+'][text]" rows="2" required>'+text+'</textarea>'+
            '</div>'+
            '<div class="form-group">'+
                '<label>Points</label>'+
                '<input type="number" class="form-control" name="q['+idx+'][points]" value="'+pts+'" min="0">'+
            '</div>'+
            '<div class="form-group">'+
                '<label>Réponse attendue (facultatif)</label>'+
                '<textarea class="form-control" name="q['+idx+'][expected]" rows="2">'+exp+'</textarea>'+
            '</div>';

        container.appendChild(wrap);
        bindCommon(wrap);
    }

    function bindCommon(wrap){
        wrap.querySelector('.remove-q').addEventListener('click', function(){ wrap.remove(); updateTotal(); });
        wrap.querySelectorAll('[name]').forEach(function(el){ el.addEventListener('input', updateTotal); });
        updateTotal();
    }

    function addOne(){
        if (modeSelect.value==='qcm') addQCM();
        else addQR();
    }

    document.getElementById('addQuestionBtn').addEventListener('click', addOne);
    document.getElementById('clearAllBtn').addEventListener('click', function(){
        if (confirm('Supprimer toutes les questions ?')) { container.innerHTML=''; qIndex=0; updateTotal(); }
    });
    document.getElementById('quizForm').addEventListener('submit', function(e){
        // petite vérif : pour chaque QCM, s’il y a moins de 2 choix non vides -> bloquer
        if (modeSelect.value==='qcm'){
            var blocks = container.querySelectorAll('.q-item');
            for (var b=0;b<blocks.length;b++){
                var choices = blocks[b].querySelectorAll('input[name^="q["][name$="[choices][]"]');
                var nonEmpty=0;
                choices.forEach(function(c){ if ((c.value||'').trim()!=='') nonEmpty++; });
                if (nonEmpty<2){ alert('Chaque QCM doit avoir au moins deux choix.'); e.preventDefault(); return false; }
            }
        }
    });

    // Démarrage
    addOne();

    // Précharger depuis import CSV
    <?php if (!empty($questionsFromCSV)): ?>
        container.innerHTML=''; qIndex=0; updateTotal();
        <?php foreach ($questionsFromCSV as $q): ?>
            addQCM(<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>);
        <?php endforeach; ?>
    <?php endif; ?>
})();
</script>
</body>
</html>
