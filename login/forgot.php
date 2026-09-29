<?php
// /login/forgot.php
declare(strict_types=1);

ini_set('default_charset','UTF-8');
header('Content-Type: text/html; charset=utf-8');

session_start();
require_once '../database/db_connect.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $errors[] = "Veuillez entrer votre e-mail ou nom d'utilisateur.";
    } else {
        try {
            // Vérifier si user existe
            $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
            $field = $is_email ? 'email' : 'username';

            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE $field = :id LIMIT 1");
            $stmt->execute([':id' => $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                // Générer token
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                // Supprimer anciens tokens de ce user
                $pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid")->execute([':uid' => $user['id']]);

                // Sauvegarder nouveau
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, :exp)");
                $stmt->execute([
                    ':uid' => $user['id'],
                    ':token' => $token,
                    ':exp' => $expires
                ]);

                // Préparer lien
                $reset_link = "https://".$_SERVER['HTTP_HOST']."/login/reset.php?token=".$token;

                // Envoi de l'e-mail
                $to = $user['email'];
                $subject = "Réinitialisation de votre mot de passe - Kelasi";
                $message = "Bonjour,\n\n";
                $message .= "Vous avez demandé la réinitialisation de votre mot de passe sur Kelasi.\n";
                $message .= "Veuillez cliquer sur le lien ci-dessous pour choisir un nouveau mot de passe :\n";
                $message .= $reset_link . "\n\n";
                $message .= "Ce lien est valable pendant 1 heure.\n";
                $message .= "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.\n\n";
                $message .= "L'équipe Kelasi";

                $headers = "From: Kelasi <no-reply@kelasi.education>\r\n";
                $headers .= "Reply-To: support@kelasi.education\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    $success = "Un e-mail de réinitialisation a été envoyé à votre adresse.";
                } else {
                    $errors[] = "Erreur lors de l'envoi de l'e-mail. Veuillez contacter l'administrateur.";
                }
            } else {
                $errors[] = "Aucun compte trouvé pour cet identifiant.";
            }
        } catch (Throwable $e) {
            error_log("Forgot error: ".$e->getMessage());
            $errors[] = "Erreur lors de la demande. Veuillez réessayer.";
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Mot de passe oublié</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
    .login-box .errors {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        text-align: left
    }

    .login-box .errors ul {
        margin: 0;
        padding-left: 20px
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        text-align: left
    }
    </style>
</head>

<body>
    <!-- <div id="preloader" class="d-block"></div> -->
    <div class="login-page-wrap">
        <div class="login-page-content">
            <div class="login-box">
                <div class="item-logo"><img src="../img/kelasi.png" alt="logo"></div>
                <h3 class="mb-3">Mot de passe oublié</h3>

                <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                <div class="errors">
                    <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <form method="post" action="forgot.php" class="login-form">
                    <div class="form-group">
                        <label for="identifier">E-mail ou Nom d'utilisateur</label>
                        <input type="text" id="identifier" name="identifier" class="form-control"
                            placeholder="E-mail ou Nom d'utilisateur" required>
                        <i class="far fa-user"></i>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="login-btn w-100">Envoyer le lien</button>
                    </div>
                </form>

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