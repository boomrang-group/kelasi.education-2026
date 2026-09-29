<?php
// customs/students/view/maxicash/cancel.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../../login/index.php');
    exit;
}

unset($_SESSION['pending_ref'], $_SESSION['pending_amount'], $_SESSION['pending_type']);

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Paiement Annulé | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../../img/favicon.png">
    <link rel="stylesheet" href="../../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../../style.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; padding: 30px; }
        .icon-cancel { font-size: 80px; color: #ffc107; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-cancel">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h2 class="mb-3">Paiement annulé</h2>
        <p class="lead">Vous avez annulé le processus de paiement.</p>
        <p>Aucun montant n'a été débité de votre compte.</p>
        <hr>
        <a href="../finances.php" class="btn btn-warning btn-lg mt-3">Retour à mes finances</a>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
