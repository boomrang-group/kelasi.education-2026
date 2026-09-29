<?php
session_start(); // Must be at the very beginning

// // If user is already logged in, redirect to a dashboard page (e.g., index.html for now)
// if (isset($_SESSION['user_id'])) {
//     header("Location: index.php"); // Or dashboard.php if it exists
//     exit;
// }

require_once '../database/db_connect.php';

$errors = [];
$login_identifier = ''; // To retain username/email in form on error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['enseignant']) {
        $login_identifier = trim(filter_input(INPUT_POST, 'login_identifier', FILTER_SANITIZE_STRING));
    $password = $_POST['password']; // Password will be verified, not sanitized as string

    if (empty($login_identifier)) {
        $errors[] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        try {
            // Check if the identifier is an email or username
            $field_type = filter_var($login_identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $sql = "SELECT id, teacher_user_id, class_id, username, code_ecole, password FROM class_subject_teacher WHERE $field_type = :identifier";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':identifier', $login_identifier);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['teacher_user_id'] = $user['teacher_user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['class_id'] = $user['class_id'];
                    $_SESSION['code_ecole'] = $user['code_ecole'];
                    // $_SESSION['last_name'] = $user['last_name'];

                    // Redirect to a protected page (e.g., index.html or a new dashboard.php)
                    header("Location: teacher/view/dashboard.php"); // TODO: Create dashboard.php and redirect there
                    exit;
                } else {
                    $errors[] = "Invalid username/email or password.";
                }
            } else {
                $errors[] = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = "An error occurred during login. Please try again. Details: " . $e->getMessage();
        }
    }
    } else {
       if ($_POST['eleve']) {
        $login_identifier = trim(filter_input(INPUT_POST, 'login_identifier', FILTER_SANITIZE_STRING));
    $password = $_POST['password']; // Password will be verified, not sanitized as string

    if (empty($login_identifier)) {
        $errors[] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        try {
            // Check if the identifier is an email or username
            $field_type = filter_var($login_identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $sql = "SELECT id, username, password, first_name, last_name, class_id, code_ecole FROM students WHERE $field_type = :identifier";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':identifier', $login_identifier);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['class_id'] = $user['class_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['code_ecole'] = $user['code_ecole'];

                    // Redirect to a protected page (e.g., index.html or a new dashboard.php)
                    header("Location: students/view/dashboard.php"); // TODO: Create dashboard.php and redirect there
                    exit;
                } else {
                    $errors[] = "Invalid username/email or password.";
                }
            } else {
                $errors[] = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = "An error occurred during login. Please try again. Details: " . $e->getMessage();
        }
    }} else {
        'Veuillez choissir votre type';
       }
       
    }
    
}
?>
<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Login</title>
    <meta name="description" content="School Management System Login">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- Normalize CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="../css/all.min.css">
    <!-- Flaticon CSS -->
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="../css/animate.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css"> <!-- This is the main style from the template -->
    <!-- Modernize js -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    /* Additional styles for error messages */
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
    </style>
</head>

<body>
    <!-- Preloader Start Here -->
    <div id="preloader" class="d-none"></div>
    <!-- Preloader End Here -->
    <!-- Login Page Start Here -->
    <div class="login-page-wrap">
        <div class="login-page-content">
            <div class="login-box">
                <div class="item-logo">
                    <img src="../img/logo2.png" alt="logo"> <!-- Assuming logo2.png is preferred from login.html -->
                </div>

                <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong>Login Failed:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="index.php" method="post" class="login-form">
                    <div class="form-group">
                        <input type="radio" name="enseignant" id="enseignant"> <span><label
                                for="">Professeur</label></span>
                        <input type="radio" name="eleve" id="eleve"> <span><label for="">Eleve</label></span>
                    </div>
                    <div class="form-group">
                        <label>Nom d'utilisateur ou adresse email</label>
                        <input type="text" name="login_identifier"
                            placeholder="Entrez Nom d'utilisateur ou adresse email" class="form-control"
                            value="<?php echo htmlspecialchars($login_identifier); ?>" required>
                        <i class="far fa-user"></i> <!-- Changed icon to user, more generic -->
                    </div>
                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="password" placeholder="Entrez votre mot de passe"
                            class="form-control" required>
                        <i class="fas fa-lock"></i>
                    </div>
                    <!-- <div class="form-group d-flex align-items-center justify-content-between">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember-me" name="remember_me">
                            <label for="remember-me" class="form-check-label">Remember Me</label>
                        </div>
                        <a href="#" class="forgot-btn">Forgot Password?</a>  
                    </div> -->
                    <div class="form-group">
                        <button type="submit" class="login-btn">Se connecter</button>
                    </div>
                </form>
                <div class="login-social" style="display: none;">
                    <!-- Hidden social login as it's not implemented -->
                    <p>or sign in with</p>
                    <ul>
                        <li><a href="#" class="bg-fb"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="bg-twitter"><i class="fab fa-twitter"></i></a></li>
                        <li><a href="#" class="bg-gplus"><i class="fab fa-google-plus-g"></i></a></li>
                        <li><a href="#" class="bg-git"><i class="fab fa-github"></i></a></li>
                    </ul>
                </div>
            </div>
            <!-- <div class="sign-up">Vous n'avez pas de compte ? <a href="register.php">Inscrivez-vous maintenant !</a></div> -->
        </div>
    </div>
    <!-- Login Page End Here -->
    <!-- jquery-->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <!-- Plugins js -->
    <!-- <script src="../js/plugins.js"></script> Remove or ensure this file exists and is necessary -->
    <!-- Popper js -->
    <script src="../js/popper.min.js"></script>
    <!-- Bootstrap js -->
    <script src="../js/bootstrap.min.js"></script>
    <!-- Scroll Up Js -->
    <script src="../js/jquery.scrollUp.min.js"></script>
    <!-- Custom Js -->
    <script src="../js/main.js"></script>

</body>

</html>