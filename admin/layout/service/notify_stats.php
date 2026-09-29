<?php
// service/notify_stats.php
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

// Depuis quand on compte ? (par défaut : 24h), sinon on garde un since en session
$since = $_SESSION['admin_notify_since'] ?? (new DateTime('-24 hours'))->format('Y-m-d H:i:s');

// Helper pour COUNT try/catch
function countFrom(PDO $pdo, string $sql, array $params): int {
  try{ $st = $pdo->prepare($sql); $st->execute($params); return (int)$st->fetchColumn(); }
  catch(Throwable $e){ return 0; }
}

$counts = [
  'messages'   => 0,
  'etudiants'  => 0,
  'cours'      => 0,
  'paiements'  => 0,
];

// Chat messages (table qu’on a créée)
$counts['messages'] = countFrom($pdo,
  "SELECT COUNT(*) FROM chat_messages WHERE code_ecole=:ce AND created_at > :since",
  [':ce'=>$codeEcole, ':since'=>$since]
);

// Étudiants nouvellement créés (si colonne created_at existe)
$counts['etudiants'] = countFrom($pdo,
  "SELECT COUNT(*) FROM students WHERE code_ecole=:ce AND (created_at > :since OR date_created > :since)",
  [':ce'=>$codeEcole, ':since'=>$since]
);

// Cours publiés (adapter le nom de table/colonne si différent)
$counts['cours'] = countFrom($pdo,
  "SELECT COUNT(*) FROM cours WHERE code_ecole=:ce AND (created_at > :since OR date_publication > :since)",
  [':ce'=>$codeEcole, ':since'=>$since]
);

// Paiements récents
$counts['paiements'] = countFrom($pdo,
  "SELECT COUNT(*) FROM paiement WHERE code_ecole=:ce AND (date_paiement > :since OR created_at > :since)",
  [':ce'=>$codeEcole, ':since'=>$since]
);

$total = array_sum($counts);

echo json_encode(['status'=>'ok', 'since'=>$since, 'total'=>$total, 'detail'=>$counts]);
