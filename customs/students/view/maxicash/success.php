<?php
// customs/students/view/maxicash/success.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../../login/index.php');
    exit;
}

// In a real scenario, we might want to check the transaction status here
// via MaxiCash API or wait for the notifyurl to update the database.

$ref = $_GET['reference'] ?? $_SESSION['pending_ref'] ?? 'N/A';
unset($_SESSION['pending_ref'], $_SESSION['pending_amount'], $_SESSION['pending_type']);

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Paiement Réussi | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../../img/favicon.png">
    <link rel="stylesheet" href="../../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../../style.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; padding: 30px; }
        .icon-success { font-size: 80px; color: #28a745; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h2 class="mb-3">Merci !</h2>
        <p class="lead">Votre paiement a été traité avec succès.</p>
        <p class="text-muted">Référence de la transaction : <strong><?= htmlspecialchars($ref) ?></strong></p>
        <hr>
        <p>Il se peut que le solde de votre compte mette quelques instants à se mettre à jour.</p>
        <a href="../finances.php" class="btn btn-primary btn-lg mt-3">Retour à mes finances</a>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
