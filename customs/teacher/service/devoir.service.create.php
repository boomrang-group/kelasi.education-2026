<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// Connexion à la base de données via le fichier central
require_once '../../../database/db_connect.php';

// Sécurité : vérifier que l'utilisateur est un professeur
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    http_response_code(403);
    exit("Non autorisé");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = date('Y-m-d');
    $noteDevoir = $_POST['noteDevoir'] ?? 0;
    $classe = $_POST['classe'] ?? 0;
    $professor_id = $_SESSION['user_id'] ?? 0;
    $code_ecole = $_SESSION['code_ecole'] ?? '';

    $type_travail = '';
    if (isset($_POST['devoir']))        $type_travail = 'Devoir';
    elseif (isset($_POST['interrogation'])) $type_travail = 'Interrogation';
    elseif (isset($_POST['examen']))        $type_travail = 'Examen';
    elseif (isset($_POST['exercice']))      $type_travail = 'Exercice';

    if ($type_travail !== '') {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO assignments (title, description, date_due, classe, professor_id, overall_score, type_travail_pratique, code_ecole)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $description, $due_date, $classe, $professor_id, $noteDevoir, $type_travail, $code_ecole]);

            $assignment_id = $pdo->lastInsertId();

            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                $sql2 = "INSERT INTO questions (assignment_id, question_text, code_ecole) VALUES (?, ?, ?)";
                $stmt2 = $pdo->prepare($sql2);
                foreach ($_POST['questions'] as $question) {
                    if (trim((string)$question) !== '') {
                        $stmt2->execute([$assignment_id, $question, $code_ecole]);
                    }
                }
            }

            $pdo->commit();
            echo "Devoir envoyé avec succès!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error in devoir.service.create.php: " . $e->getMessage());
            echo "Erreur lors de l'enregistrement.";
        }
    } else {
        echo "Type de travail non spécifié.";
    }
}
?>