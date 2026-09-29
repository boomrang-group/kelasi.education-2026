<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../database/db_connect.php';

// Sécurité : vérifier que l'utilisateur est un professeur
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    http_response_code(403);
    exit("Non autorisé");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $assignment_id = $_POST['assignment_id'] ?? 0;
    $eleve_id = $_POST['eleve_id'] ?? 0;
    $correction_date = date('Y-m-d');
    $overall_score = $_POST['overall_score'] ?? 0;
    $global_feedback = $_POST['global_feedback'] ?? '';
    $code_ecole = $_SESSION['code_ecole'] ?? '';

    try {
        $sql = "INSERT INTO assignment_corrections (assignment_id, eleve_id, overall_score, global_feedback, correction_date, code_ecole)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$assignment_id, $eleve_id, $overall_score, $global_feedback, $correction_date, $code_ecole]);
        // header("location:../dashboard.php");
        exit;
    } catch (Exception $e) {
        error_log("Error in correction.devoir.service.php: " . $e->getMessage());
        http_response_code(500);
        exit("Erreur lors de l'enregistrement.");
    }
}
?>