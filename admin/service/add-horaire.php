<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../service/security_helpers.php';
csrf_protect();

// ======================================
// DB CONNECTION (auto fallback)
// ======================================
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
    header('Location: ../add-horaires.php?msg=error');
    exit;
}

// ======================================
// AUTH CHECK
// ======================================
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));

if (!in_array($role, ['admin', 'administrateur'], true)) {
    http_response_code(403);
    header('Location: ../add-horaires.php?msg=error');
    exit;
}

// ======================================
// ECOLE CODE
// ======================================
$code_ecole = $_SESSION['code_ecole'] ?? '';

if (!$code_ecole) {

    $userId = $_SESSION['user_id'] ?? null;

    if ($userId) {
        $st = $pdo->prepare("
            SELECT code_ecole
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $st->execute([':id' => $userId]);

        $code_ecole = (string)($st->fetchColumn() ?: '');

        if ($code_ecole) {
            $_SESSION['code_ecole'] = $code_ecole;
        }
    }
}

if (!$code_ecole) {
    http_response_code(400);
    header('Location: ../add-horaires.php?msg=error');
    exit;
}

// ======================================
// ROUTING (GET / POST)
// ======================================
$method = $_SERVER['REQUEST_METHOD'];

// ======================================
// GET => FETCH HORAIRES
// ======================================
if ($method === 'GET') {

    $class_id = (int)($_GET['class_id'] ?? 0);

    if ($class_id <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM horaires
        WHERE class_id = ?
        AND code_ecole = ?
        ORDER BY heure_debut ASC
    ");

    $stmt->execute([$class_id, $code_ecole]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ======================================
// POST => CREATE / REPLACE HORAIRES
// ======================================
if ($method === 'POST') {

    $class_id = (int)($_POST['class_id'] ?? 0);

    $heure_debut = $_POST['heure_debut'] ?? [];
    $heure_fin   = $_POST['heure_fin'] ?? [];

    $lundi    = $_POST['lundi'] ?? [];
    $mardi    = $_POST['mardi'] ?? [];
    $mercredi = $_POST['mercredi'] ?? [];
    $jeudi    = $_POST['jeudi'] ?? [];
    $vendredi = $_POST['vendredi'] ?? [];
    $samedi   = $_POST['samedi'] ?? [];

    if ($class_id <= 0 || empty($heure_debut)) {
        http_response_code(400);
        header('Location: ../add-horaires.php?msg=error');
        exit;
    }

    try {
        // DELETE OLD
        $delete = $pdo->prepare("
            DELETE FROM horaires
            WHERE class_id = ?
            AND code_ecole = ?
        ");

        $delete->execute([$class_id, $code_ecole]);

        // INSERT NEW
        $sql = "
            INSERT INTO horaires
            (
                class_id,
                heure_debut,
                heure_fin,
                lundi,
                mardi,
                mercredi,
                jeudi,
                vendredi,
                samedi,
                code_ecole
            )
            VALUES
            (
                :class_id,
                :heure_debut,
                :heure_fin,
                :lundi,
                :mardi,
                :mercredi,
                :jeudi,
                :vendredi,
                :samedi,
                :code_ecole
            )
        ";

        $stmt = $pdo->prepare($sql);

        foreach ($heure_debut as $i => $hd) {

            $hf = $heure_fin[$i] ?? '';

            if ($hd === '' || $hf === '') {
                continue;
            }

            $stmt->execute([
                ':class_id'    => $class_id,
                ':heure_debut' => $hd,
                ':heure_fin'   => $hf,

                ':lundi'    => trim($lundi[$i] ?? ''),
                ':mardi'    => trim($mardi[$i] ?? ''),
                ':mercredi' => trim($mercredi[$i] ?? ''),
                ':jeudi'    => trim($jeudi[$i] ?? ''),
                ':vendredi' => trim($vendredi[$i] ?? ''),
                ':samedi'   => trim($samedi[$i] ?? ''),

                ':code_ecole' => $code_ecole
            ]);
        }

        header('Location: ../add-horaires.php?msg=created');
        exit;

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'error' => 'Erreur serveur',
            'debug' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : null
        ]);

        exit;
    }
}

// ======================================
// METHOD NOT ALLOWED
// ======================================
http_response_code(405);
header('Location: ../add-horaires.php?msg=error');
    exit;