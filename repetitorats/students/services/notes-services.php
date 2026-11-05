<?php
// repetitorats/students/services/notes-services.php
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

$mesNotes = [];
$moyenneGenerale = null;
$totalEvaluations = 0;

try {
    // Récupération des notes obtenues aux quiz par cet élève
    $st = $pdo->prepare("
        SELECT 
            r.id AS resultat_id,
            r.note_obtenue,
            r.submitted_at,
            q.titre AS quiz_titre,
            q.type_quiz,
            c.nom AS cours_nom
        FROM quiz_resultats_repetitorat r
        JOIN quiz_repetitorat q ON r.quiz_id = q.id
        LEFT JOIN cours_repetitorat c ON q.cours_id = c.id
        WHERE r.student_id = :uid
        ORDER BY r.submitted_at DESC
    ");
    $st->execute([':uid' => $userId]);
    $mesNotes = $st->fetchAll(PDO::FETCH_ASSOC);

    // Calcul de la moyenne générale
    if (!empty($mesNotes)) {
        $sommeNotes = 0;
        foreach ($mesNotes as $note) {
            $sommeNotes += (float)($note['note_obtenue'] ?? 0);
        }
        $totalEvaluations = count($mesNotes);
        $moyenneGenerale = round($sommeNotes / $totalEvaluations, 2);
    }
} catch (Throwable $e) {
    $mesNotes = [];
}