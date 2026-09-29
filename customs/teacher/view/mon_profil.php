<?php
// customs/teacher/view/mon_profil.php
// Édition des informations personnelles + changement de mot de passe pour l'utilisateur connecté.

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';

// ---- Accès : besoin d'être connecté, peu importe le rôle ----
if (empty($_SESSION['user_id'])) {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId     = (int)$_SESSION['user_id'];
$role       = strtolower((string)($_SESSION['role'] ?? ''));
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---------- Helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s){ return trim((string)$s); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(string $t): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// ---------- Charger infos utilisateur ----------
function loadUser(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, numero_bancaire, PASSWORD FROM users WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

$errors = [];
$success = '';
$user = loadUser($pdo, $userId);
if (!$user) {
    $errors[] = "Utilisateur introuvable.";
}

// ---------- POST : mise à jour ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $errors[] = "CSRF token invalide.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_profile') {
            $first_name      = clean($_POST['first_name'] ?? '');
            $last_name       = clean($_POST['last_name'] ?? '');
            $email           = clean($_POST['email'] ?? '');
            $phone           = clean($_POST['phone'] ?? '');
            $username        = clean($_POST['username'] ?? '');
            $numero_bancaire = clean($_POST['numero_bancaire'] ?? '');

            // validations simples
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "E-mail invalide.";
            }
            if ($username === '') {
                $errors[] = "Le nom d'utilisateur est requis.";
            }
            // unicité email / username (hors soi)
            if (!$errors) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id");
                $st->execute([':e'=>$email, ':id'=>$userId]);
                if ((int)$st->fetchColumn() > 0) $errors[] = "Cet e-mail est déjà utilisé.";

                $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u AND id <> :id");
                $st->execute([':u'=>$username, ':id'=>$userId]);
                if ((int)$st->fetchColumn() > 0) $errors[] = "Ce nom d'utilisateur est déjà utilisé.";
            }

            if (!$errors) {
                try {
                    $up = $pdo->prepare("
                        UPDATE users
                           SET first_name=:fn, last_name=:ln, email=:em, phone=:ph, username=:un, numero_bancaire=:nb
                         WHERE id=:id
                        LIMIT 1
                    ");
                    $up->execute([
                        ':fn'=>$first_name !== '' ? $first_name : null,
                        ':ln'=>$last_name  !== '' ? $last_name  : null,
                        ':em'=>$email,
                        ':ph'=>$phone !== '' ? $phone : null,
                        ':un'=>$username,
                        ':nb'=>$numero_bancaire !== '' ? $numero_bancaire : null,
                        ':id'=>$userId
                    ]);

                    // rafraîchir session utile
                    $_SESSION['username'] = $username;
                    $_SESSION['email']    = $email;
                    $_SESSION['first_name'] = $first_name !== '' ? $first_name : null;
                    $_SESSION['last_name']  = $last_name  !== '' ? $last_name  : null;

                    $success = "Profil mis à jour avec succès.";
                    $user = loadUser($pdo, $userId);
                } catch (Throwable $e) {
                    $errors[] = "Erreur lors de la mise à jour du profil.";
                    if ($DEBUG) $errors[] = e($e->getMessage());
                }
            }
        }
        elseif ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($current === '' || $new === '' || $confirm === '') {
                $errors[] = "Tous les champs du mot de passe sont requis.";
            }
            if (strlen($new) < 8) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
            }
            if ($new !== $confirm) {
                $errors[] = "La confirmation ne correspond pas.";
            }

            // vérifier l'ancien mot de passe (hash moderne + compat clair)
            if (!$errors) {
                $stored = (string)($user['PASSWORD'] ?? '');
                $ok = password_verify($current, $stored) || (strlen($stored) < 60 && hash_equals($stored, $current));
                if (!$ok) {
                    $errors[] = "Ancien mot de passe incorrect.";
                }
            }

            if (!$errors) {
                try {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    $st = $pdo->prepare("UPDATE users SET PASSWORD=:p, updated_at=NOW() WHERE id=:id LIMIT 1");
                    $st->execute([':p'=>$hash, ':id'=>$userId]);
                    $success = "Mot de passe mis à jour avec succès.";
                    // recharger l'utilisateur pour éviter d'utiliser une valeur obsolète
                    $user = loadUser($pdo, $userId);
                } catch (Throwable $e) {
                    $errors[] = "Erreur lors de la mise à jour du mot de passe.";
                    if ($DEBUG) $errors[] = e($e->getMessage());
                }
            }
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mon profil | Paramètres du compte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <style>
        .card{border:0;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        .muted{color:#6b7280}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3>Mon profil</h3>
                <ul>
                    <li>Compte</li>
                    <li><?= e(strtoupper($role)) ?></li>
                </ul>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <strong>Veuillez corriger :</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $er): ?>
                            <li><?= e($er) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Infos personnelles -->
                <div class="col-xl-12 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Informations personnelles</h5>
                            <form method="post" action="">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Prénom</label>
                                        <input type="text" class="form-control" name="first_name" value="<?= e($user['first_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Nom</label>
                                        <input type="text" class="form-control" name="last_name" value="<?= e($user['last_name'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Nom d'utilisateur <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="username" required value="<?= e($user['username'] ?? '') ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>E-mail <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" required value="<?= e($user['email'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Téléphone</label>
                                        <input type="text" class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Numéro bancaire</label>
                                        <input type="text" class="form-control" name="numero_bancaire" value="<?= e($user['numero_bancaire'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                                    <a class="btn btn-outline-secondary" href="../view/mes_cours.php">Retour</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Mot de passe -->
                <div class="d-none col-xl-5 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Changer le mot de passe</h5>
                            <form method="post" action="" autocomplete="off">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="form-group">
                                    <label>Mot de passe actuel</label>
                                    <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                                </div>
                                <div class="form-group">
                                    <label>Nouveau mot de passe</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="8" autocomplete="new-password">
                                    <small class="muted">Au moins 8 caractères.</small>
                                </div>
                                <div class="form-group">
                                    <label>Confirmer le nouveau mot de passe</label>
                                    <input type="password" class="form-control" name="confirm_password" required minlength="8" autocomplete="new-password">
                                </div>

                                <div class="form-group">
                                    <button class="btn btn-warning" type="submit">Mettre à jour le mot de passe</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="mt-2">
                        <small class="muted">École : <?= e($code_ecole ?? '—') ?> • Rôle : <?= e($role ?: '—') ?></small>
                    </div>
                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/main.js"></script>
</body>
</html>
