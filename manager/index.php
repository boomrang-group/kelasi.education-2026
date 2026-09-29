<?php
// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once '../database/db_connect.php';

$errors = [];
$login_identifier = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role = $_POST['role'] ?? 1;
    $login_identifier = trim(filter_input(INPUT_POST, 'login_identifier', FILTER_SANITIZE_STRING));
    $password = $_POST['password'];

    if (empty($login_identifier)) {
        $errors[] = "Nom d'utilisateur ou e-mail est requis.";
    }
    if (empty($password)) {
        $errors[] = "Mot de passe est requis.";
    }

    if (empty($errors)) {
        try {
            $field_type = filter_var($login_identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            if ($role == 1) {
                // Promoteur
                $sql = "SELECT id, username, password, first_name, last_name FROM users WHERE $field_type = :identifier";
            } else {
                // Administrateur
                $sql = "SELECT id, username, password, nom_responsable, postnom_responsable, nom_ecole, code_ecole, url_ecole FROM ecoles WHERE $field_type = :identifier";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':identifier', $login_identifier);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                if ($role == 1) {
                    $_SESSION['role'] = 'Promoteur';
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    header("Location: dashboard.php");
                } else {
                    $_SESSION['role'] = 'Administrateur';
                    $_SESSION['code_ecole'] = $user['code_ecole'];
                    $_SESSION['url_ecole'] = $user['url_ecole'];
                    $_SESSION['nom_ecole'] = $user['nom_ecole'];
                    $_SESSION['nom_responsable'] = $user['nom_responsable'];
                    $_SESSION['postnom_responsable'] = $user['postnom_responsable'];
                    header("Location: ../admin/dashboard.php");
                }
                exit;
            } else {
                $errors[] = "Nom d'utilisateur/e-mail ou mot de passe invalide.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = "Erreur lors de la connexion. Veuillez réessayer. Détails : " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>MyKelasi | Connexion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">

    <!-- CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">

    <!-- Modernizr -->
    <script src="../js/modernizr-3.6.0.min.js"></script>

    <style>
    .login-box .errors {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        text-align: left;
    }

    .login-box .errors ul {
        margin: 0;
        padding-left: 20px;
    }

    .password-wrapper {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #aaa;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        text-align: left;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>

    <div class="login-page-wrap">
        <div class="login-page-content">
            <div class="login-box">
                <div class="item-logo">
                    <img src="../img/kelasi.png" alt="logo">
                </div>

                <!-- ✅ Message de succès -->
                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert alert-success">
                    ✅ Inscription réussie ! Vous pouvez maintenant vous connecter.
                </div>
                <?php endif; ?>

                <!-- ❌ Messages d'erreur -->
                <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong>Échec de la connexion :</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="index.php" method="post" class="login-form">
                    <input type="hidden" name="role" value="1" readonly>

                    <div class="form-group">
                        <label>Nom d'utilisateur ou e-mail</label>
                        <input type="text" name="login_identifier" placeholder="Nom d'utilisateur ou e-mail"
                            class="form-control" value="<?php echo htmlspecialchars($login_identifier); ?>" required>
                        <i class="far fa-user"></i>
                    </div>

                    <div class="form-group password-wrapper">
                        <label>Mot de passe</label>
                        <input type="password" name="password" placeholder="Mot de passe" class="form-control"
                            id="password-field" required>
                        <span toggle="#password-field" class="fas fa-eye toggle-password"></span>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="login-btn">Se connecter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

    <!-- JS pour afficher/masquer mot de passe -->
    <script>
    $(document).ready(function() {
        $(".toggle-password").click(function() {
            let input = $("#password-field");
            let type = input.attr("type") === "password" ? "text" : "password";
            input.attr("type", type);
            $(this).toggleClass("fa-eye fa-eye-slash");
        });
    });
    </script>
</body>

</html>
