<?php
require_once '../service/user_connecter.php';
// $pdo is already provided by user_connecter.php which requires db_connect.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];

    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['video']['tmp_name'];
        $fileName = basename($_FILES['video']['name']);
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $allowed = ['mp4', 'webm', 'ogg', 'avi', 'mov'];

        if (in_array(strtolower($fileExt), $allowed)) {
            $uploadDir = '../../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid() . '.' . $fileExt;
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $pdo->prepare("INSERT INTO videos (title, description, filename, class, code_ecole) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $newFileName, $class, $code_ecole]);

                echo "Vidéo uploadée avec succès !";
            } else {
                echo "Erreur lors de l'enregistrement du fichier.";
            }
        } else {
            echo "Format de fichier non autorisé.";
        }
    } else {
        echo "Erreur lors de l'upload du fichier.";
    }
}
?>