<?php
// /login/index.php
declare(strict_types=1);

// Forcer UTF-8
ini_set('default_charset','UTF-8');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

session_start();

// Déjà connecté ?
if (!empty($_SESSION['role'])) {
    $r = strtolower((string)$_SESSION['role']);
    if ($r === 'admin') {
        header('Location: ../admin/dashboard.php'); exit;
    } elseif ($r === 'manager') {
        header('Location: ../manager/dashboard.php'); exit;
    } elseif ($r === 'promoteur') {
        header('Location: ../my_school/dashboard.php'); exit;
    } elseif ($r === 'prof') {
        header('Location: ../customs/teacher/view/dashboard.php'); exit;
    } elseif ($r === 'eleve') {
        header('Location: ../customs/students/view/dashboard.php'); exit;
    } else {
        header('Location: ../my_school/dashboard.php'); exit;
    }
}

require_once '../database/db_connect.php';

$errors = [];
$success_msg = '';
$login_identifier = '';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Message logout
if (isset($_GET['msg']) && strtolower(trim((string)$_GET['msg'])) === 'logout') {
    $success_msg = "Vous êtes déconnecté(e) avec succès.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Erreur de validation CSRF.";
    }

    $login_identifier = trim((string)($_POST['login_identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($login_identifier === '') $errors[] = "Nom d'utilisateur ou e-mail est requis.";
    if ($password === '')         $errors[] = "Mot de passe est requis.";

    if (!$errors) {
        try {
            // Email ou username ?
            $is_email = filter_var($login_identifier, FILTER_VALIDATE_EMAIL);
            $field_type = $is_email ? 'email' : 'username';

            $sql = "
                SELECT id, username, email, PASSWORD AS password, first_name, last_name, role, code_ecole
                FROM users
                WHERE $field_type = :identifier
                LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':identifier' => $login_identifier]);
            $u = $st->fetch(PDO::FETCH_ASSOC);

            if ($u) {
                $stored = (string)$u['password'];
                $is_legacy = (strlen($stored) < 60 && hash_equals($stored, $password));
                $is_ok = password_verify($password, $stored) || $is_legacy;

                if ($is_ok) {
                    // Init session
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = (int)$u['id'];
                    $_SESSION['username']   = (string)$u['username'];
                    $_SESSION['role']       = strtolower((string)$u['role']);
                    $_SESSION['email']      = $u['email'] ?? null;
                    $_SESSION['first_name'] = $u['first_name'] ?? null;
                    $_SESSION['last_name']  = $u['last_name'] ?? null;
                    $_SESSION['code_ecole'] = $u['code_ecole'] ?? null;

                    $_SESSION['user']       = [
                        'id'         => (int)$u['id'],
                        'username'   => (string)$u['username'],
                        'email'      => $u['email'] ?? null,
                        'first_name' => $u['first_name'] ?? null,
                        'last_name'  => $u['last_name'] ?? null,
                        'role'       => strtolower((string)$u['role']),
                        'code_ecole' => $u['code_ecole'] ?? null,
                    ];

                    // 🟢 [CORRECTION 1] Charger directement les infos de l'école pour la NAVBAR
                    if (!empty($u['code_ecole'])) {
                        try {
                            $stEcole = $pdo->prepare("
                                SELECT nom_ecole, url_ecole, nom_responsable, postnom_responsable 
                                FROM ecoles 
                                WHERE code_ecole = :ce 
                                LIMIT 1
                            ");
                            $stEcole->execute([':ce' => $u['code_ecole']]);
                            if ($ecole = $stEcole->fetch(PDO::FETCH_ASSOC)) {
                                $_SESSION['nom_ecole']           = $ecole['nom_ecole'] ?? '';
                                $_SESSION['url_ecole']           = $ecole['url_ecole'] ?? '';
                                $_SESSION['nom_responsable']     = $ecole['nom_responsable'] ?? '';
                                $_SESSION['postnom_responsable'] = $ecole['postnom_responsable'] ?? '';
                            }
                        } catch (Throwable $e) {}
                    }

                    // If legacy password, force change
                    if ($is_legacy) {
                        $_SESSION['force_password_change'] = true;
                        header('Location: force_reset.php');
                        exit;
                    }

                    // 🟢 [CORRECTION 2] Lier Users → Students & mettre à jour id_user
                    if ($_SESSION['role'] === 'eleve') {
                        try {
                            $st2 = $pdo->prepare("
                                SELECT id, class_id
                                FROM students
                                WHERE (username = :un OR email = :em)
                                  " . (!empty($_SESSION['code_ecole']) ? "AND code_ecole = :ce" : "") . "
                                LIMIT 1
                            ");
                            $params = [':un' => $_SESSION['username'], ':em' => $_SESSION['email']];
                            if (!empty($_SESSION['code_ecole'])) $params[':ce'] = $_SESSION['code_ecole'];
                            
                            $st2->execute($params);

                            if ($stu = $st2->fetch(PDO::FETCH_ASSOC)) {
                                $_SESSION['student_id'] = (int)$stu['id'];
                                $_SESSION['class_id']   = (int)($stu['class_id'] ?? 0);

                                // Mettre à jour la colonne id_user dans students
                                $stUp = $pdo->prepare("UPDATE students SET id_user = :uid WHERE id = :sid");
                                $stUp->execute([':uid' => (int)$u['id'], ':sid' => (int)$stu['id']]);
                            }
                        } catch(Throwable $e){}
                    }

                    // Redirection
                    $r = $_SESSION['role'];
                    if ($r === 'admin') {
                        header('Location: ../admin/dashboard.php'); exit;
                    } elseif ($r === 'promoteur') {
                        header('Location: ../my_school/dashboard.php'); exit;
                    } elseif ($r === 'manager') {
                        header('Location: ../manager/dashboard.php'); exit;
                    } elseif ($r === 'prof') {
                        header('Location: ../customs/teacher/view/dashboard.php'); exit;
                    } else {
                        header('Location: ../customs/students/view/dashboard.php'); exit;
                    }
                }
            }

            $errors[] = "Identifiants invalides.";
        } catch (Throwable $e) {
            error_log("Login error: ".$e->getMessage());
            $errors[] = "Erreur lors de la connexion. Veuillez réessayer.";
        }
    }
}
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Connexion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/js/all.min.js"
        integrity="sha512-6BTOlkauINO65nLhXhthZMtepgJSghyimIalb+crKRPhvhmsCdnIuGcVbR5/aQY2A+260iC1OPy1oCdB6pSSwQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../js/modernizr-3.6.0.min.js"></script>
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

    .password-wrapper {
        position: relative
    }

    .form-check {
        user-select: none
    }

    .text-help {
        font-size: .875rem;
    }
    </style>
</head>

<body>
    <!-- <div id="preloader" class="d-block"></div> -->

    <div class="login-page-wrap">
        <div class="login-page-content">
            <div class="login-box">
                <div class="item-logo"><img src="../img/kelasi.png" alt="logo"></div>

                <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success_msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    <!-- <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer">X</button> -->
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong>échec de la connexion :</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="index.php" method="post" class="login-form" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-group">
                        <label for="login_identifier">Nom d'utilisateur ou e-mail</label>
                        <input type="text" id="login_identifier" name="login_identifier"
                            placeholder="Nom d'utilisateur ou e-mail" class="form-control"
                            value="<?= htmlspecialchars($login_identifier, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            required autocomplete="username">
                    </div>

                    <div class="form-group password-wrapper">
                        <label for="password-field">Mot de passe</label>
                        <input type="password" name="password" id="password-field" placeholder="Mot de passe"
                            class="form-control" required autocomplete="current-password">
                        <!-- Ligne sous le champ : Afficher le mot de passe + Mot de passe oubli�� ? -->
                        <div class="d-flex align-items-center justify-content-between mt-2 flex-wrap gap-2">
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" value="" id="showPasswordChk">
                                <label class="form-check-label" for="showPasswordChk">
                                    Afficher le mot de passe
                                </label>
                            </div>
                            <a href="forgot.php" class="text-decoration-none text-help">Mot de passe oubliée ?</a>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="login-btn w-100">Se connecter</button>
                    </div>
                    <a href="../new_school" class = "nav-link">Vous avez pas un compte ? Créer un compte</a>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Besoin d'aide&nbsp;? Contactez l'administrateur de votre école.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    // Checkbox Show/Hide password
    (function() {
        const chk = document.getElementById('showPasswordChk');
        const input = document.getElementById('password-field');
        if (chk && input) {
            chk.addEventListener('change', function() {
                const isPwd = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPwd ? 'text' : 'password');
            });
        }
    })();
    </script>
</body>

</html>