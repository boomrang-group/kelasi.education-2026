<?php
// admin/service/paiement_cash_validate.php — Validation unitaire ou en lot
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- DB connect ---
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB indisponible']); exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = strtolower((string)($_SESSION['role'] ?? ''));
$isAdmin  = in_array($role, ['admin','administrateur'], true);
$codeEcole= (string)($_SESSION['code_ecole'] ?? '');
if (!$isAdmin || !$codeEcole) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Accès refusé']); exit;
}

// CSRF
$csrf = $_POST['_csrf'] ?? '';
if (!$csrf || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$csrf)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'CSRF invalide']); exit;
}

$id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

if ($id <= 0 && !$ids) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Paramètres manquants']); exit;
}

try {
    if ($id > 0) {
        // Validation unitaire
        $st = $pdo->prepare("
            UPDATE paiement
               SET is_validated = 1,
                   validated_at = NOW(),
                   validated_by = :uid
             WHERE id = :id
               AND code_ecole = :ce
               AND (LOWER(mode) = 'cash' OR mode = 'CASH')
               AND (is_validated = 0 OR is_validated IS NULL)
             LIMIT 1
        ");
        $st->execute([':uid'=>$userId, ':id'=>$id, ':ce'=>$codeEcole]);
        echo json_encode(['status'=>'ok','updated'=>$st->rowCount()]); exit;
    }

    // Validation en lot
    // Sécuriser IN (...) : préparer dynamiquement
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        UPDATE paiement
           SET is_validated = 1,
               validated_at = NOW(),
               validated_by = ?
         WHERE id IN ($place)
           AND code_ecole = ?
           AND (LOWER(mode) = 'cash' OR mode = 'CASH')
           AND (is_validated = 0 OR is_validated IS NULL)
    ";
    $params = array_merge([$userId], $ids, [$codeEcole]);
    $st = $pdo->prepare($sql);
    $st->execute($params);

    echo json_encode(['status'=>'ok','updated'=>$st->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
