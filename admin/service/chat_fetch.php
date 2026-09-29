<?php
// service/chat_fetch.php
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

// Contexte session
$userId    = (int)($_SESSION['user_id'] ?? 0);
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId <= 0 || !$codeEcole) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

// Inputs
$scope   = strtolower((string)($_GET['scope'] ?? 'public'));
$sinceId = (int)($_GET['since_id'] ?? 0);
$peer    = isset($_GET['peer_user']) ? (int)$_GET['peer_user'] : 0;

$allowedScopes = ['public','professeur','eleves','direct'];
if (!in_array($scope, $allowedScopes, true)) { $scope = 'public'; }

try {
    if ($scope === 'direct') {
        if ($peer <= 0) {
            // sans peer -> rien
            echo json_encode(['status'=>'ok','items'=>[]]);
            exit;
        }

        // fil privé bidirectionnel
        $sql = "
            SELECT id, code_ecole, scope, sender_user, sender_name, sender_role,
                   recipient_user, message, created_at
            FROM chat_messages
            WHERE code_ecole = :ce
              AND scope = 'direct'
              AND (
                    (sender_user = :me AND recipient_user = :peer)
                 OR (sender_user = :peer AND recipient_user = :me)
              )
        ";
        $params = [':ce'=>$codeEcole, ':me'=>$userId, ':peer'=>$peer];

        if ($sinceId > 0) {
            $sql .= " AND id > :sinceId";
            $params[':sinceId'] = $sinceId;
        }

        $sql .= " ORDER BY id ASC LIMIT 200";
    } else {
        // canal par rôle (public / professeur / eleves)
        $sql = "
            SELECT id, code_ecole, scope, sender_user, sender_name, sender_role,
                   recipient_user, message, created_at
            FROM chat_messages
            WHERE code_ecole = :ce
              AND scope = :scope
        ";
        $params = [':ce'=>$codeEcole, ':scope'=>$scope];

        if ($sinceId > 0) {
            $sql .= " AND id > :sinceId";
            $params[':sinceId'] = $sinceId;
        }

        $sql .= " ORDER BY id ASC LIMIT 200";
    }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['status'=>'ok','items'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
