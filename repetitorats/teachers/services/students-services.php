<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['repetiteur','Repetiteur'], true)) {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$pdo = null;
foreach ([__DIR__.'/../../../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo)) {
    http_response_code(500);
    exit('Erreur serveur : Base de données indisponible.');
}

$userId = (int)$_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? 'Répétiteur');
$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName = (string) ($_SESSION['last_name'] ?? '');
$codeEcole = (string) ($_SESSION['code_ecole'] ?? '');

$nomComplet = trim($firstName . ' ' . $lastName);
if ($nomComplet === '') { $nomComplet = $username; }

$nomEcole = $_SESSION['nom_ecole'] ?? 'Raynée International School';
$urlEcole = $_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international';

// Récupération des élèves encadrés assignés
$eleves = [];
try {
    $st = $pdo->prepare("
        SELECT * FROM reservations_repetitorat 
        WHERE (repetiteur_pref LIKE :nom OR repetiteur_pref LIKE :username)
          " . ($codeEcole !== '' ? " AND code_ecole = :ce" : "") . "
        ORDER BY id DESC
    ");
    $params = [
        ':nom' => '%' . $nomComplet . '%',
        ':username' => '%' . $username . '%'
    ];
    if ($codeEcole !== '') $params[':ce'] = $codeEcole;
    $st->execute($params);
    $eleves = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}