<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../database/db_connect.php';

$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || $role !== 'prof') {
    header("Location: ../../../login/index.php?msg=forbidden");
    exit();
}

$teacher_user_id = $_SESSION['user_id'] ?? null;
$class = $_SESSION['class_id'] ?? null; 
$code_ecole = $_SESSION['code_ecole'] ?? null; 

// Requête sécurisée avec prepare
$stmt = $pdo->prepare("SELECT * FROM ecoles WHERE code_ecole = :code_ecole");
$stmt->execute([':code_ecole' => $code_ecole]);
$ecole = $stmt->fetch(); // fetch() au lieu de fetchAll() car on attend 1 résultat
if ($ecole) {
    $nom = $ecole['nom_ecole'];
    $url_ecole = $ecole['url_ecole'];
} else {
    echo "Aucune école trouvée avec le code : " . htmlspecialchars($code_ecole);
}
?>