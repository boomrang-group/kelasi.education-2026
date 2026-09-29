<?php
// service/chat_users.php
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
    echo json_encode(['status'=>'error','message'=>'DB unavailable']);
    exit;
}

$userId    = (int)($_SESSION['user_id'] ?? 0);
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId <= 0 || !$codeEcole) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

// role = 'professeur' | 'eleve'  (adapter aux libellés de ta table users)
$role = strtolower((string)($_GET['role'] ?? 'eleve'));
$allowed = ['professeur','eleve','enseignant']; // si tu stockes "enseignant"
if (!in_array($role, $allowed, true)) { $role = 'eleve'; }

try {
    // Adapter aux vrais noms de colonnes de votre table users
    $st = $pdo->prepare("
        SELECT id, username, nom, prenom, role
        FROM users
        WHERE code_ecole = :ce
          AND LOWER(role) IN (:r1, :r2)
        ORDER BY nom, prenom, username
        LIMIT 500
    ");

    // on mappe 'professeur' sur ['professeur','enseignant'] pour compat
    $map = ($role === 'professeur') ? ['professeur','enseignant'] : ['eleve','eleve'];

    // NOTE: pour binder un IN avec paramètres, on passe 2 placeholders distincts
    $st->bindValue(':ce', $codeEcole, PDO::PARAM_STR);
    $st->bindValue(':r1', $map[0], PDO::PARAM_STR);
    $st->bindValue(':r2', $map[1], PDO::PARAM_STR);
    $st->execute();

    $rows = [];
    while ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $label = trim(($u['nom'] ?? '').' '.($u['prenom'] ?? ''));
        if ($label === '') $label = (string)($u['username'] ?? ('#'.$u['id']));
        $rows[] = [
            'id'    => (int)$u['id'],
            'label' => $label,
            'role'  => (string)$u['role'],
        ];
    }

    echo json_encode(['status'=>'ok','items'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
