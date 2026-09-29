<?php
/**
 * chat.php — Chat admin/prof/eleve (AJAX) dans un seul fichier
 * Dépend de tables: messages, users, class_subject_teacher, students
 * Nécessite en session: user_id, role, code_ecole, username
 */
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header_remove('X-Powered-By');

/* 1) Connexion PDO (on attend $pdo depuis database/db_connect.php) */
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
  if (file_exists($p)) { require_once $p; break; }
}
if (!($pdo instanceof PDO)) { http_response_code(500); exit("Erreur serveur (DB)."); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* 2) Auth/session minimale */
$userId    = (int)($_SESSION['user_id'] ?? 0);
$role      = strtolower((string)($_SESSION['role'] ?? ''));
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
$username  = (string)($_SESSION['username'] ?? '');

if (!$userId || !$role || !$codeEcole) {
  http_response_code(401); exit("Non autorisé (session manquante).");
}

/* 3) Helpers */
function jresp($data, int $code=200){ http_response_code($code); header('Content-Type: application/json'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function cleanStr(string $s, int $max=5000): string { $s=trim($s); if(mb_strlen($s)>$max)$s=mb_substr($s,0,$max); return $s; }

/** Identité expéditeur selon rôle (retourne ['sender_type','sender_id']) */
function resolveSelfIdentity(PDO $pdo, string $role, int $userId, string $codeEcole, string $username): array {
  if (in_array($role,['admin','administrateur','promoteur'],true)) {
    return ['sender_type'=>'admin','sender_id'=>$codeEcole];
  }
  if ($role==='prof') {
    $st=$pdo->prepare("SELECT id FROM class_subject_teacher WHERE teacher_user_id=:u AND code_ecole=:ec LIMIT 1");
    $st->execute([':u'=>$userId,':ec'=>$codeEcole]);
    $r=$st->fetch();
    if(!$r) throw new RuntimeException("Prof introuvable.");
    return ['sender_type'=>'prof','sender_id'=>(string)$r['id']];
  }
  if ($role==='eleve') {
    // 1) username direct
    $st=$pdo->prepare("SELECT id FROM students WHERE username=:un AND code_ecole=:ec LIMIT 1");
    $st->execute([':un'=>$username,':ec'=>$codeEcole]);
    if($s=$st->fetch()) return ['sender_type'=>'eleve','sender_id'=>(string)$s['id']];
    // 2) via email users -> students
    $st=$pdo->prepare("SELECT email FROM users WHERE id=:u AND code_ecole=:ec LIMIT 1");
    $st->execute([':u'=>$userId,':ec'=>$codeEcole]);
    $u=$st->fetch();
    if($u && !empty($u['email'])){
      $st2=$pdo->prepare("SELECT id FROM students WHERE email=:em AND code_ecole=:ec LIMIT 1");
      $st2->execute([':em'=>$u['email'],':ec'=>$codeEcole]);
      if($s2=$st2->fetch()) return ['sender_type'=>'eleve','sender_id'=>(string)$s2['id']];
    }
    throw new RuntimeException("Élève introuvable.");
  }
  throw new RuntimeException("Rôle non supporté.");
}

/** Libellé lisible de l’expéditeur */
function labelFromSender(PDO $pdo, array $msg): string {
  switch($msg['sender_type']){
    case 'admin': return 'Administration';
    case 'prof':
      $st=$pdo->prepare("SELECT u.first_name,u.last_name FROM class_subject_teacher c JOIN users u ON u.id=c.teacher_user_id WHERE c.id=:id LIMIT 1");
      $st->execute([':id'=>$msg['sender_id']]);
      if($r=$st->fetch()){ $n=trim(($r['first_name']??'').' '.($r['last_name']??'')); return $n?:'Professeur'; }
      return 'Professeur';
    case 'eleve':
      $st=$pdo->prepare("SELECT first_name,last_name FROM students WHERE id=:id LIMIT 1");
      $st->execute([':id'=>$msg['sender_id']]);
      if($r=$st->fetch()){ $n=trim(($r['first_name']??'').' '.($r['last_name']??'')); return $n?:'Élève'; }
      return 'Élève';
  }
  return 'Inconnu';
}

/**
 * WHERE + params + expectedKeys (évite HY093)
 * $target = ['type'=>'tous'|'admin'|'prof'|'eleve', 'id'=>string|null]
 * $me = ['sender_type','sender_id']
 */
function conversationWhere(string $codeEcole, array $target, array $me): array {
  if ($target['type']==='tous'){
    $where="m.code_ecole=:ec AND m.receiver_type='tous' AND m.is_public=1";
    return [$where, [':ec'=>$codeEcole], [':ec']];
  }
  $where="m.code_ecole=:ec AND (
    (m.sender_type=:me_t AND m.sender_id=:me_i AND m.receiver_type=:to_t AND m.receiver_id=:to_i)
    OR
    (m.sender_type=:to_t AND m.sender_id=:to_i AND m.receiver_type=:me_t AND m.receiver_id=:me_i)
  )";
  $params=[
    ':ec'=>$codeEcole,
    ':me_t'=>$me['sender_type'],
    ':me_i'=>(string)$me['sender_id'],
    ':to_t'=>$target['type'],
    ':to_i'=>(string)$target['id'],
  ];
  $expected=[':ec',':me_t',':me_i',':to_t',':to_i'];
  return [$where,$params,$expected];
}

/* 4) API */
if (isset($_GET['api'])) {
  $action=$_GET['api'];

  try { $me = resolveSelfIdentity($pdo,$role,$userId,$codeEcole,$username); }
  catch(Throwable $e){ jresp(['error'=>true,'reason'=>'identity','msg'=>$e->getMessage()],400); }

  if ($action==='contacts') {
    try {
      // Profs
      $st=$pdo->prepare("SELECT c.id AS cst_id,u.first_name,u.last_name
                         FROM class_subject_teacher c JOIN users u ON u.id=c.teacher_user_id
                         WHERE c.code_ecole=:ec ORDER BY u.first_name,u.last_name");
      $st->execute([':ec'=>$codeEcole]);
      $profs=[];
      while($r=$st->fetch()){ $profs[]=['type'=>'prof','id'=>(string)$r['cst_id'],'name'=>trim(($r['first_name']??'').' '.($r['last_name']??''))?:'Professeur']; }

      // Élèves
      $st=$pdo->prepare("SELECT id,first_name,last_name FROM students WHERE code_ecole=:ec ORDER BY first_name,last_name LIMIT 500");
      $st->execute([':ec'=>$codeEcole]);
      $eleves=[];
      while($r=$st->fetch()){ $eleves[]=['type'=>'eleve','id'=>(string)$r['id'],'name'=>trim(($r['first_name']??'').' '.($r['last_name']??''))?:'Élève']; }

      jresp(['error'=>false,'contacts'=>[
        'public'=>[['type'=>'tous','id'=>null,'name'=>'Fil public de l’école']],
        'admin' =>[['type'=>'admin','id'=>$codeEcole,'name'=>'Administration']],
        'profs' =>$profs,
        'eleves'=>$eleves
      ]]);
    } catch(Throwable $e){ jresp(['error'=>true,'reason'=>'db','msg'=>$e->getMessage()],500); }
  }

  if ($action==='fetch') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $targetType=(string)($input['target_type']??'');
    $targetId   = $input['target_id'] ?? null;
    if(!in_array($targetType,['tous','admin','prof','eleve'],true)){ jresp(['error'=>true,'reason'=>'param','msg'=>'target_type invalide'],400); }
    if($targetType!=='tous' && !$targetId){ jresp(['error'=>true,'reason'=>'param','msg'=>'target_id manquant'],400); }
    $target=['type'=>$targetType,'id'=>$targetType==='tous'?null:(string)$targetId];

    try{
      [$where,$params,$expected] = conversationWhere($codeEcole,$target,$me);
      $bind=[]; foreach($expected as $k){ $bind[$k]=$params[$k]; }
      $sql="SELECT m.id,m.sender_type,m.sender_id,m.receiver_type,m.receiver_id,m.message,m.is_public,m.created_at
            FROM messages m WHERE $where ORDER BY m.created_at ASC, m.id ASC LIMIT 500";
      $st=$pdo->prepare($sql); $st->execute($bind);
      $rows=$st->fetchAll();
      foreach($rows as &$r){
        $r['sender_label']=labelFromSender($pdo,$r);
        $r['mine']=($r['sender_type']===$me['sender_type'] && (string)$r['sender_id']===(string)$me['sender_id']);
        $r['created_at_iso']=(new DateTime($r['created_at']))->format(DateTime::ATOM);
      } unset($r);
      jresp(['error'=>false,'messages'=>$rows]);
    }catch(Throwable $e){ jresp(['error'=>true,'reason'=>'db','msg'=>$e->getMessage()],500); }
  }

  if ($action==='send') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $targetType=(string)($input['target_type']??'');
    $targetId   = $input['target_id'] ?? null;
    $msg        = cleanStr((string)($input['message']??''));
    if(!in_array($targetType,['tous','admin','prof','eleve'],true)){ jresp(['error'=>true,'reason'=>'param','msg'=>'target_type invalide'],400); }
    if($targetType!=='tous' && !$targetId){ jresp(['error'=>true,'reason'=>'param','msg'=>'target_id manquant'],400); }
    if($msg===''){ jresp(['error'=>true,'reason'=>'param','msg'=>'message vide'],400); }

    $receiverType=$targetType; $receiverId=null; $isPublic=0;
    if($targetType==='tous'){ $receiverId=null; $isPublic=1; }
    elseif($targetType==='admin'){ $receiverId=$codeEcole; }
    else{ $receiverId=(string)$targetId; }

    try{
      $st=$pdo->prepare("INSERT INTO messages (code_ecole,sender_type,sender_id,receiver_type,receiver_id,message,is_public)
                         VALUES (:ec,:st,:sid,:rt,:rid,:msg,:pub)");
      $st->bindValue(':ec',$codeEcole,PDO::PARAM_STR);
      $st->bindValue(':st',$me['sender_type'],PDO::PARAM_STR);
      $st->bindValue(':sid',(string)$me['sender_id'],PDO::PARAM_STR);
      $st->bindValue(':rt',$receiverType,PDO::PARAM_STR);
      if($receiverId===null){ $st->bindValue(':rid',null,PDO::PARAM_NULL); }
      else{ $st->bindValue(':rid',(string)$receiverId,PDO::PARAM_STR); }
      $st->bindValue(':msg',$msg,PDO::PARAM_STR);
      $st->bindValue(':pub',(int)$isPublic,PDO::PARAM_INT);
      $st->execute();
      jresp(['error'=>false,'ok'=>true,'id'=>$pdo->lastInsertId()]);
    }catch(Throwable $e){ jresp(['error'=>true,'reason'=>'db','msg'=>$e->getMessage()],500); }
  }

  jresp(['error'=>true,'reason'=>'route','msg'=>'API inconnue'],404);
}

/* 5) Interface HTML */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Messagerie scolaire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{ --muted:#94a3b8; }
body{ background:linear-gradient(135deg,#0b1220,#0f172a); color:#e5e7eb; min-height:100dvh; }
.app{ display:grid; grid-template-columns:320px 1fr; gap:16px; padding:16px; height:100dvh; }
.sidebar,.main{ background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.09); border-radius:16px; }
.sidebar{ display:flex; flex-direction:column; overflow:hidden; }
.header{ padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); }
.list{ padding:8px 8px 16px; overflow:auto; }
.section-title{ font-size:.75rem; color:#9ca3af; padding:8px 10px; text-transform:uppercase; letter-spacing:.08em;}
.contact{ display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:12px; cursor:pointer; }
.contact:hover{ background:rgba(255,255,255,.06); }
.contact.active{ outline:2px solid rgba(255,255,255,.18); }
.avatar{ width:34px; height:34px; border-radius:50%; background:#1f2937; display:grid; place-items:center; font-weight:700; }
.main{ display:grid; grid-template-rows:auto 1fr auto; }
.chat-head{ padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; justify-content:space-between; align-items:center; }
.messages{ padding:16px; overflow:auto; display:flex; flex-direction:column; gap:10px; }
.msg{ max-width:70%; padding:10px 12px; border-radius:14px; background:#0b1220; border:1px solid rgba(255,255,255,.08); }
.msg.me{ margin-left:auto; background:#111827; border-color:#1f2937; }
.msg .meta{ font-size:.75rem; color:#9ca3af; margin-bottom:4px; }
.composer{ padding:12px; border-top:1px solid rgba(255,255,255,.08); display:flex; gap:8px; }
.form-control,.form-select{ background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.15); color:#e5e7eb; }
.form-control:focus,.form-select:focus{ border-color:#60a5fa; box-shadow:none; }
@media (max-width:960px){ .app{ grid-template-columns:1fr; } .sidebar{ height:45dvh; } .main{ height:calc(100dvh - 16px*2 - 45dvh - 16px); } }
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="header d-flex justify-content-between">
      <div>
        <div class="fw-bold">Messagerie</div>
        <div class="text-muted" style="font-size:.9rem">École: <span class="badge text-bg-dark"><?=htmlspecialchars($codeEcole)?></span></div>
      </div>
      <span class="badge text-bg-secondary"><?=htmlspecialchars($role)?></span>
    </div>
    <div class="p-2 border-bottom border-secondary-subtle">
      <input id="search" class="form-control form-control-sm" placeholder="Rechercher…">
    </div>
    <div class="list" id="contacts"></div>
  </aside>

  <main class="main">
    <div class="chat-head">
      <div>
        <div id="chatTitle" class="fw-bold">Sélectionnez une conversation</div>
        <div id="chatSubtitle" class="text-muted" style="font-size:.9rem"></div>
      </div>
    </div>
    <div id="messages" class="messages">
      <div class="text-center text-muted mt-5">Aucune conversation ouverte.</div>
    </div>
    <div class="composer">
      <input id="msgInput" class="form-control" placeholder="Écrire un message…" disabled>
      <button id="sendBtn" class="btn btn-primary" disabled>Envoyer</button>
    </div>
  </main>
</div>

<script>
const $ = s => document.querySelector(s);
const api = (a,p={},m='POST') => fetch(`?api=${encodeURIComponent(a)}`,{method:m,headers:{'Content-Type':'application/json'},body:m==='POST'?JSON.stringify(p):undefined}).then(r=>r.json());
let CURRENT=null, POLL=null, ALL=[];

function avatar(n){ const t=(n||'').trim().split(/\s+/); return ((t[0]?.[0]||'?')+(t[1]?.[0]||'')).toUpperCase(); }
function contactEl(c){
  const el=document.createElement('div');
  el.className='contact';
  el.innerHTML=`<div class="avatar">${avatar(c.name)}</div><div><div class="fw-semibold">${c.name}</div><div class="text-muted" style="font-size:.8rem">${c.type==='tous'?'Public':'Privé'}</div></div>`;
  el.onclick=()=>{ document.querySelectorAll('.contact').forEach(x=>x.classList.remove('active')); el.classList.add('active'); openConv(c); };
  return el;
}
function addSection(title, arr, root){
  if(!arr || !arr.length) return;
  const h=document.createElement('div'); h.className='section-title'; h.textContent=title; root.appendChild(h);
  arr.forEach(c=> root.appendChild(contactEl(c)));
}
function renderContacts(data){
  const root=$('#contacts'); root.innerHTML='';
  addSection('Fil public',[...data.public],root);
  addSection('Administration',[...data.admin],root);
  addSection('Professeurs',[...data.profs],root);
  addSection('Élèves',[...data.eleves],root);
  ALL=[...data.public,...data.admin,...data.profs,...data.eleves];
}
function renderMessages(rows){
  const box=$('#messages'); box.innerHTML='';
  if(!rows || !rows.length){ box.innerHTML='<div class="text-center text-muted mt-5">Aucun message.</div>'; return; }
  rows.forEach(r=>{
    const d=document.createElement('div');
    d.className='msg'+(r.mine?' me':'');
    const when=new Date(r.created_at_iso);
    d.innerHTML=`<div class="meta">${r.sender_label} • ${when.toLocaleString()}</div><div>${(r.message||'').replace(/</g,'&lt;')}</div>`;
    box.appendChild(d);
  });
  box.scrollTop=box.scrollHeight;
}
async function openConv(c){
  CURRENT=c;
  $('#chatTitle').textContent=c.name;
  $('#chatSubtitle').textContent=c.type==='tous'?'Visible à toute l’école':'Conversation privée';
  $('#msgInput').disabled=false; $('#sendBtn').disabled=false;
  const r=await api('fetch',{target_type:c.type,target_id:c.id??null});
  if(!r.error) renderMessages(r.messages);
  if(POLL) clearInterval(POLL);
  POLL=setInterval(async ()=>{
    if(!CURRENT) return;
    const rr=await api('fetch',{target_type:CURRENT.type,target_id:CURRENT.id??null});
    if(!rr.error) renderMessages(rr.messages);
  },5000);
}
$('#sendBtn').onclick=async ()=>{
  const v=$('#msgInput').value.trim(); if(!v||!CURRENT) return;
  const r=await api('send',{target_type:CURRENT.type,target_id:CURRENT.id??null,message:v});
  if(r.error){ alert(r.msg||'Erreur envoi'); return; }
  $('#msgInput').value='';
  const rr=await api('fetch',{target_type:CURRENT.type,target_id:CURRENT.id??null});
  if(!rr.error) renderMessages(rr.messages);
};
$('#msgInput').addEventListener('keydown',e=>{ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); $('#sendBtn').click(); }});
$('#search').oninput=()=>{
  const q=$('#search').value.toLowerCase(), root=$('#contacts');
  const f=ALL.filter(c=> (c.name||'').toLowerCase().includes(q));
  const groups={tous:[],admin:[],prof:[],eleve:[]};
  f.forEach(c=> groups[c.type].push(c));
  root.innerHTML='';
  if(groups.tous.length) addSection('Fil public',groups.tous,root);
  if(groups.admin.length) addSection('Administration',groups.admin,root);
  if(groups.prof.length) addSection('Professeurs',groups.prof,root);
  if(groups.eleve.length) addSection('Élèves',groups.eleve,root);
};
(async ()=>{ const r=await api('contacts',{},'GET'); if(!r.error) renderContacts(r.contacts); else alert(r.msg||'Erreur contacts'); })();
</script>
</body>
</html>
