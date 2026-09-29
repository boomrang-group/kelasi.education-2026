<?php
require '../../database/db_connect.php';
include('user_connecter.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_id = $teacher_user_id; //$_POST['sender_id'];
    $sender_type = 'prof';// $_POST['sender_type'];  'admin', 'prof', 'eleve'
    $receiver_id = !empty($_POST['destinataire']) ? $_POST['destinataire'] : null; //!empty($_POST['receiver_id']) ? $_POST['receiver_id'] : null;
    $receiver_type = !empty($_POST['profil']) ? $_POST['profil'] : null; //!empty($_POST['receiver_type']) ? $_POST['receiver_type'] : null;
    $message = $_POST['contenu'];
    // $is_public = 0;
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, sender_type, receiver_id, receiver_type, message, is_public, code_ecole)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sender_id, $sender_type, $receiver_id, $receiver_type, $message, $is_public, $$code_ecole]);

    header('Location: ../view/notice-board.php?msg=created');
    exit;
}
?>