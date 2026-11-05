<?php
// repetitorats/students/services/quiz-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || !in_array($role, ['eleve', 'eleve_encadre'], true)) {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

// Connexion à la BDD
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

$quizzesList = [];

try {
    // Récupération des quiz publiés pour l'école et vérification des résultats de l'élève
    $sql = "
        SELECT 
            q.*, 
            c.nom AS cours_nom,
            r.id AS resultat_id,
            r.note_obtenue,
            r.submitted_at
        FROM quiz_repetitorat q
        LEFT JOIN cours_repetitorat c ON q.cours_id = c.id
        LEFT JOIN quiz_resultats_repetitorat r ON r.quiz_id = q.id AND r.student_id = :uid
        WHERE q.is_published = 1
    ";

    if ($codeEcole !== '') {
        $sql .= " AND q.code_ecole = :ce";
        $st = $pdo->prepare($sql . " ORDER BY q.id DESC");
        $st->execute([':uid' => $userId, ':ce' => $codeEcole]);
    } else {
        $st = $pdo->prepare($sql . " ORDER BY q.id DESC");
        $st->execute([':uid' => $userId]);
    }

    $quizzesList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $quizzesList = [];
}