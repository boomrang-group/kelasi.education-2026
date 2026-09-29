<?php
// customs/teacher/view/chat.php
// Chat PROF ↔ ÉLÈVE (version alignée sur chat_messages)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

/* ================== CONTEXTE ================== */
$profUserId = (int)($_SESSION['user_id'] ?? 0); // users.id
$code_ecole = $_SESSION['code_ecole'] ?? null;
$profName   = $_SESSION['username'] ?? 'Professeur';

if (!$profUserId || !$code_ecole) {
    exit('Contexte professeur invalide');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ================== CLASSES DU PROF ================== */
$st = $pdo->prepare("
    SELECT DISTINCT class_id
    FROM class_subject_teacher
    WHERE teacher_user_id = :uid
      AND code_ecole = :ce
");
$st->execute([':uid'=>$profUserId, ':ce'=>$code_ecole]);
$classIds = $st->fetchAll(PDO::FETCH_COLUMN);

$students = [];
if ($classIds) {
    $in = implode(',', array_fill(0, count($classIds), '?'));
    $params = $classIds;
    $params[] = $code_ecole;

    $st = $pdo->prepare("
        SELECT id, first_name, last_name, username, class_id
        FROM students
        WHERE class_id IN ($in)
          AND code_ecole = ?
        ORDER BY last_name, first_name
    ");
    $st->execute($params);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================== ÉLÈVE SÉLECTIONNÉ ================== */
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

/* ================== AJAX ================== */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_GET['ajax'] === 'list' && $studentId > 0) {
        $st = $pdo->prepare("
            SELECT id, sender_user, sender_role, sender_name, message, created_at
            FROM chat_messages
            WHERE code_ecole = :ce
              AND scope = 'direct'
              AND (
                   (sender_user = :prof AND recipient_user = :eleve)
                OR (sender_user = :eleve AND recipient_user = :prof)
              )
            ORDER BY id ASC
        ");
        $st->execute([
            ':ce'=>$code_ecole,
            ':prof'=>$profUserId,
            ':eleve'=>$studentId
        ]);

        echo json_encode([
            'ok'=>true,
            'messages'=>$st->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit;
    }

    if ($_GET['ajax'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $msg = trim($_POST['message'] ?? '');
        if ($msg === '') {
            echo json_encode(['ok'=>false,'msg'=>'Message vide']); exit;
        }

        $st = $pdo->prepare("
            INSERT INTO chat_messages
            (code_ecole, scope, sender_user, sender_name, sender_role, recipient_user, message)
            VALUES
            (:ce, 'direct', :sid, :sname, 'prof', :rid, :msg)
        ");
        $st->execute([
            ':ce'=>$code_ecole,
            ':sid'=>$profUserId,
            ':sname'=>$profName,
            ':rid'=>$studentId,
            ':msg'=>$msg
        ]);

        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Chat Professeur</title>
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/jquery-3.3.1.min.js"></script>
</head>

<body>
<div id="wrapper" class="wrapper bg-ash">

<?php include '../layout/navbar.php'; ?>

<div class="dashboard-page-one">
<?php include '../layout/sidebar.php'; ?>

<div class="dashboard-content-one">

<div class="breadcrumbs-area">
    <h3>💬 Chat Professeur</h3>
    <p class="muted">Discussion directe avec vos élèves</p>
</div>

<div class="row">

<!-- LISTE ÉLÈVES -->
<div class="col-lg-4">
    <div class="card">
        <div class="card-body">
            <select class="form-control" onchange="location='chat.php?student_id='+this.value">
                <option value="0">— Choisir un élève —</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $studentId===$s['id']?'selected':'' ?>>
                        <?= h(($s['last_name']??'').' '.($s['first_name']??'')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- CHAT -->
<div class="col-lg-8">
    <div class="card">
        <div class="card-body d-flex flex-column" style="height:70vh">

            <div id="chatBox" style="flex:1;overflow:auto;background:#f8fafc;padding:10px;border-radius:10px"></div>

            <?php if ($studentId>0): ?>
            <form id="sendForm" class="mt-2">
                <textarea name="message" class="form-control" rows="2" placeholder="Votre message..." required></textarea>
                <button class="btn btn-primary btn-sm mt-2">Envoyer</button>
            </form>
            <?php else: ?>
            <div class="text-muted">Sélectionnez un élève pour discuter.</div>
            <?php endif; ?>

        </div>
    </div>
</div>

</div>

<?php include '../layout/footer.php'; ?>

</div>
</div>
</div>

<script>
function render(msgs){
    let html='';
    msgs.forEach(m=>{
        let me = m.sender_role==='prof';
        html += `<div style="margin-bottom:6px;text-align:${me?'right':'left'}">
            <div style="display:inline-block;padding:8px 12px;border-radius:12px;
            background:${me?'#dbeafe':'#fff'}">
                ${$('<div>').text(m.message).html()}
            </div>
        </div>`;
    });
    $('#chatBox').html(html).scrollTop(999999);
}

function load(){
    <?php if ($studentId>0): ?>
    $.getJSON('chat.php?ajax=list&student_id=<?= $studentId ?>',res=>{
        if(res.ok) render(res.messages);
    });
    <?php endif; ?>
}

$('#sendForm').on('submit',function(e){
    e.preventDefault();
    $.post('chat.php?ajax=send&student_id=<?= $studentId ?>',
        $(this).serialize(),
        ()=>{ this.reset(); load(); },
        'json'
    );
});

load();
setInterval(load,10000);
</script>

</body>
</html>
