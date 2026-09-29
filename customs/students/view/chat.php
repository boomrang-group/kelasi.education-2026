<?php
// customs/students/view/chat.php
// Chat élève ↔ professeur (table: chat_messages)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }

if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$code_ecole  = (string)($_SESSION['code_ecole'] ?? '');
$studentId   = (int)($_SESSION['student_id'] ?? 0);
$classId     = (int)($_SESSION['class_id'] ?? 0);
$studentName = (string)($_SESSION['username'] ?? 'Élève');
$email       = (string)($_SESSION['email'] ?? '');

if ($code_ecole === '' || $studentId <= 0) {
    http_response_code(403);
    exit('Contexte élève invalide (code_ecole/student_id manquants)');
}

// Retrouver class_id si absent
if ($classId <= 0) {
    try {
        $st = $pdo->prepare("SELECT class_id, first_name, last_name FROM students WHERE id=:id AND code_ecole=:ce LIMIT 1");
        $st->execute([':id'=>$studentId, ':ce'=>$code_ecole]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $classId = (int)($r['class_id'] ?? 0);
            $_SESSION['class_id'] = $classId;
            $nm = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if ($nm !== '') $studentName = $nm;
        }
    } catch(Throwable $e) {}
}

if ($classId <= 0) {
    http_response_code(403);
    exit('Classe introuvable pour cet élève.');
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

/**
 * Récupère les profs de la classe:
 * 1) via table teacher (souvent votre cas)
 * 2) fallback via table users
 */
$teachers = [];
try {
    // 1) teacher table
    $sql1 = "
        SELECT DISTINCT 
            t.id AS teacher_id,
            TRIM(CONCAT(COALESCE(t.first_name,''),' ',COALESCE(t.last_name,''))) AS nom
        FROM class_subject_teacher cst
        JOIN teacher t ON t.id = cst.teacher_user_id
        WHERE cst.class_id = :cid AND cst.code_ecole = :ce
        ORDER BY nom
    ";
    $st = $pdo->prepare($sql1);
    $st->execute([':cid'=>$classId, ':ce'=>$code_ecole]);
    $teachers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 2) fallback users table
    if (!$teachers) {
        $sql2 = "
            SELECT DISTINCT
                u.id AS teacher_id,
                TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS nom
            FROM class_subject_teacher cst
            JOIN users u ON u.id = cst.teacher_user_id
            WHERE cst.class_id = :cid AND cst.code_ecole = :ce
            ORDER BY nom
        ";
        $st = $pdo->prepare($sql2);
        $st->execute([':cid'=>$classId, ':ce'=>$code_ecole]);
        $teachers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // normalisation label
    foreach ($teachers as &$t) {
        $t['teacher_id'] = (int)($t['teacher_id'] ?? 0);
        $t['nom'] = trim((string)($t['nom'] ?? '')) ?: ('Professeur #'.(int)$t['teacher_id']);
    }
    unset($t);

} catch(Throwable $e) {
    $teachers = [];
}

// ======================= AJAX =======================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // LISTE conversation élève<->prof
    if ($_GET['ajax'] === 'list') {
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            echo json_encode(['status'=>'ok','messages'=>[]], JSON_UNESCAPED_UNICODE); exit;
        }

        try {
            $q = "
                SELECT id, sender_user, sender_name, sender_role, recipient_user, message, created_at
                FROM chat_messages
                WHERE code_ecole = :ce
                  AND scope = 'direct'
                  AND (
                        (sender_user = :sid AND recipient_user = :tid)
                     OR (sender_user = :tid AND recipient_user = :sid)
                  )
                ORDER BY id ASC
                LIMIT 500
            ";
            $st = $pdo->prepare($q);
            $st->execute([
                ':ce'  => $code_ecole,
                ':sid' => $studentId,
                ':tid' => $teacherId
            ]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['status'=>'ok','messages'=>$rows], JSON_UNESCAPED_UNICODE); exit;
        } catch(Throwable $e) {
            echo json_encode(['status'=>'err','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); exit;
        }
    }

    // ENVOI
    if ($_GET['ajax'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
            echo json_encode(['status'=>'err','msg'=>'CSRF'], JSON_UNESCAPED_UNICODE); exit;
        }

        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $body = trim((string)($_POST['message'] ?? ''));

        if ($teacherId <= 0) { echo json_encode(['status'=>'err','msg'=>'Prof invalide'], JSON_UNESCAPED_UNICODE); exit; }
        if ($body === '')     { echo json_encode(['status'=>'err','msg'=>'Message vide'], JSON_UNESCAPED_UNICODE); exit; }

        try {
            $st = $pdo->prepare("
                INSERT INTO chat_messages
                    (code_ecole, scope, sender_user, sender_name, sender_role, recipient_user, message)
                VALUES
                    (:ce, 'direct', :sid, :sname, 'eleve', :rid, :msg)
            ");
            $st->execute([
                ':ce'    => $code_ecole,
                ':sid'   => $studentId,
                ':sname' => $studentName,
                ':rid'   => $teacherId,
                ':msg'   => $body
            ]);

            echo json_encode(['status'=>'ok'], JSON_UNESCAPED_UNICODE); exit;
        } catch(Throwable $e) {
            echo json_encode(['status'=>'err','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); exit;
        }
    }

    echo json_encode(['status'=>'err','msg'=>'Action inconnue'], JSON_UNESCAPED_UNICODE); exit;
}
// ===================== FIN AJAX =======================
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Chat | Espace élève</title>
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
    .card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .04)
    }

    .muted {
        color: #6b7280
    }

    .chat-box {
        height: 62vh;
        overflow: auto;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 12px
    }

    .msg {
        max-width: 75%;
        padding: .6rem .8rem;
        border-radius: 16px;
        margin: .35rem 0;
        white-space: pre-wrap;
        line-height: 1.35
    }

    .msg.me {
        background: #dbeafe;
        margin-left: auto;
        border: 1px solid #c7ddff
    }

    .msg.other {
        background: #ffffff;
        margin-right: auto;
        border: 1px solid #eef2f7
    }

    .meta {
        font-size: .78rem;
        color: #6b7280;
        margin-top: .25rem
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px
    }

    .k-btn {
        border-radius: .85rem
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
                    <h3>💬 Messagerie</h3>
                    <p class="muted mb-0">Conversation privée entre vous et vos professeurs (envoi + réception).</p>
                </div>

                <div class="row">
                    <!-- Choix prof -->
                    <div class="col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-2">Choisir un professeur</h6>

                                <?php if (!$teachers): ?>
                                <div class="alert alert-warning mb-0">
                                    Aucun professeur trouvé pour votre classe.
                                    <br><small class="muted">Vérifiez `class_subject_teacher` (class_id, code_ecole,
                                        teacher_user_id).</small>
                                </div>
                                <?php else: ?>
                                <select id="teacherSelect" class="form-control">
                                    <option value="0">— Sélectionner —</option>
                                    <?php foreach($teachers as $t): ?>
                                    <option value="<?= (int)$t['teacher_id'] ?>"><?= e($t['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="muted d-block mt-2"><?= count($teachers) ?> professeur(s)
                                    trouvé(s).</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Zone chat -->
                    <div class="col-lg-8 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="topbar mb-2">
                                    <div><strong>Conversation</strong> <span class="muted" id="whoTalk"></span></div>
                                    <button class="btn btn-sm btn-outline-primary k-btn"
                                        id="btnRefresh">Rafraîchir</button>
                                </div>

                                <div id="chatBox" class="chat-box">
                                    <div class="muted">Sélectionnez un professeur pour afficher la conversation.</div>
                                </div>

                                <form id="sendForm" class="mt-3" autocomplete="off">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="teacher_id" id="teacher_id" value="0">

                                    <div class="form-group mb-2">
                                        <textarea class="form-control" name="message" id="message" rows="3"
                                            placeholder="Écrire un message..." required></textarea>
                                    </div>

                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary k-btn">
                                            <i class="far fa-paper-plane"></i> Envoyer
                                        </button>
                                    </div>
                                    <div id="sendAlert" class="mt-2"></div>
                                </form>
                            </div>
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

    <script>
    (function() {
        let currentTeacher = 0;

        function escapeHtml(s) {
            return String(s || '').replace(/[&<>"']/g, function(c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [c];
            });
        }

        function scrollBottom() {
            const box = document.getElementById('chatBox');
            if (box) box.scrollTop = box.scrollHeight;
        }

        function renderMessages(list) {
            let html = '';
            if (!list || !list.length) {
                html = '<div class="muted">Aucun message pour le moment.</div>';
                $('#chatBox').html(html);
                return;
            }
            list.forEach(m => {
                const me = (String(m.sender_role || '').toLowerCase() === 'eleve');
                html += `
                <div class="msg ${me?'me':'other'}">
                    ${escapeHtml(m.message || '')}
                    <div class="meta">${escapeHtml(m.sender_name || (me?'Moi':'Prof'))} • ${escapeHtml(m.created_at || '')}</div>
                </div>
            `;
            });
            $('#chatBox').html(html);
            scrollBottom();
        }

        function loadConversation() {
            if (!currentTeacher) return;
            $.getJSON('chat.php', {
                ajax: 'list',
                teacher_id: currentTeacher
            }, function(resp) {
                if (resp && resp.status === 'ok') {
                    renderMessages(resp.messages || []);
                }
            });
        }

        $('#teacherSelect').on('change', function() {
            currentTeacher = parseInt(this.value || '0', 10);
            $('#teacher_id').val(currentTeacher);

            const label = $('#teacherSelect option:selected').text();
            $('#whoTalk').text(currentTeacher ? ('— avec ' + label) : '');

            if (!currentTeacher) {
                $('#chatBox').html(
                    '<div class="muted">Sélectionnez un professeur pour afficher la conversation.</div>'
                    );
                return;
            }
            loadConversation();
        });

        $('#btnRefresh').on('click', function() {
            loadConversation();
        });

        $('#sendForm').on('submit', function(e) {
            e.preventDefault();
            if (!currentTeacher) {
                $('#sendAlert').html(
                    '<div class="alert alert-warning mb-0">Choisissez un professeur.</div>');
                return;
            }

            const fd = new FormData(this);
            $.ajax({
                url: 'chat.php?ajax=send',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 'ok') {
                        $('#sendAlert').html(
                            '<div class="alert alert-success mb-0">Message envoyé.</div>');
                        $('#message').val('');
                        loadConversation();
                    } else {
                        $('#sendAlert').html('<div class="alert alert-danger mb-0">Erreur: ' +
                            escapeHtml(resp.msg || 'inconnue') + '</div>');
                    }
                },
                error: function(x) {
                    $('#sendAlert').html(
                        '<div class="alert alert-danger mb-0">Erreur réseau (' + x.status +
                        ').</div>');
                }
            });
        });

        // auto refresh léger
        setInterval(function() {
            if (currentTeacher) loadConversation();
        }, 8000);
    })();
    </script>
</body>

</html>