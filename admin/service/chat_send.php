<?php
// service/chat_send.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Connexion DB
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB unavailable']);
    exit;
}

// Auth minimale
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = (string)($_SESSION['role'] ?? 'admin');
$codeEcole= (string)($_SESSION['code_ecole'] ?? '');
$sender   = (string)($_SESSION['username'] ?? ($_SESSION['name'] ?? 'Admin'));

if ($userId <= 0 || !$codeEcole) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

// Inputs
$payload = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$scope   = strtolower(trim((string)($payload['scope'] ?? 'public')));
$message = trim((string)($payload['message'] ?? ''));
$toUser  = isset($payload['to_user']) ? (int)$payload['to_user'] : null; // pour scope=direct

$allowedScopes = ['public','professeur','eleves','direct'];
if (!in_array($scope, $allowedScopes, true)) { $scope = 'public'; }

if ($message === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Message vide']);
    exit;
}

// Construction dynamique des colonnes/valeurs pour éviter HY093
$cols   = ['code_ecole','scope','sender_user','sender_name','sender_role','message'];
$marks  = [':code_ecole',':scope',':sender_user',':sender_name',':sender_role',':message'];
$params = [
    ':code_ecole' => $codeEcole,
    ':scope'      => $scope,
    ':sender_user'=> $userId,
    ':sender_name'=> $sender,
    ':sender_role'=> $role,
    ':message'    => $message,
];

if ($scope === 'direct') {
    if (!$toUser) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Destinataire manquant pour un message direct']);
        exit;
    }
    $cols[]  = 'recipient_user';
    $marks[] = ':recipient_user';
    $params[':recipient_user'] = $toUser;
}

$sql = 'INSERT INTO chat_messages ('.implode(',', $cols).') VALUES ('.implode(',', $marks).')';
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    echo json_encode(['status'=>'ok','id'=>$pdo->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
