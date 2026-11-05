
<?php
// kelasi/admin/repetitorats/index.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../../login/index.php?msg=forbidden'); exit;
}

// Connexion DB
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : Base de données indisponible.');
}

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');

// Initialisation des indicateurs
$totalDemandes = 0;
$demandesEnAttente = 0;
$demandesConfirmees = 0;
$demandesAnnulees = 0;
$totalProfesseurs = 0;

try {
    // Total des demandes
    $st1 = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat" . ($codeEcole ? " WHERE code_ecole = :ce" : ""));
    $st1->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $totalDemandes = (int)$st1->fetchColumn();

    // Demandes en attente
    $st2 = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'en_attente'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $st2->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesEnAttente = (int)$st2->fetchColumn();

    // Demandes confirmées
    $stConf = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'confirmee'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $stConf->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesConfirmees = (int)$stConf->fetchColumn();

    // Demandes annulées
    $stAnn = $pdo->prepare("SELECT COUNT(*) FROM reservations_repetitorat WHERE statut = 'annulee'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $stAnn->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $demandesAnnulees = (int)$stAnn->fetchColumn();

    // Total des professeurs (rôle 'repetiteur')
    $st3 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'repetiteur'" . ($codeEcole ? " AND code_ecole = :ce" : ""));
    $st3->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $totalProfesseurs = (int)$st3->fetchColumn();
} catch (Throwable $e) {
    // Gestion silencieuse si les tables ne sont pas encore toutes initialisées
}
?>