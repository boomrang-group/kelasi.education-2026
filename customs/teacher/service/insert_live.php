<?php
require '../../database/db_connect.php'; // connexion PDO
require 'user_connecter.php'; // connexion PDO

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cours        = addslashes($_POST['cours']);
    $description  = addslashes(html_entity_decode($_POST['description']));
    $lien         = trim($_POST['lien']);
    $id_professeur = $teacher_user_id; // ou récupéré depuis la session
    $classe       = $class;
    $date_time    = $_POST['date_time']; // format attendu : YYYY-MM-DD HH:MM:SS

    $sql = "INSERT INTO live (
                cours, description, lien, id_professeur, classe, date_time, code_ecole
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $cours, $description, $lien, $id_professeur, $classe, $date_time, $code_ecole
    ]);

    header('Location: ../view/live.php?msg=created');
    exit;
}
?>
