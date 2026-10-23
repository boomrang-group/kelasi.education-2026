<?php
// repetitorats/students/services/reviser-cours-services.php
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
$leconId = (int) ($_GET['id'] ?? 0);
$codeEcole = trim((string) ($_SESSION['code_ecole'] ?? ''));

$firstName = trim((string) ($_SESSION['first_name'] ?? ''));
$lastName = trim((string) ($_SESSION['last_name'] ?? ''));
$username = trim((string) ($_SESSION['username'] ?? 'Élève'));

$nomComplet = trim($firstName . ' ' . $lastName) ?: $username;
$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Espace Élève — MyKelasi');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/repetitorat');

$lecon = null;
$contenus = [];

if ($leconId > 0) {
    try {
        // Récupérer les détails de la leçon et du cours associé
        $stL = $pdo->prepare("
            SELECT l.*, c.nom AS cours_nom, c.teacher_user_id 
            FROM lecons_repetitorat l
            JOIN cours_repetitorat c ON l.cours_id = c.id
            WHERE l.id = :id AND l.is_published = 1 
            LIMIT 1
        ");
        $stL->execute([':id' => $leconId]);
        $lecon = $stL->fetch(PDO::FETCH_ASSOC);

        if ($lecon) {
            // Récupérer les contenus multimédias liés à la leçon (lecon_contenus_repetitorat)
            $stC = $pdo->prepare("
                SELECT * FROM lecon_contenus_repetitorat 
                WHERE lecon_id = :lid 
                ORDER BY ordre ASC
            ");
            $stC->execute([':lid' => $leconId]);
            $contenus = $stC->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $lecon = null;
    }
}