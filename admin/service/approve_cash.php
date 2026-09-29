<?php
// admin/service/approve_cash.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/db_connect.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['status'=>'err','message'=>'DB indisponible']);
    exit;
}

/* ========= SÉCURITÉ ========= */
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = strtolower($_SESSION['role'] ?? '');
$code_ecole = $_SESSION['code_ecole'] ?? '';

if (!in_array($role, ['admin','administrateur'], true)) {
    echo json_encode(['status'=>'err','message'=>'Accès refusé']);
    exit;
}

if (
    empty($_POST['_csrf']) ||
    !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'])
) {
    echo json_encode(['status'=>'err','message'=>'CSRF invalide']);
    exit;
}

/* ========= PARAMÈTRES ========= */
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve','reject'], true)) {
    echo json_encode(['status'=>'err','message'=>'Paramètres invalides']);
    exit;
}

/* ========= CHARGER PAIEMENT ========= */
$st = $pdo->prepare("SELECT * FROM paiement WHERE id=:id LIMIT 1");
$st->execute([':id'=>$id]);
$paiement = $st->fetch(PDO::FETCH_ASSOC);

if (!$paiement) {
    echo json_encode(['status'=>'err','message'=>'Paiement introuvable']);
    exit;
}

if (strcasecmp($paiement['mode'], 'Cash') !== 0) {
    echo json_encode(['status'=>'err','message'=>'Paiement non cash']);
    exit;
}

if ($code_ecole && $paiement['code_ecole'] !== $code_ecole) {
    echo json_encode(['status'=>'err','message'=>'École invalide']);
    exit;
}

/* ========= ACTION ========= */
try {

    if ($action === 'approve') {

        $st = $pdo->prepare("
            UPDATE paiement
            SET is_validated = 1,
                validated_at = NOW(),
                validated_by = :uid
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':uid' => $userId,
            ':id'  => $id
        ]);

        echo json_encode([
            'status'  => 'ok',
            'message' => 'Paiement cash approuvé'
        ]);
        exit;
    }

    /* ===== REJET ===== */
    $st = $pdo->prepare("
        UPDATE paiement
        SET is_validated = -1,
            validated_at = NOW(),
            validated_by = :uid
        WHERE id = :id
        LIMIT 1
    ");
    $st->execute([
        ':uid'=>$userId,
        ':id'=>$id
    ]);

    echo json_encode([
        'status'=>'ok',
        'message'=>'Paiement rejeté'
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status'=>'err',
        'message'=>'Erreur SQL : '.$e->getMessage()
    ]);
    exit;
}