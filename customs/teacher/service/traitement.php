<?php
   require_once '../service/user_connecter.php';
   // $pdo is already provided by user_connecter.php which requires db_connect.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];

    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['pdf']['tmp_name'];
        $fileName = basename($_FILES['pdf']['name']);
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);

        if (strtolower($fileExt) === 'pdf') {
            $uploadDir = '../../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid() . '.pdf';
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $pdo->prepare("INSERT INTO pdfs (title, description, filename, class, code_ecole) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $newFileName, $class, $code_ecole]);

                echo "Document PDF uploadé avec succès ! <a href='all-documents-pdf.php'>Voir les PDF</a>";
            } else {
                echo "Erreur lors de l'enregistrement du fichier.";
            }
        } else {
            echo "Seuls les fichiers PDF sont autorisés.";
        }
    } else {
        echo "Erreur lors de l'upload du fichier.";
    }
}
?>