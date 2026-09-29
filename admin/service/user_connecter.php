<?php
session_start();

// À ce stade, la session doit contenir un code valide
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header("Location: ../../login/index.php?msg=forbidden");
    exit();
} else {
    $code_ecole = $_SESSION['code_ecole'] ?? '';
    $id_admin = $_SESSION['user_id'];
}
?>
