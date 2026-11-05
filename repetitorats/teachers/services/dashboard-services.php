<?php
// repetitorats/teachers/services/dashboard-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

// Connexion à la base de données
$pdo = null;
$dbPaths = [
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];

foreach ($dbPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : base de données indisponible.');
}

$userId = (int) $_SESSION['user_id'];
$codeEcole = trim((string) ($_SESSION['code_ecole'] ?? ''));
$username = trim((string) ($_SESSION['username'] ?? 'Répétiteur'));
$firstName = trim((string) ($_SESSION['first_name'] ?? ''));
$lastName = trim((string) ($_SESSION['last_name'] ?? ''));

$nomComplet = trim($firstName . ' ' . $lastName);
if ($nomComplet === '') {
    $nomComplet = $username;
}

$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

// Initialisation des compteurs
$totalEleves = 0;
$totalCours = 0;
$totalQuizzes = 0;
$totalMoyenneGeneral = 0.0;

try {
    // 1. Nombre d'élèves encadrés / réservations confirmées pour ce répétiteur[cite: 5, 6]
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
    $totalEleves = (int) $stEleves->fetchColumn();

    // 2. Nombre de cours créés dans cours_repetitorat[cite: 5, 6]
    $stCours = $pdo->prepare("
        SELECT COUNT(*) FROM cours_repetitorat 
        WHERE teacher_user_id = :uid 
        " . ($codeEcole !== '' ? " AND code_ecole = :ce" : "") . "
    ");
    $paramsCours = [':uid' => $userId];
    if ($codeEcole !== '') $paramsCours[':ce'] = $codeEcole;
    $stCours->execute($paramsCours);
    $totalCours = (int) $stCours->fetchColumn();

    // 3. Nombre de quiz créés dans quiz_repetitorat[cite: 5, 6]
    $stQuiz = $pdo->prepare("
        SELECT COUNT(*) FROM quiz_repetitorat 
        WHERE teacher_user_id = :uid 
        " . ($codeEcole !== '' ? " AND code_ecole = :ce" : "") . "
    ");
    $paramsQuiz = [':uid' => $userId];
    if ($codeEcole !== '') $paramsQuiz[':ce'] = $codeEcole;
    $stQuiz->execute($paramsQuiz);
    $totalQuizzes = (int) $stQuiz->fetchColumn();

} catch (Throwable $e) {
    // En cas d'erreur SQL, les indicateurs restent à 0
}