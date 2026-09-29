<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = null;
foreach ([
    __DIR__.'/../../database/db_connect.php',
    __DIR__.'/../../../database/db_connect.php',
    __DIR__.'/../database/db_connect.php'
] as $p) {
    if (file_exists($p)) { require $p; break; }
}

if (!isset($pdo)) {
    die("DB error");
}

$userId  = $_SESSION['user_id'] ?? null;
$role    = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin','administrateur'], true);

$codeEcole = $_SESSION['code_ecole'] ?? '';

if (!$codeEcole && $userId) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=?");
    $st->execute([$userId]);
    $codeEcole = (string)$st->fetchColumn();
    $_SESSION['code_ecole'] = $codeEcole;
}

if (!$isAdmin || !$codeEcole) {
    die("Accès refusé");
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'read';

/* =====================================================
   SAVE (INSERT / UPDATE)
===================================================== */
if ($action === 'save') {

    $id = $_POST['id'] ?? null;

    $data = [
        'beneficiaire' => trim($_POST['beneficiaire'] ?? ''),
        'montant' => (float)($_POST['montant'] ?? 0),
        'date_depense' => $_POST['date_depense'] ?? '',
        'description' => trim($_POST['description'] ?? '')
    ];

    if ($id) {

        $st = $pdo->prepare("
            UPDATE depenses SET
                beneficiaire=:beneficiaire,
                montant=:montant,
                date_depense=:date_depense,
                description=:description
            WHERE id=:id AND code_ecole=:ce
        ");

        $st->execute([
            ':beneficiaire'=>$data['beneficiaire'],
            ':montant'=>$data['montant'],
            ':date_depense'=>$data['date_depense'],
            ':description'=>$data['description'],
            ':id'=>$id,
            ':ce'=>$codeEcole
        ]);

        header("Location: ../all-depenses.php?msg=updated");
        exit;

    } else {

        $st = $pdo->prepare("
            INSERT INTO depenses
            (code_ecole, beneficiaire, montant, date_depense, description)
            VALUES (:ce,:beneficiaire,:montant,:date_depense,:description)
        ");

        $st->execute([
            ':ce'=>$codeEcole,
            ':beneficiaire'=>$data['beneficiaire'],
            ':montant'=>$data['montant'],
            ':date_depense'=>$data['date_depense'],
            ':description'=>$data['description']
        ]);

        header("Location: ../all-depenses.php?msg=created");
        exit;
    }
}

/* =====================================================
   DELETE
===================================================== */
if ($action === 'delete') {

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        header("Location: ../all-depenses.php?msg=error");
        exit;
    }

    try {
        $st = $pdo->prepare("
            DELETE FROM depenses
            WHERE id = :id AND code_ecole = :ce
        ");

        $st->execute([
            ':id' => $id,
            ':ce' => $codeEcole
        ]);

        header("Location: ../all-depenses.php?msg=deleted");
        exit;

    } catch (Throwable $e) {
        header("Location: ../all-depenses.php?msg=error");
        exit;
    }
}

/* =====================================================
   READ ALL (LIST)
===================================================== */
$depenses = [];

$st = $pdo->prepare("
    SELECT * FROM depenses
    WHERE code_ecole=:ce
    ORDER BY date_depense DESC
");

$st->execute([':ce'=>$codeEcole]);
$depenses = $st->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   READ ONE (EDIT MODE)
   👉 IMPORTANT POUR TON PROBLEME
===================================================== */
$editData = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {

    $st = $pdo->prepare("
        SELECT * FROM depenses
        WHERE id=:id AND code_ecole=:ce
        LIMIT 1
    ");

    $st->execute([
        ':id'=>(int)$_GET['id'],
        ':ce'=>$codeEcole
    ]);

    $editData = $st->fetch(PDO::FETCH_ASSOC);
}