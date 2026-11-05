<?php
// repetitorats/teachers/services/notes-eleves-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$dbPaths = array(
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
);

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

$nomComplet = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
if ($nomComplet === '') {
    $nomComplet = (string) ($_SESSION['username'] ?? 'Répétiteur');
}
$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

$elevesNotes = array();

// Récupération des élèves encadrés par ce répétiteur et leurs notes d'évaluations/quiz
try {
    // Cette requête récupère les notes des élèves pour les quiz du répétiteur et calcule leur moyenne
    $stNotes = $pdo->prepare(
        'SELECT 
            u.id AS eleve_id,
            u.first_name,
            u.last_name,
            u.username,
            COUNT(r.id) AS total_quiz_passes,
            AVG(r.note_obtenue) AS moyenne_notes,
            SUM(r.note_obtenue) AS total_points
         FROM users u
         JOIN student_repetiteur sr ON sr.student_id = u.id
         LEFT JOIN quiz_resultats_repetitorat r ON r.student_id = u.id
         WHERE sr.teacher_user_id = :uid AND sr.code_ecole = :ce
         GROUP BY u.id, u.first_name, u.last_name, u.username
         ORDER BY u.last_name ASC'
    );
    $stNotes->execute(array(':uid' => $userId, ':ce' => $codeEcole));
    $elevesNotes = $stNotes->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Si la table de résultats n'existe pas encore ou en cas d'erreur mineure
    $elevesNotes = array();
}