<?php
// repetitorats/teachers/services/dashboard-services.php
declare(strict_types=1);
// Au tout début de vos services ou dans un fichier de configuration global
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['repetiteur','Repetiteur'], true)) {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

// Connexion à la base de données
$pdo = null;
foreach ([__DIR__.'/../../../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo)) {
    http_response_code(500);
    exit('Erreur serveur : Base de données indisponible.');
}

// Informations de l'utilisateur
$userId = (int)$_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? 'Répétiteur');
$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName = (string) ($_SESSION['last_name'] ?? '');
$codeEcole = (string) ($_SESSION['code_ecole'] ?? '');

$nomComplet = trim($firstName . ' ' . $lastName);
if ($nomComplet === '') {
    $nomComplet = $username;
}

// Nom et URL de l'école depuis la session si disponibles
$nomEcole = $_SESSION['nom_ecole'] ?? 'Espace Répétiteur — MyKelasi';
$urlEcole = $_SESSION['url_ecole'] ?? 'kelasi.education/repetitorat';

// Récupération des statistiques réelles
$totalEleves = 0;
$totalCours = 0;
$totalQuizzes = 0;

try {
    $stEleves = $pdo->prepare("
        SELECT COUNT(*) FROM reservations_repetitorat 
        WHERE (repetiteur_pref LIKE :nom OR repetiteur_pref LIKE :username)
          AND statut = 'confirmee'
          " . ($codeEcole !== '' ? " AND code_ecole = :ce" : "") . "
    ");
    $paramsEleves = [
        ':nom' => '%' . $nomComplet . '%',
        ':username' => '%' . $username . '%'
    ];
    if ($codeEcole !== '') $paramsEleves[':ce'] = $codeEcole;
    $stEleves->execute($paramsEleves);
    $totalEleves = (int)$stEleves->fetchColumn();

    $stCours = $pdo->prepare("SELECT COUNT(*) FROM cours WHERE teacher_user_id = :uid");
    $stCours->execute([':uid' => $userId]);
    $totalCours = (int)$stCours->fetchColumn();

    $stQuiz = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_user_id = :uid");
    $stQuiz->execute([':uid' => $userId]);
    $totalQuizzes = (int)$stQuiz->fetchColumn();
} catch (Throwable $e) {}