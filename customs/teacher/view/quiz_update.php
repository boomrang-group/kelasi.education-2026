<?php
// customs/teacher/view/quiz_update.php
// Édition d’un quiz par son propriétaire (prof) + gestion complète des questions (sur la même page).

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

 $DEBUG = DEBUG; // DEBUG handled in db_connect.php
if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';

// ---- Sécurité : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId     = (int)($_SESSION['user_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---------- Helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

function getQuiz(PDO $pdo, int $quizId, int $teacherId, ?string $codeEcole): ?array {
    $sql = "SELECT * FROM quizzes WHERE id=:id AND teacher_user_id=:tid";
    $p = [':id'=>$quizId, ':tid'=>$teacherId];
    if ($codeEcole) { $sql.=" AND code_ecole=:ce"; $p[':ce']=$codeEcole; }
    $st = $pdo->prepare($sql); $st->execute($p);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    return $q ?: null;
}

// === Gestion des questions ===
// Hypothèses de colonnes (quiz_questions) :
// id, quiz_id, type('qcm'|'qr'), question_text, points, options_json(TEXT|JSON), correct_json(TEXT|JSON), order_index(INT), created_at, updated_at

function loadQuestions(PDO $pdo, int $quizId): array {
    try {
        $st=$pdo->prepare("
            SELECT id, quiz_id, `type`, question_text, points, options_json, correct_json, order_index, created_at, updated_at
            FROM quiz_questions
            WHERE quiz_id=:qid
            ORDER BY order_index ASC, id ASC
        ");
        $st->execute([':qid'=>$quizId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e){
        // Si la table/colonnes n'existent pas, renvoyer un tableau vide (la page reste utilisable pour les paramètres)
        return [];
    }
}

function maxOrderIndex(PDO $pdo, int $quizId): int {
    try {
        $st=$pdo->prepare("SELECT COALESCE(MAX(order_index),0) FROM quiz_questions WHERE quiz_id=:qid");
        $st->execute([':qid'=>$quizId]);
        return (int)$st->fetchColumn();
    } catch(Throwable $e){ return 0; }
}

function swapOrder(PDO $pdo, int $quizId, int $idA, int $idB): void {
    $pdo->beginTransaction();
    try {
        $ga = $pdo->prepare("SELECT id, order_index FROM quiz_questions WHERE id=:id AND quiz_id=:qid FOR UPDATE");
        $gb = $pdo->prepare("SELECT id, order_index FROM quiz_questions WHERE id=:id AND quiz_id=:qid FOR UPDATE");
        $ga->execute([':id'=>$idA, ':qid'=>$quizId]); $qa = $ga->fetch(PDO::FETCH_ASSOC);
        $gb->execute([':id'=>$idB, ':qid'=>$quizId]); $qb = $gb->fetch(PDO::FETCH_ASSOC);
        if (!$qa || !$qb) throw new RuntimeException('Questions introuvables.');

        $ua = $pdo->prepare("UPDATE quiz_questions SET order_index=:o WHERE id=:id");
        $ub = $pdo->prepare("UPDATE quiz_questions SET order_index=:o WHERE id=:id");
        $ua->execute([':o'=>$qb['order_index'], ':id'=>$qa['id']]);
        $ub->execute([':o'=>$qa['order_index'], ':id'=>$qb['id']]);

        $pdo->commit();
    } catch(Throwable $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// ---------- Contexte ----------
$quizId   = (int)($_GET['quiz_id'] ?? $_POST['quiz_id'] ?? 0);
$classId  = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$msgParam = '';
$msgQuest = '';
$errorsP  = []; // erreurs paramètres
$errorsQ  = []; // erreurs questions

// Charger quiz existant (sécurité propriétaire)
$quiz = $quizId ? getQuiz($pdo, $quizId, $userId, $code_ecole) : null;
if (!$quiz) { header('Location: list_des_quiz.php?msg=error'); exit; }

// ------- Traitement POST -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check((string)($_POST['_csrf'] ?? ''))) throw new RuntimeException('CSRF invalide.');
        $scope = (string)($_POST['scope'] ?? 'params'); // 'params' ou 'questions'
        $action = (string)($_POST['action'] ?? '');

        // === PARAMÈTRES DU QUIZ ===
        if ($scope === 'params' && $action === 'save_quiz') {
            $title          = trim((string)($_POST['title'] ?? ''));
            $description    = trim((string)($_POST['description'] ?? ''));
            $type_eval      = trim((string)($_POST['type_eval'] ?? ''));
            $mode_questions = strtoupper(trim((string)($_POST['mode_questions'] ?? '')));
            $overall_score  = (int)($_POST['overall_score'] ?? 0);
            $due_date       = trim((string)($_POST['due_date'] ?? ''));
            $is_published   = isset($_POST['is_published']) ? 1 : 0;

            if ($title === '') $errorsP[] = "Le titre est obligatoire.";
            if (!in_array($mode_questions, ['QCM','QR','MIXTE'], true)) $errorsP[] = "Mode invalide (QCM, QR ou MIXTE).";
            if ($overall_score < 0) $errorsP[] = "Le barème ne peut pas être négatif.";

            $dueParam = null;
            if ($due_date !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) $dueParam = $due_date.' 23:59:59';
                else $dueParam = $due_date;
            }

            if (!$errorsP) {
                $sql = "UPDATE quizzes
                           SET title=:title, description=:description, type_eval=:type_eval,
                               mode_questions=:mode, overall_score=:score, due_date=:due_date,
                               is_published=:pub, updated_at=NOW()
                         WHERE id=:id AND teacher_user_id=:tid";
                if ($code_ecole) $sql .= " AND code_ecole=:ce";
                $st = $pdo->prepare($sql);
                $params = [
                    ':title'=>$title, ':description'=>$description, ':type_eval'=>$type_eval,
                    ':mode'=>$mode_questions, ':score'=>$overall_score, ':due_date'=>$dueParam,
                    ':pub'=>$is_published, ':id'=>$quizId, ':tid'=>$userId
                ];
                if ($code_ecole) $params[':ce'] = $code_ecole;
                $st->execute($params);

                $msgParam = '<div class="alert alert-success mb-3">Paramètres du quiz mis à jour.</div>';
                $quiz = getQuiz($pdo, $quizId, $userId, $code_ecole); // refresh
            }
        }

        // === QUESTIONS ===
        if ($scope === 'questions') {

            // Ajouter QCM
            if ($action === 'add_qcm') {
                $q_text   = trim((string)($_POST['q_text'] ?? ''));
                $points   = (int)($_POST['q_points'] ?? 0);
                $options  = trim((string)($_POST['q_options'] ?? '')); // une par ligne
                $correct  = (int)($_POST['q_correct_index'] ?? 0);     // 1-based

                if ($q_text === '') $errorsQ[] = "Le libellé de la question est obligatoire.";
                if ($points < 0) $errorsQ[] = "Les points ne peuvent pas être négatifs.";
                $opts = array_values(array_filter(array_map('trim', explode("\n", $options)), fn($v)=>$v!==''));
                if (count($opts) < 2) $errorsQ[] = "Au moins deux options sont requises.";
                if ($correct < 1 || $correct > count($opts)) $errorsQ[] = "Index de la bonne réponse invalide.";

                if (!$errorsQ) {
                    $order = maxOrderIndex($pdo, $quizId) + 1;
                    $st = $pdo->prepare("
                        INSERT INTO quiz_questions
                        (quiz_id, `type`, question_text, points, options_json, correct_json, order_index, created_at, updated_at)
                        VALUES (:qid, 'qcm', :qt, :pts, :optj, :corrj, :ord, NOW(), NOW())
                    ");
                    $st->execute([
                        ':qid'=>$quizId,
                        ':qt'=>$q_text,
                        ':pts'=>$points,
                        ':optj'=>json_encode($opts, JSON_UNESCAPED_UNICODE),
                        ':corrj'=>json_encode([$correct], JSON_UNESCAPED_UNICODE),
                        ':ord'=>$order
                    ]);
                    $msgQuest = '<div class="alert alert-success mb-3">Question QCM ajoutée.</div>';
                }
            }

            // Ajouter QR
            if ($action === 'add_qr') {
                $q_text = trim((string)($_POST['qr_text'] ?? ''));
                $points = (int)($_POST['qr_points'] ?? 0);

                if ($q_text === '') $errorsQ[] = "Le libellé de la question est obligatoire.";
                if ($points < 0) $errorsQ[] = "Les points ne peuvent pas être négatifs.";

                if (!$errorsQ) {
                    $order = maxOrderIndex($pdo, $quizId) + 1;
                    $st = $pdo->prepare("
                        INSERT INTO quiz_questions
                        (quiz_id, `type`, question_text, points, options_json, correct_json, order_index, created_at, updated_at)
                        VALUES (:qid, 'qr', :qt, :pts, NULL, NULL, :ord, NOW(), NOW())
                    ");
                    $st->execute([':qid'=>$quizId, ':qt'=>$q_text, ':pts'=>$points, ':ord'=>$order]);
                    $msgQuest = '<div class="alert alert-success mb-3">Question (ouverte) ajoutée.</div>';
                }
            }

            // Mettre à jour une question
            if ($action === 'update_question') {
                $qid      = (int)($_POST['qid'] ?? 0);
                $q_type   = strtolower((string)($_POST['edit_type'] ?? 'qr'));
                $q_text   = trim((string)($_POST['edit_text'] ?? ''));
                $points   = (int)($_POST['edit_points'] ?? 0);
                $opts_raw = (string)($_POST['edit_options'] ?? '');
                $corr_idx = (int)($_POST['edit_correct_index'] ?? 0);

                if ($q_text === '') $errorsQ[] = "Le libellé de la question est obligatoire.";
                if ($points < 0) $errorsQ[] = "Les points ne peuvent pas être négatifs.";

                $optJson = null; $corrJson = null;
                if ($q_type === 'qcm') {
                    $opts = array_values(array_filter(array_map('trim', explode("\n", $opts_raw)), fn($v)=>$v!==''));
                    if (count($opts) < 2) $errorsQ[] = "Au moins deux options sont requises.";
                    if ($corr_idx < 1 || $corr_idx > count($opts)) $errorsQ[] = "Index de la bonne réponse invalide.";
                    $optJson  = json_encode($opts, JSON_UNESCAPED_UNICODE);
                    $corrJson = json_encode([$corr_idx], JSON_UNESCAPED_UNICODE);
                }

                if (!$errorsQ) {
                    $sql = "UPDATE quiz_questions SET question_text=:qt, points=:pts, `type`=:tp, updated_at=NOW()";
                    $params = [':qt'=>$q_text, ':pts'=>$points, ':tp'=>$q_type, ':id'=>$qid, ':quiz'=>$quizId];

                    if ($q_type === 'qcm') {
                        $sql .= ", options_json=:oj, correct_json=:cj";
                        $params[':oj'] = $optJson; $params[':cj'] = $corrJson;
                    } else {
                        $sql .= ", options_json=NULL, correct_json=NULL";
                    }
                    $sql .= " WHERE id=:id AND quiz_id=:quiz";

                    $st = $pdo->prepare($sql);
                    $st->execute($params);
                    $msgQuest = '<div class="alert alert-success mb-3">Question mise à jour.</div>';
                }
            }

            // Supprimer
            if ($action === 'delete_question') {
                $qid = (int)($_POST['qid'] ?? 0);
                $st = $pdo->prepare("DELETE FROM quiz_questions WHERE id=:id AND quiz_id=:quiz");
                $st->execute([':id'=>$qid, ':quiz'=>$quizId]);
                $msgQuest = '<div class="alert alert-success mb-3">Question supprimée.</div>';
            }

            // Réordonner
            if ($action === 'move_question') {
                $qid = (int)($_POST['qid'] ?? 0);
                $dir = (string)($_POST['dir'] ?? 'up');
                $all = loadQuestions($pdo, $quizId);
                $idx = null;
                foreach ($all as $i=>$row) if ((int)$row['id']===$qid) {$idx=$i; break;}
                if ($idx!==null) {
                    if ($dir==='up' && $idx>0) {
                        swapOrder($pdo, $quizId, (int)$all[$idx]['id'], (int)$all[$idx-1]['id']);
                    } elseif ($dir==='down' && $idx < count($all)-1) {
                        swapOrder($pdo, $quizId, (int)$all[$idx]['id'], (int)$all[$idx+1]['id']);
                    }
                    $msgQuest = '<div class="alert alert-success mb-3">Ordre mis à jour.</div>';
                }
            }
        }

    } catch (Throwable $e) {
        if ($scope === 'params') $errorsP[] = 'Erreur: '.$e->getMessage();
        else $errorsQ[] = 'Erreur: '.$e->getMessage();
        if ($DEBUG) {
            $et = ($scope==='params')? '&sect=param' : '&sect=quest';
            echo '<pre>DEBUG '.$et.' '.$e->getFile().':'.$e->getLine().' :: '.e($e->getMessage()).'</pre>';
        }
    }
}

// Prefill (si GET ou si erreurs)
$title          = isset($title) ? $title : (string)($quiz['title'] ?? '');
$description    = isset($description) ? $description : (string)($quiz['description'] ?? '');
$type_eval      = isset($type_eval) ? $type_eval : (string)($quiz['type_eval'] ?? '');
$mode_questions = isset($mode_questions) ? $mode_questions : strtoupper((string)($quiz['mode_questions'] ?? 'QCM'));
$overall_score  = isset($overall_score) ? (int)$overall_score : (int)($quiz['overall_score'] ?? 0);
$due_date       = isset($due_date) ? $due_date : (string)($quiz['due_date'] ?? '');
$is_published   = isset($is_published) ? (int)$is_published : (int)($quiz['is_published'] ?? 0);

// recharger questions pour l'affichage
$questions = loadQuestions($pdo, $quizId);

// util
function shortTxt($t,$n=120){ $t=(string)$t; return mb_strlen($t,'UTF-8')>$n? mb_substr($t,0,$n,'UTF-8').'…' : $t; }

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Modifier le quiz</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <style>
        .card{border:0;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        .muted{color:#6b7280}
        .form-text{font-size:.8rem;color:#6b7280}
        .q-row{border:1px solid #e5e7eb;border-radius:.75rem;padding:.75rem;margin-bottom:.75rem;background:#fff}
        .q-badge{display:inline-block;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;border-radius:999px;padding:.1rem .5rem;font-size:.75rem}
        .btn-icon{min-width:2.25rem}
        textarea.form-control{min-height:110px}
        .anchor{scroll-margin-top:80px}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area d-flex justify-content-between align-items-center">
                <h3>✏️ Modifier le quiz — <?= e($quiz['title']) ?></h3>
                <div>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="list_des_quiz.php?<?= $classId>0 ? 'class_id='.((int)$classId).'&' : '' ?>quiz_id=<?= (int)$quizId ?>">← Retour au quiz</a>
                </div>
            </div>

            <!-- PARAMÈTRES -->
            <a id="params" class="anchor"></a>
            <?= $msgParam ?>
            <?php if ($errorsP): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errorsP as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                        <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                        <input type="hidden" name="scope" value="params">
                        <input type="hidden" name="action" value="save_quiz">
                        <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>

                        <div class="form-group">
                            <label for="title">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?= e($title) ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= e($description) ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="type_eval">Type d’évaluation</label>
                                <input type="text" class="form-control" id="type_eval" name="type_eval" placeholder="Devoir, Test, Examen…" value="<?= e($type_eval) ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="mode_questions">Mode (QCM / QR / MIXTE) <span class="text-danger">*</span></label>
                                <select class="form-control" id="mode_questions" name="mode_questions" required>
                                    <?php foreach (['QCM','QR','MIXTE'] as $m): ?>
                                        <option value="<?= e($m) ?>" <?= (strtoupper($mode_questions)===$m)?'selected':''; ?>><?= e($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">QCM = choix multiple, QR = réponse ouverte, MIXTE = combiné.</small>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="overall_score">Barème total</label>
                                <input type="number" class="form-control" id="overall_score" name="overall_score" min="0" value="<?= (int)$overall_score ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="due_date">Échéance (YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)</label>
                                <input type="text" class="form-control" id="due_date" name="due_date" placeholder="2025-10-30 ou 2025-10-30 23:59:59" value="<?= e($due_date) ?>">
                                <small class="form-text">Laisser vide si aucune échéance.</small>
                            </div>
                            <div class="form-group col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= $is_published ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_published">Publié</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                                <a href="list_des_quiz.php?<?= $classId>0 ? 'class_id='.((int)$classId).'&' : '' ?>quiz_id=<?= (int)$quizId ?>" class="btn btn-outline-secondary">Annuler</a>
                            </div>
                            <a class="btn btn-link" href="#questions">Aller aux questions ↓</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- QUESTIONS -->
            <a id="questions" class="anchor"></a>
            <?= $msgQuest ?>
            <?php if ($errorsQ): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errorsQ as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Questions du quiz</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-success btn-sm" data-toggle="collapse" data-target="#addQCM">➕ Ajouter QCM</button>
                        <button class="btn btn-info btn-sm" data-toggle="collapse" data-target="#addQR">➕ Ajouter Question ouverte</button>
                        <a class="btn btn-link btn-sm ml-auto" href="#params">↑ Retour aux paramètres</a>
                    </div>

                    <!-- Ajouter QCM -->
                    <div id="addQCM" class="collapse mt-3">
                        <div class="q-row">
                            <form method="post">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                <input type="hidden" name="scope" value="questions">
                                <input type="hidden" name="action" value="add_qcm">
                                <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>

                                <div class="form-group">
                                    <label>Énoncé</label>
                                    <textarea class="form-control" name="q_text" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Options (une par ligne)</label>
                                    <textarea class="form-control" name="q_options" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Index de la bonne réponse (1-based)</label>
                                        <input type="number" class="form-control" name="q_correct_index" min="1" value="1">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Points</label>
                                        <input type="number" class="form-control" name="q_points" min="0" value="1">
                                    </div>
                                </div>
                                <button class="btn btn-success btn-sm">Ajouter QCM</button>
                            </form>
                        </div>
                    </div>

                    <!-- Ajouter QR -->
                    <div id="addQR" class="collapse mt-3">
                        <div class="q-row">
                            <form method="post">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                <input type="hidden" name="scope" value="questions">
                                <input type="hidden" name="action" value="add_qr">
                                <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>

                                <div class="form-group">
                                    <label>Énoncé</label>
                                    <textarea class="form-control" name="qr_text" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" class="form-control" name="qr_points" min="0" value="1">
                                </div>
                                <button class="btn btn-info btn-sm">Ajouter Question ouverte</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste & édition -->
            <div class="card">
                <div class="card-body">
                    <?php if (!$questions): ?>
                        <div class="muted">Aucune question pour ce quiz.</div>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                            <?php
                                $opts = [];
                                if (!empty($q['options_json'])) {
                                    $tmp = json_decode((string)$q['options_json'], true);
                                    if (is_array($tmp)) $opts = $tmp;
                                }
                                $corr = null;
                                if (!empty($q['correct_json'])) {
                                    $c = json_decode((string)$q['correct_json'], true);
                                    if (is_array($c) && isset($c[0])) $corr = (int)$c[0];
                                }
                                $isQcm = (strtolower((string)$q['type'])==='qcm');
                            ?>
                            <div class="q-row">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="q-badge"><?= strtoupper((string)$q['type']) ?></span>
                                        <strong class="ml-2">#<?= (int)$q['order_index'] ?> • <?= (int)$q['points'] ?> pt(s)</strong>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <!-- up -->
                                        <form method="post" class="mr-1" title="Monter">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                            <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                            <input type="hidden" name="scope" value="questions">
                                            <input type="hidden" name="action" value="move_question">
                                            <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                                            <input type="hidden" name="dir" value="up">
                                            <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
                                            <button class="btn btn-light btn-sm btn-icon">▲</button>
                                        </form>
                                        <!-- down -->
                                        <form method="post" class="mr-2" title="Descendre">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                            <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                            <input type="hidden" name="scope" value="questions">
                                            <input type="hidden" name="action" value="move_question">
                                            <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                                            <input type="hidden" name="dir" value="down">
                                            <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
                                            <button class="btn btn-light btn-sm btn-icon">▼</button>
                                        </form>
                                        <!-- delete -->
                                        <form method="post" onsubmit="return confirm('Supprimer cette question ?');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                            <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                            <input type="hidden" name="scope" value="questions">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                                            <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
                                            <button class="btn btn-outline-danger btn-sm">Supprimer</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div><strong>Énoncé :</strong> <?= nl2br(e((string)$q['question_text'])) ?></div>
                                    <?php if ($isQcm && $opts): ?>
                                        <div class="mt-2">
                                            <strong>Options :</strong>
                                            <ol class="mb-0">
                                                <?php foreach ($opts as $i=>$opt): ?>
                                                    <li<?= ($corr===($i+1))?' style="font-weight:600;text-decoration:underline"':''; ?>>
                                                        <?= e((string)$opt) ?><?= ($corr===($i+1))?' ✓':''; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Édition inline -->
                                <div class="mt-3">
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">
                                        <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                                        <input type="hidden" name="scope" value="questions">
                                        <input type="hidden" name="action" value="update_question">
                                        <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                                        <?php if ($DEBUG): ?><input type="hidden" name="debug" value="1"><?php endif; ?>

                                        <div class="form-row">
                                            <div class="form-group col-md-2">
                                                <label>Type</label>
                                                <select class="form-control" name="edit_type">
                                                    <option value="qcm" <?= $isQcm?'selected':''; ?>>QCM</option>
                                                    <option value="qr"  <?= !$isQcm?'selected':''; ?>>QR (ouverte)</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Points</label>
                                                <input type="number" class="form-control" name="edit_points" min="0" value="<?= (int)$q['points'] ?>">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Énoncé</label>
                                            <textarea class="form-control" name="edit_text"><?= e((string)$q['question_text']) ?></textarea>
                                        </div>

                                        <?php
                                          $optsTxt = $opts ? implode("\n",$opts) : '';
                                          $corrVal = $corr ? $corr : 1;
                                        ?>
                                        <div class="form-row" <?= $isQcm?'':'style="display:none"' ?> data-qcm-block>
                                            <div class="form-group col-md-8">
                                                <label>Options (une par ligne)</label>
                                                <textarea class="form-control" name="edit_options"><?= e($optsTxt) ?></textarea>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Index bonne réponse (1-based)</label>
                                                <input type="number" class="form-control" name="edit_correct_index" min="1" value="<?= (int)$corrVal ?>">
                                            </div>
                                        </div>

                                        <button class="btn btn-primary btn-sm">💾 Enregistrer la question</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script>
// Toggle QCM block si le type change dans l’édition inline
$(document).on('change','select[name="edit_type"]',function(){
    var $form = $(this).closest('form');
    if ($(this).val().toLowerCase()==='qcm') $form.find('[data-qcm-block]').show();
    else $form.find('[data-qcm-block]').hide();
});
</script>
</body>
</html>
