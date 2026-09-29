<?php
require '../database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $gender     = $_POST['gender'];
    $occupation = $_POST['occupation'];
    $email      = $_POST['email'];
    $address    = $_POST['address'];
    $phone      = $_POST['phone'];

    $sql = "INSERT INTO parents (
                first_name, last_name, gender, occupation, email, address, phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$first_name, $last_name, $gender, $occupation, $email, $address, $phone])) {
        header('Location: ../all-parents.php?msg=created');
        exit;
    } else {
        echo "Erreur lors de l’enregistrement.";
    }
}
?>