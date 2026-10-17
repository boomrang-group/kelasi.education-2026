<?php
// kelasi/admin/repetitorats/service/repetitorat-service-teacher.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../../login/index.php?msg=forbidden'); exit;
}

// --- Connexion DB robuste ---
$pdo = null;
foreach ([__DIR__.'/../../../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

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

$alertMsg = '';

// --- Suppression d'une réservation ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM reservations_repetitorat WHERE id = :id AND code_ecole = :ce");
            $stmt->execute([':id' => $id, ':ce' => $codeEcole]);
        } catch (Throwable $e) {}
    }
    header('Location: ../repetitorat_teacher.php?msg=deleted'); exit;
}

// --- Traitement 1 : Création globale d'un répétiteur (depuis le bouton du haut) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_repetiteur_global') {
    $first_name     = trim((string)($_POST['first_name'] ?? ''));
    $last_name      = trim((string)($_POST['last_name'] ?? ''));
    $gender         = trim((string)($_POST['gender'] ?? ''));
    $phone          = trim((string)($_POST['phone'] ?? ''));
    $email          = trim((string)($_POST['email'] ?? ''));
    $username       = trim((string)($_POST['username'] ?? ''));
    $plainPassword  = trim((string)($_POST['password'] ?? '123456'));
    $passwordHash   = password_hash($plainPassword, PASSWORD_BCRYPT);
    $specialization = trim((string)($_POST['specialization'] ?? ''));

    try {
        $pdo->beginTransaction();

        // 1. Insertion dans users avec le rôle 'repetiteur'
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, password, email, role, first_name, last_name, phone, code_ecole)
            VALUES (:username, :password, :email, 'repetiteur', :first_name, :last_name, :phone, :code_ecole)
        ");
        $stmtUser->execute([
            ':username'   => $username,
            ':password'   => $passwordHash,
            ':email'      => $email !== '' ? $email : $username . '@kelasi.local',
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':phone'      => $phone,
            ':code_ecole' => $codeEcole
        ]);

        // 2. Insertion dans teacher (si la table est utilisée)
        $stmtTeacher = $pdo->prepare("
            INSERT INTO teacher (first_name, last_name, email, gender, phone, specialization, code_ecole, date_of_joining)
            VALUES (:fn, :ln, :email, :gen, :ph, :spec, :ce, CURDATE())
        ");
        $stmtTeacher->execute([
            ':fn'    => $first_name,
            ':ln'    => $last_name,
            ':email' => $email !== '' ? $email : $username . '@kelasi.local',
            ':gen'   => $gender,
            ':ph'    => $phone,
            ':spec'  => $specialization,
            ':ce'    => $codeEcole
        ]);

        $pdo->commit();
        $alertMsg = '<div class="alert alert-success">✅ Nouveau Répétiteur créé avec succès (Rôle : <b>repetiteur</b>). Mot de passe : <b>' . h($plainPassword) . '</b></div>';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alertMsg = '<div class="alert alert-danger">❌ Erreur : ' . h($e->getMessage()) . '</div>';
    }
}

// --- Traitement 2 : Affectation d'un répétiteur existant à un élève ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_repetiteur') {
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $teacher_name   = trim((string)($_POST['teacher_name'] ?? ''));

    if ($reservation_id > 0 && $teacher_name !== '') {
        try {
            $upRes = $pdo->prepare("UPDATE reservations_repetitorat SET statut = 'confirmee', repetiteur_pref = :prof WHERE id = :id AND code_ecole = :ce");
            $upRes->execute([':prof' => $teacher_name, ':id' => $reservation_id, ':ce' => $codeEcole]);
            $alertMsg = '<div class="alert alert-success">✅ Répétiteur affecté avec succès à l\'élève.</div>';
        } catch (Throwable $e) {
            $alertMsg = '<div class="alert alert-danger">❌ Erreur d\'affectation : ' . h($e->getMessage()) . '</div>';
        }
    }
}

// --- Chargement des professeurs ayant STRICTEMENT le rôle 'repetiteur' ---
$repetiteursExistants = [];
try {
    $stT = $pdo->prepare("
        SELECT id, first_name, last_name, username 
        FROM users 
        WHERE role = 'repetiteur' AND code_ecole = :ce 
        ORDER BY last_name ASC
    ");
    $stT->execute([':ce' => $codeEcole]);
    $repetiteursExistants = $stT->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $repetiteursExistants = [];
}

// --- Charger les réservations élèves ---
$reservations = [];
try {
    $sql = "SELECT * FROM reservations_repetitorat";
    $params = [];
    
    // Condition selon l'école et le statut confirmé
    $conditions = ["statut = 'confirmee'"];
    if ($codeEcole !== '') {
        $conditions[] = "code_ecole = :ce";
        $params[':ce'] = $codeEcole;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY id DESC ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $reservations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $reservations = [];
}
?>