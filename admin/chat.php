<?php
// admin/chat.php — Chat Admin (Public, Professeur, Élèves) + Messages privés
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- DB connect ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId    = (int)($_SESSION['user_id'] ?? 0);
$username  = (string)($_SESSION['username'] ?? '');
$role      = strtolower((string)($_SESSION['role'] ?? ''));
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');

if ($userId <= 0 || !$codeEcole) {
    if ($userId > 0 && !$codeEcole) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$userId]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        $_SESSION['code_ecole'] = $codeEcole;
    }
}
if ($userId <= 0 || !$codeEcole) { http_response_code(403); exit('Accès refusé.'); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$senderDisplay = $username ?: ('User#'.$userId);

// Groupes (sans administrateur)
$GROUPS = [
    'public'     => 'Public',
    'professeur' => 'Professeur',
    'eleves'     => 'Élèves',
    'direct'     => 'Privé',
];

// Liste destinataires privés : profs + élèves de l’école
$recipients = [];
try {
    $st = $pdo->prepare("
        SELECT id, username, role
        FROM users
        WHERE code_ecole = :e
          AND LOWER(role) IN ('professeur','enseignant','eleve','élève','eleves','élèves')
        ORDER BY LOWER(role), username
    ");
    $st->execute([':e'=>$codeEcole]);
    $recipients = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch(Throwable $e) {
    $recipients = [];
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>Chat | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
        .chat-wrap{display:grid;grid-template-columns:320px 1fr;gap:1rem}
        .chat-sidebar{border:1px solid #e5e7eb;border-radius:.5rem;background:#fff}
        .chat-main{border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;display:flex;flex-direction:column;min-height:520px}
        .chat-header{padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
        .chat-feed{flex:1;overflow:auto;padding:1rem;background:#f9fafb}
        .msg{max-width:80%;margin-bottom:.75rem;padding:.5rem .75rem;border-radius:.75rem;background:#fff;border:1px solid #e5e7eb}
        .mine{margin-left:auto;background:#eff6ff;border-color:#dbeafe}
        .meta{font-size:.8rem;color:#6b7280;margin-bottom:.15rem}
        .content{white-space:pre-wrap}
        .chat-input{border-top:1px solid #e5e7eb;padding:.75rem}
        .badge-scope{font-size:.75rem;border:1px solid #e5e7eb;border-radius:999px;padding:.15rem .5rem;background:#fafafa}
        .sticky-actions{display:flex;gap:.5rem;align-items:center;margin-left:auto}
        .list-group-chat .list-group-item{cursor:pointer}
        .list-group-chat .active{background:#eff6ff;border-color:#dbeafe}
        .muted{color:#6b7280}
        @media (max-width: 992px){ .chat-wrap{grid-template-columns:1fr} }
    </style>
</head>
<body>
<div id="preloader" class="d-none"></div>
<div id="wrapper" class="wrapper bg-ash">
    <?php require_once('layout/navbar.php'); ?>
    <div class="dashboard-page-one">
        <?php require_once('layout/sidebar.php'); ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                <div>
                    <h3>Chat (Admin)</h3>
                    <p class="muted mb-0">Connecté en tant que <strong><?= h($senderDisplay) ?></strong> · École <span class="badge-scope"><?= h($codeEcole ?: '—') ?></span></p>
                </div>
            </div>

            <div class="chat-wrap">
                <div class="chat-sidebar">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-2">Destinataires</h5>
                        <p class="muted mb-0">Choisissez un groupe ou « Privé » pour un utilisateur précis.</p>
                    </div>
                    <ul class="list-group list-group-flush list-group-chat" id="chatScopes">
                        <?php foreach ($GROUPS as $key=>$label): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between" data-scope="<?= h($key) ?>">
                            <span>
                                <?php if ($key==='public'): ?><i class="fas fa-bullhorn mr-2"></i><?php endif; ?>
                                <?php if ($key==='professeur'): ?><i class="fas fa-chalkboard-teacher mr-2"></i><?php endif; ?>
                                <?php if ($key==='eleves'): ?><i class="fas fa-user-graduate mr-2"></i><?php endif; ?>
                                <?php if ($key==='direct'): ?><i class="fas fa-user mr-2"></i><?php endif; ?>
                                <?= h($label) ?>
                            </span>
                            <span class="badge badge-light" id="count-<?= h($key) ?>">—</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="chat-main">
                    <div class="chat-header">
                        <div>
                            <div class="h5 mb-0" id="scopeLabel">Public</div>
                            <div class="muted" id="scopeHint">Messages visibles par tout le monde de l’école.</div>
                        </div>

                        <div id="privateSelectWrap" class="d-none">
                            <label class="mb-0 mr-2">Destinataire privé :</label>
                            <select id="privateUser" class="form-control">
                                <option value="">— Sélectionnez un utilisateur —</option>
                                <?php foreach ($recipients as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>">
                                        <?= h(($u['username'] ?: ('User#'.$u['id'])) . ' — ' . (strtolower($u['role']) ?: '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sticky-actions">
                            <button id="btn-refresh" class="btn btn-sm btn-outline-primary" title="Rafraîchir (Ctrl+R)">
                                <i class="fas fa-sync-alt"></i> Rafraîchir
                            </button>
                        </div>
                    </div>

                    <div id="chatFeed" class="chat-feed">
                        <div class="muted">Chargement du fil…</div>
                    </div>

                    <div class="chat-input">
                        <form id="chatForm" autocomplete="off">
                            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="scope" id="scopeInput" value="public">
                            <input type="hidden" name="recipient_user" id="recipientInput" value="">
                            <div class="form-row">
                                <div class="col-12 col-md-9 mb-2 mb-md-0">
                                    <textarea class="form-control" name="message" id="messageInput" rows="2" placeholder="Votre message… (Entrée = ligne, Ctrl+Entrée = envoyer)"></textarea>
                                </div>
                                <div class="col-12 col-md-3 d-flex align-items-start">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-paper-plane"></i> Envoyer
                                    </button>
                                </div>
                            </div>
                            <small class="muted d-block mt-2">
                                Raccourcis : <kbd>Ctrl</kbd>+<kbd>Entrée</kbd> pour envoyer · <kbd>Ctrl</kbd>+<kbd>R</kbd> pour rafraîchir
                            </small>
                        </form>
                    </div>
                </div>
            </div>

            <?php require_once('layout/footer.php'); ?>
        </div>
    </div>
</div>

<script src="../js/jquery-3.3.1.min.js"></script>
<script src="../js/plugins.js"></script>
<script src="../js/popper.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/main.js"></script>
<script>
(function(){
    const $scopes = $('#chatScopes .list-group-item');
    const $feed   = $('#chatFeed');
    const $form   = $('#chatForm');
    const $msg    = $('#messageInput');
    const $scopeI = $('#scopeInput');
    const $scopeLabel = $('#scopeLabel');
    const $scopeHint  = $('#scopeHint');

    const $privateWrap = $('#privateSelectWrap');
    const $privateUser = $('#privateUser');
    const $recipientI  = $('#recipientInput');

    const HINTS = {
        public: 'Messages visibles par tout le monde de l’école.',
        professeur: 'Messages visibles uniquement par les professeurs.',
        eleves: 'Messages visibles uniquement par les élèves.',
        direct: 'Messages privés avec un utilisateur précis.'
    };

    let currentScope = 'public';
    let currentDirectUser = '';
    let lastIdKey = () => currentScope + (currentScope==='direct' ? ('#'+(currentDirectUser||'0')) : '');
    let lastIdByKey = {};
    let pollTimer = null;

    function setActiveScope(scope){
        currentScope = scope;
        $scopes.removeClass('active');
        $scopes.filter('[data-scope="'+scope+'"]').addClass('active');
        $scopeI.val(scope);
        $scopeLabel.text($scopes.filter('[data-scope="'+scope+'"]').text().trim());
        $scopeHint.text(HINTS[scope] || '');

        if (scope === 'direct'){
            $privateWrap.removeClass('d-none');
            if (!$privateUser.val()){
                $feed.html('<div class="muted">Sélectionnez un destinataire privé pour afficher la conversation.</div>');
                return;
            }
        } else {
            $privateWrap.addClass('d-none');
            $recipientI.val('');
            currentDirectUser = '';
        }
        fetchMessages(true);
    }

    function renderMessages(items, append=false){
        if (!append) $feed.empty();
        if (!items || items.length === 0){
            if (!append) $feed.html('<div class="muted">Aucun message.</div>');
            return;
        }
        for (const it of items){
            const mine = it.is_mine ? ' mine' : '';
            const toBadge = (it.scope==='direct' && it.other_party_name) ? ` · <span class="badge badge-light">Privé avec ${escapeHtml(it.other_party_name)}</span>` : '';
            const html = `
                <div class="msg${mine}">
                    <div class="meta">
                        <strong>${escapeHtml(it.sender_name || '—')}</strong>
                        <span class="badge badge-light">${escapeHtml(it.sender_role || '')}</span>
                        ${toBadge}
                        · <span title="${escapeHtml(it.created_at)}">${escapeHtml(it.created_at_human || it.created_at)}</span>
                    </div>
                    <div class="content">${nl2br(escapeHtml(it.message || ''))}</div>
                </div>`;
            $feed.append(html);
            const k = lastIdKey();
            lastIdByKey[k] = Math.max(lastIdByKey[k]||0, parseInt(it.id,10)||0);
        }
        $feed.scrollTop($feed[0].scrollHeight);
    }

    function updateCounts(counts){
        if (!counts) return;
        for (const k of ['public','professeur','eleves','direct']){
            $('#count-'+k).text(counts[k] ?? '—');
        }
    }

    function ajaxErrorAlert(xhr, where){
        const txt = (xhr && xhr.responseText) ? xhr.responseText : '';
        console.error('AJAX ERROR @'+where, xhr);
        alert('Erreur de requête ('+(xhr ? xhr.status : '?')+').\n' + (txt ? ('Détails:\n'+txt) : ''));
    }

    function fetchMessages(reset=false){
        const k = lastIdKey();
        const sinceId = reset ? 0 : (lastIdByKey[k]||0);
        const payload = { scope: currentScope, since_id: sinceId };
        if (currentScope==='direct'){
            const rid = $privateUser.val();
            if (!rid){ return; }
            payload.recipient_user = rid;
        }
        $.ajax({
            url: 'service/chat_fetch.php',
            method: 'POST',
            dataType: 'json',
            data: payload,
            success: function(resp){
                if (resp && resp.status === 'ok'){
                    if (reset) renderMessages(resp.items||[], false);
                    else renderMessages(resp.items||[], true);
                    updateCounts(resp.counts||{});
                } else {
                    if (reset) $feed.html('<div class="text-danger">Impossible de charger le fil.</div>');
                    console.warn('Bad JSON', resp);
                }
            },
            error: function(xhr){ if (reset) $feed.html('<div class="text-danger">Erreur de chargement.</div>'); ajaxErrorAlert(xhr, 'fetch'); }
        });
    }

    function startPolling(){
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function(){ fetchMessages(false); }, 5000);
    }

    function escapeHtml(s){ return $('<div/>').text(s||'').html(); }
    function nl2br(s){ return (s||'').replace(/\n/g, '<br>'); }

    $scopes.on('click', function(){ setActiveScope($(this).data('scope')); });

    $privateUser.on('change', function(){
        const rid = $(this).val();
        currentDirectUser = rid || '';
        $('#recipientInput').val(currentDirectUser);
        if (!rid){
            $feed.html('<div class="muted">Sélectionnez un destinataire privé pour afficher la conversation.</div>');
            return;
        }
        lastIdByKey[lastIdKey()] = 0;
        fetchMessages(true);
    });

    $('#btn-refresh').on('click', function(){ fetchMessages(true); });

    $(document).on('keydown', function(e){
        if (e.ctrlKey && e.key === 'Enter'){ $form.submit(); }
        if (e.ctrlKey && (e.key.toLowerCase() === 'r')){
            e.preventDefault();
            fetchMessages(true);
        }
    });

    $form.on('submit', function(e){
        e.preventDefault();
        const txt = $msg.val().trim();
        if (!txt) { $msg.focus(); return; }

        if (currentScope==='direct' && !$privateUser.val()){
            alert('Choisissez un destinataire privé.');
            $privateUser.focus();
            return;
        }

        $.ajax({
            url: 'service/chat_send.php',
            method: 'POST',
            dataType: 'json',
            data: $(this).serialize(),
            success: function(resp){
                if (resp && resp.status === 'ok'){
                    $msg.val('');
                    fetchMessages(true);
                } else {
                    alert(resp && resp.message ? resp.message : 'Envoi impossible.');
                    console.warn('SEND bad JSON', resp);
                }
            },
            error: function(xhr){ ajaxErrorAlert(xhr, 'send'); }
        });
    });

    setActiveScope('public');
    startPolling();
})();
</script>
</body>
</html>
