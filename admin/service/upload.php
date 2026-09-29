<?php
require '../../database/db_connect.php';
require 'user_connecter.php';
require '../../service/security_helpers.php';

csrf_protect();

// Traitement de l'envoi de formulaire vidéo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    // V04: Ne pas utiliser html_entity_decode sur les entrées utilisateur
    $description = $_POST['description'] ?? '';

    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        // V09: Vérification de la taille du fichier (ex: 50 Mo max)
        $maxSize = 50 * 1024 * 1024;
        if ($_FILES['video']['size'] > $maxSize) {
            die("❌ Le fichier est trop volumineux (max 50 Mo).");
        }

        $fileTmp = $_FILES['video']['tmp_name'];
        $fileName = basename($_FILES['video']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'ogg', 'avi', 'mov'];

        if (in_array($fileExt, $allowed)) {
            $uploadDir = '../../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('video_', true) . '.' . $fileExt;
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $pdo->prepare("INSERT INTO videos (title, description, filename, code_ecole) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $newFileName, $code_ecole]);

                header("Location: ../all-video.php?msg=uploaded");
                exit;
            } else {
                echo "❌ Erreur lors de l'enregistrement du fichier.";
            }
        } else {
            echo "⚠️ Format de fichier non autorisé. Types valides : mp4, webm, ogg, avi, mov.";
        }
    } else {
        echo "📎 Veuillez sélectionner un fichier vidéo valide.";
    }
}
?>