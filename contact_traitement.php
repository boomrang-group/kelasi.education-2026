<?php
 // error_reporting handled in db_connect.php
 // display_errors handled in db_connect.php
require 'database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (!empty($name) && !empty($email) && !empty($message)) {

        // ✅ 1. ENVOYER EMAIL
        $to = "cm.chrismbenza@gmail.com"; // 🔴 change ici
        $subject = "Nouveau message de contact";

        $body = "
        Nom: $name\n
        Email: $email\n
        Message:\n$message
        ";

        $headers = "From: $email";

        if (mail($to, $subject, $body, $headers)) {
            echo "success";
        } else {
            echo "error_mail";
        }

    } else {
        echo "error_empty";
    }
}