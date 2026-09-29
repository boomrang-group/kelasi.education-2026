<?php
require '../../database/db_connect.php';
require 'user_connecter.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = html_entity_decode($_POST['description'] ?? '');

    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['pdf']['tmp_name'];
        $fileName = basename($_FILES['pdf']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExt === 'pdf') {
            $uploadDir = '../../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('doc_', true) . '.pdf';
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $pdo->prepare("INSERT INTO pdfs (title, description, filename, code_ecole) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $newFileName, $code_ecole]);

                header("Location: ../all-documents-pdf.php?msg=uploaded");
                exit;
            } else {
                echo "❌ Erreur lors de l'enregistrement du fichier.";
            }
        } else {
            echo "⚠️ Seuls les fichiers PDF sont autorisés.";
        }
    } else {
        echo "📎 Veuillez sélectionner un fichier PDF valide.";
    }
}
?>