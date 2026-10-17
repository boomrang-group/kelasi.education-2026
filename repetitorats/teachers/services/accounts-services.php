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
$successMsg = '';
$errorMsg = '';

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');

    try {
        if ($newPassword !== '') {
            $hashedPwd = password_hash($newPassword, PASSWORD_DEFAULT);
            $st = $pdo->prepare("UPDATE users SET first_name = :fn, last_name = :ln, phone = :ph, PASSWORD = :pw WHERE id = :id");
            $st->execute([':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone, ':pw' => $hashedPwd, ':id' => $userId]);
        } else {
            $st = $pdo->prepare("UPDATE users SET first_name = :fn, last_name = :ln, phone = :ph WHERE id = :id");
            $st->execute([':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone, ':id' => $userId]);
        }
        
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $successMsg = "Profil mis à jour avec succès !";
    } catch (Throwable $e) {
        $errorMsg = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération des données fraîches de l'utilisateur
$stUser = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stUser->execute([':id' => $userId]);
$userData = $stUser->fetch(PDO::FETCH_ASSOC);

$nomComplet = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
if ($nomComplet === '') { $nomComplet = $userData['username']; }

$nomEcole = $_SESSION['nom_ecole'] ?? 'Raynée International School';
$urlEcole = $_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international';