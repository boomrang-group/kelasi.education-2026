<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// =========================
// DB CONNECTION
// =========================
$pdo = null;
$db_candidates = [
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];

foreach ($db_candidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur (DB).');
}

require_once __DIR__ . '/../../service/security_helpers.php';
csrf_protect();

// =========================
// AUTH CHECK
// =========================
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['admin','administrateur'], true)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// =========================
// CODE ECOLE
// =========================
$code_ecole = $_SESSION['code_ecole'] ?? '';
if (!$code_ecole) {
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $userId]);
        $code_ecole = (string)($st->fetchColumn() ?: '');
        if ($code_ecole) $_SESSION['code_ecole'] = $code_ecole;
    }
}
if (!$code_ecole) exit('Code école manquant.');

// =========================
// POST ONLY
// =========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// =========================
// INPUTS
// =========================
$id          = (int)($_POST['id'] ?? 0); // 🔥 EDIT MODE
$description = trim((string)($_POST['description'] ?? ''));
$niveau      = (int)($_POST['niveau'] ?? 0);
$classe      = trim((string)($_POST['classe'] ?? ''));
$section     = (int)($_POST['section'] ?? 0);
$options     = (int)($_POST['options'] ?? 0);

// =========================
// CHECK NIVEAU TYPE
// =========================
$isSimpleNiveau = false;

$stmt = $pdo->prepare("SELECT description FROM niveau WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $niveau]);
$niveauDesc = strtolower((string)($stmt->fetchColumn() ?? ''));

if (str_contains($niveauDesc, 'primaire') || str_contains($niveauDesc, 'secondaire')) {
    $isSimpleNiveau = true;
}

// =========================
// VALIDATION
// =========================
$missing = [];

if ($niveau <= 0) $missing[] = 'niveau';
if (!$isSimpleNiveau) {
    if ($section <= 0) $missing[] = 'section';
    if ($options <= 0) $missing[] = 'options';
}
if ($classe === '') $missing[] = 'classe';
if ($description === '') $missing[] = 'description';

if ($missing) {
    header('Location: ../add-class.php?msg=error');
    exit;
}

// =========================
// VALUES
// =========================
$sectionVal = $section > 0 ? $section : null;
$optionsVal = $options > 0 ? $options : null;

// =========================
// INSERT OR UPDATE
// =========================
try {

    // =========================
    // 🔥 UPDATE MODE
    // =========================
    if ($id > 0) {

        // check ownership
        $chk = $pdo->prepare("SELECT id FROM classes WHERE id = :id AND code_ecole = :ce");
        $chk->execute([':id' => $id, ':ce' => $code_ecole]);

        if (!$chk->fetchColumn()) {
            exit('Classe introuvable.');
        }

        $sql = "UPDATE classes SET
                    classe = :classe,
                    description = :description,
                    niveau = :niveau,
                    section = :section,
                    options = :options
                WHERE id = :id AND code_ecole = :ce";

        $st = $pdo->prepare($sql);
        $st->execute([
            ':classe'      => $classe,
            ':description' => $description,
            ':niveau'      => $niveau,
            ':section'     => $sectionVal,
            ':options'     => $optionsVal,
            ':id'          => $id,
            ':ce'          => $code_ecole,
        ]);

        header('Location: ../all-class.php?msg=updated');
        exit;
    }

    // =========================
    // 🔥 CREATE MODE
    // =========================
    $sql = "INSERT INTO classes
            (classe, description, niveau, section, options, code_ecole)
            VALUES
            (:classe, :description, :niveau, :section, :options, :code)";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':classe'      => $classe,
        ':description' => $description,
        ':niveau'      => $niveau,
        ':section'     => $sectionVal,
        ':options'     => $optionsVal,
        ':code'        => $code_ecole,
    ]);

    header('Location: ../all-class.php?msg=created');
    exit;

} catch (Throwable $e) {
    if (defined('DEBUG') && DEBUG) {
        echo $e->getMessage();
        exit;
    }

    header('Location: ../add-class.php?msg=error');
    exit;
}