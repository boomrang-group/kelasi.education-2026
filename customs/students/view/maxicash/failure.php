<?php
// customs/students/view/maxicash/failure.php
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
    <title>Échec du paiement | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../../img/favicon.png">
    <link rel="stylesheet" href="../../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../../style.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; padding: 30px; }
        .icon-failure { font-size: 80px; color: #dc3545; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-failure">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        <h2 class="mb-3">Échec du paiement</h2>
        <p class="lead">Désolé, votre transaction n'a pas pu être traitée.</p>
        <p>Cela peut être dû à un solde insuffisant ou à un problème technique avec le fournisseur de paiement.</p>
        <hr>
        <a href="../paiement.php" class="btn btn-danger btn-lg mt-3">Réessayer le paiement</a>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
