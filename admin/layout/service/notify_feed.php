<?php
// service/notify_feed.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// DB
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php'] as $p) {
  if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB unavailable']); exit;
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'no school']); exit; }

$since = $_SESSION['admin_notify_since'] ?? (new DateTime('-24 hours'))->format('Y-m-d H:i:s');
$items = [];

// On charge jusqu’à ~10 par type puis on fusionne/tri
function safeQuery(PDO $pdo, string $sql, array $params): array {
  try { $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
  catch(Throwable $e) { return []; }
}

// Messages
$rows = safeQuery($pdo,
  "SELECT id, created_at, sender_name AS who, message AS what
   FROM chat_messages
   WHERE code_ecole=:ce AND created_at > :since
   ORDER BY id DESC LIMIT 10",
  [':ce'=>$codeEcole, ':since'=>$since]
);
foreach($rows as $r){
  $items[] = [
    'ts'    => strtotime($r['created_at'] ?? 'now'),
    'icon'  => 'fas fa-comments',
    'title' => 'Nouveau message',
    'text'  => mb_strimwidth((string)$r['what'], 0, 140, '…'),
    'meta'  => 'De: '.($r['who'] ?? '—'),
    'when'  => $r['created_at'] ?? '',
    'link'  => 'admin/chat.php',
    'badge' => 'Chat'
  ];
}

// Étudiants créés
$rows = safeQuery($pdo,
  "SELECT id, CONCAT_WS(' ', first_name, last_name) AS fullname, COALESCE(created_at, date_created) AS c
   FROM students
   WHERE code_ecole=:ce AND (created_at > :since OR date_created > :since)
   ORDER BY id DESC LIMIT 10",
  [':ce'=>$codeEcole, ':since'=>$since]
);
foreach($rows as $r){
  $items[] = [
    'ts'    => strtotime($r['c'] ?? 'now'),
    'icon'  => 'fas fa-user-plus',
    'title' => 'Nouvelle inscription',
    'text'  => (string)($r['fullname'] ?: 'Élève'),
    'meta'  => 'ID: '.$r['id'],
    'when'  => $r['c'] ?? '',
    'link'  => 'admin/all-students.php',
    'badge' => 'Élève'
  ];
}

// Cours publiés
$rows = safeQuery($pdo,
  "SELECT id, titre, COALESCE(created_at, date_publication) AS c
   FROM cours
   WHERE code_ecole=:ce AND (created_at > :since OR date_publication > :since)
   ORDER BY id DESC LIMIT 10",
  [':ce'=>$codeEcole, ':since'=>$since]
);
foreach($rows as $r){
  $items[] = [
    'ts'    => strtotime($r['c'] ?? 'now'),
    'icon'  => 'fas fa-book',
    'title' => 'Cours publié',
    'text'  => (string)($r['titre'] ?: 'Cours'),
    'meta'  => 'ID: '.$r['id'],
    'when'  => $r['c'] ?? '',
    'link'  => 'admin/cours.php',
    'badge' => 'Cours'
  ];
}

// Paiements
$rows = safeQuery($pdo,
  "SELECT id, eleve, montant_paye, COALESCE(date_paiement, created_at) AS c
   FROM paiement
   WHERE code_ecole=:ce AND (date_paiement > :since OR created_at > :since)
   ORDER BY id DESC LIMIT 10",
  [':ce'=>$codeEcole, ':since'=>$since]
);
foreach($rows as $r){
  $items[] = [
    'ts'    => strtotime($r['c'] ?? 'now'),
    'icon'  => 'fas fa-cash-register',
    'title' => 'Paiement reçu',
    'text'  => 'Montant: $'.number_format((float)$r['montant_paye'], 2, '.', ' '),
    'meta'  => 'Élève ID: '.$r['eleve'],
    'when'  => $r['c'] ?? '',
    'link'  => 'admin/paiement.php',
    'badge' => 'Paiement'
  ];
}

// tri global par date desc et tranche
usort($items, function($a,$b){ return ($b['ts'] <=> $a['ts']); });
$items = array_slice($items, 0, 20);

echo json_encode(['status'=>'ok','since'=>$since,'items'=>$items]);
