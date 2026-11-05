<?php
// mykelasi/admin/../repetitorat_student.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

// --- Connexion DB robuste ---
$pdo = null;
foreach ([__DIR__.'/../../../database/db_connect.php', __DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Contexte école ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$uid]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
    }
}

// --- Traitement du changement de statut ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_statut') {
    $resId = (int)($_POST['reservation_id'] ?? 0);
    $newStatut = trim((string)($_POST['statut'] ?? ''));
    if ($resId > 0 && in_array($newStatut, ['en_attente', 'confirmee', 'annulee'], true)) {
        $stUp = $pdo->prepare("UPDATE reservations_repetitorat SET statut = :st WHERE id = :id AND code_ecole = :ce");
        $stUp->execute([':st' => $newStatut, ':id' => $resId, ':ce' => $codeEcole]);
        header('Location: repetitorat_student.php?msg=updated'); exit;
    }
}

// --- Traitement de la suppression ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $codeEcole = (string)($_SESSION['code_ecole'] ?? '');

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM reservations_repetitorat WHERE id = :id AND code_ecole = :ce");
            $stmt->execute([':id' => $id, ':ce' => $codeEcole]);
        } catch (Throwable $e) {
            // Gestion silencieuse ou journalisation de l'erreur
        }
    }
    header('Location: ../repetitorat_student.php?msg=deleted'); exit;
}

// --- Charger les réservations de répétitorat ---
$reservations = [];
try {
    $sql = "
        SELECT 
            id,
            code_ecole,
            nom_eleve,
            postnom_eleve,
            prenom_eleve,
            sexe_eleve,
            date_naissance,
            lieu_naissance,
            classe,
            ecole_provenance,
            phone_eleve,
            nom_parent,
            whatsapp_parent,
            phone_parent,
            matieres,
            mode,
            jours_souhaites,
            heure_souhaitee,
            frequence,
            statut,
            created_at
        FROM reservations_repetitorat
    ";

    $params = [];
    if ($codeEcole !== '') {
        $sql .= " WHERE code_ecole = :ce ";
        $params[':ce'] = $codeEcole;
    }

    $sql .= " ORDER BY id DESC ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $reservations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $reservations = [];
}
?>