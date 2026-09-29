<?php
// /login/reset.php
declare(strict_types=1);

ini_set('default_charset','UTF-8');
header('Content-Type: text/html; charset=utf-8');

session_start();
require_once '../database/db_connect.php';

$errors = [];
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token === '') {
    header('Location: forgot.php');
    exit;
}

// Vérifier la validité du token
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = :token AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $reset_request = $stmt->fetch();

    if (!$reset_request) {
        $errors[] = "Le lien de réinitialisation est invalide ou a expiré.";
    }
} catch (Throwable $e) {
    error_log("Reset token check error: ".$e->getMessage());
    $errors[] = "Une erreur est survenue. Veuillez réessayer.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $pdo->beginTransaction();

            // Mettre à jour le mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET PASSWORD = :pwd WHERE id = :uid");
            $stmt->execute([
                ':pwd' => $hashed_password,
                ':uid' => $reset_request['user_id']
            ]);

            // Supprimer le token utilisé
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid");
            $stmt->execute([':uid' => $reset_request['user_id']]);

            $pdo->commit();
            $success = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("Reset password error: ".$e->getMessage());
            $errors[] = "Erreur lors de la réinitialisation. Veuillez réessayer.";
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>MyKelasi | Réinitialisation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .login-box .errors{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:10px;border-radius:4px;margin-bottom:15px;text-align:left}
        .login-box .errors ul{margin:0;padding-left:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:10px;border-radius:4px;margin-bottom:15px;text-align:left}
        .password-wrapper{position:relative}
    </style>
</head>
<body>
<!-- <div id="preloader" class="d-block"></div> -->
<div class="login-page-wrap">
    <div class="login-page-content">
        <div class="login-box">
            <div class="item-logo"><img src="../img/kelasi.png" alt="logo"></div>
            <h3 class="mb-3">Nouveau mot de passe</h3>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="index.php" class="login-btn w-100 d-inline-block text-center text-decoration-none">Se connecter</a>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($errors): ?>
                    <div class="errors">
                        <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <?php if (!$errors || ($_SERVER['REQUEST_METHOD'] === 'POST' && $success === '')): ?>
                    <form method="post" action="reset.php" class="login-form">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="form-group">
                            <label for="password">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Nouveau mot de passe" required minlength="6">
                            <i class="fas fa-lock"></i>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmez le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirmez le mot de passe" required minlength="6">
                            <i class="fas fa-lock"></i>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="login-btn w-100">Réinitialiser</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php endif; ?>

            <div class="mt-3 text-center">
                <a href="index.php" class="text-decoration-none">Retour à la connexion</a>
            </div>
        </div>
    </div>
</div>
<script src="../js/jquery-3.3.1.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/main.js"></script>
</body>
</html>
