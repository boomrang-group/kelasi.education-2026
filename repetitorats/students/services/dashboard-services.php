<?php
// repetitorats/students/services/dashboard-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || !in_array($role, ['eleve', 'eleve_encadre'], true)) {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

// Connexion BDD
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

$firstName = trim((string) ($_SESSION['first_name'] ?? ''));
$lastName = trim((string) ($_SESSION['last_name'] ?? ''));
$username = trim((string) ($_SESSION['username'] ?? 'Élève'));

$nomComplet = trim($firstName . ' ' . $lastName) ?: $username;
$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Espace Élève — MyKelasi');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/repetitorat');

// Statistique 1 : Nombre de cours publiés accessibles
$totalCours = 0;
try {
    $stC = $pdo->prepare('SELECT COUNT(DISTINCT c.id) FROM cours_repetitorat c JOIN lecons_repetitorat l ON l.cours_id = c.id WHERE l.is_published = 1 ' . ($codeEcole ? 'AND c.code_ecole = :ce' : ''));
    if ($codeEcole) {
        $stC->execute([':ce' => $codeEcole]);
    } else {
        $stC->execute();
    }
    $totalCours = (int) $stC->fetchColumn();
} catch (Throwable $e) {}

// Statistique 2 : Total des quiz publiés disponibles
$totalQuiz = 0;
try {
    $stQ = $pdo->prepare('SELECT COUNT(*) FROM quiz_repetitorat WHERE is_published = 1 ' . ($codeEcole ? 'AND code_ecole = :ce' : ''));
    if ($codeEcole) {
        $stQ->execute([':ce' => $codeEcole]);
    } else {
        $stQ->execute();
    }
    $totalQuiz = (int) $stQ->fetchColumn();
} catch (Throwable $e) {}

// Statistique 3 : Moyenne de l'élève
$moyenneEleve = null;
try {
    $stM = $pdo->prepare('SELECT AVG(note_obtenue) FROM quiz_resultats_repetitorat WHERE student_id = :uid');
    $stM->execute([':uid' => $userId]);
    $resM = $stM->fetchColumn();
    if ($resM !== false && $resM !== null) {
        $moyenneEleve = round((float) $resM, 2);
    }
} catch (Throwable $e) {}