<?php
// repetitorats/students/services/cours-services.php
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

$mesCours = [];

try {
    // Récupération des cours et leçons publiés
    $sql = "
        SELECT 
            c.id AS cours_id,
            c.nom AS cours_nom,
            l.id AS lecon_id,
            l.titre AS lecon_titre,
            l.created_at AS lecon_date,
            cnt.type_contenu,
            cnt.contenu_id
        FROM cours_repetitorat c
        JOIN lecons_repetitorat l ON l.cours_id = c.id
        LEFT JOIN lecon_contenus_repetitorat cnt ON cnt.lecon_id = l.id
        WHERE l.is_published = 1
    ";

    if ($codeEcole !== '') {
        $sql .= " AND c.code_ecole = :ce";
        $st = $pdo->prepare($sql . " ORDER BY c.nom ASC, l.ordre ASC");
        $st->execute([':ce' => $codeEcole]);
    } else {
        $st = $pdo->prepare($sql . " ORDER BY c.nom ASC, l.ordre ASC");
        $st->execute();
    }

    $mesCours = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $mesCours = [];
}