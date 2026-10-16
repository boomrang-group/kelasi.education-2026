<?php
 // error_reporting handled in db_connect.php
 // display_errors handled in db_connect.php
require 'database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Prevent Email Header Injection by stripping newline characters from header fields
    $clean_email = str_replace(["\r", "\n"], '', $email);
    $clean_name = str_replace(["\r", "\n"], '', $name);

    if (!empty($clean_name) && !empty($clean_email) && !empty($message)) {

        if (!filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {
            echo "error_invalid_email";
            exit;
        }

        // ✅ 1. ENVOYER EMAIL
        $to = "cm.chrismbenza@gmail.com"; // 🔴 change ici
        $subject = "Nouveau message de contact";

        $body = "
        Nom: $clean_name\n
        Email: $clean_email\n
        Message:\n$message
        ";

        $headers = "From: $clean_email";

        if (mail($to, $subject, $body, $headers)) {
            echo "success";
        } else {
            echo "error_mail";
        }

    } else {
        echo "error_empty";
    }
}