<?php
// update-status.php
declare(strict_types=1);

session_start();

// ---------- Helpers ----------
function cu(): ?array {
    return $_SESSION['user'] ?? null;
}
function uid(): int { $u = cu(); return $u['id'] ?? 0; }
function norm_role(?string $r): string { return strtolower(trim((string)$r)); }
function has_role($roles): bool {
    $u = cu(); if (!$u || empty($u['role'])) return false;
    $cur = norm_role($u['role']);
    $arr = is_array($roles) ? $roles : [$roles];
    foreach ($arr as $r) if ($cur === norm_role($r)) return true;
    return false;
}

// ---------- Vérification rôle ----------
if (!has_role('manager')) {
    http_response_code(403);
    exit('Accès refusé.');
}

// ---------- Vérification POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ecole_id']) || !isset($_POST['statut'])) {
    http_response_code(400);
    exit('Requête invalide.');
}

$ecole_id = (int)$_POST['ecole_id'];
$statut   = trim($_POST['statut']);
$allowed_status = ['approuver', 'en attente', 'non approuver'];

if (!in_array($statut, $allowed_status, true)) {
    http_response_code(400);
    exit('Statut invalide.');
}

// ---------- DB Connect ----------
$pdo = null;
$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur DB.');
}

// ---------- Update statut ----------
try {
    $sql = "UPDATE ecoles SET statut = :statut WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':statut' => $statut,
        ':id'     => $ecole_id
    ]);
    // Redirection vers all-school.php
    header('Location: all-school.php');
    exit;
} catch (Throwable $e) {
    error_log('update-status error: ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur lors de la mise à jour.');
}