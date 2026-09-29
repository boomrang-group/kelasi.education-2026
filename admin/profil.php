<?php
// admin/profil.php — Profil de l'administrateur d'école
declare(strict_types=1);

/* ===== DEBUG (optionnel ?debug=1) ===== */
 // DEBUG handled in db_connect.php


/* ===== SESSION ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ===== HELPERS ===== */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function first_existing(array $paths): ?string { foreach ($paths as $p) if (file_exists($p)) return $p; return null; }

function flash_set(string $type, string $msg): void { $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function flash_get(): ?array { if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_token(): string { return $_SESSION['csrf_token'] ?? ''; }
function csrf_ok(?string $t): bool { return is_string($t) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t); }
function csrf_rotate(): void { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

function norm_role(?string $r): string { return strtolower(trim((string)$r)); }

/* ===== AUTH: admin requis ===== */
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = norm_role($_SESSION['role'] ?? '');
if ($userId <= 0 || !in_array($role, ['admin','administrateur'], true)) {
    $login = '../login/index.php?msg=forbidden';
    if (!headers_sent()) { header("Location: $login"); exit; }
    echo '<p>Accès refusé. <a href="'.h($login).'">Se connecter</a></p>'; exit;
}

/* ===== DB (PDO) ===== */
$db_file = first_existing([
    __DIR__.'/../database/db_connect.php',     // admin/ -> ../database
    __DIR__.'/../../database/db_connect.php',  // selon structure
    __DIR__.'/database/db_connect.php',
]);
if (!$db_file) {
    http_response_code(500);
    echo "database/db_connect.php introuvable.";
    exit;
}
require_once $db_file;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Connexion PDO \$pdo non initialisée par $db_file.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* ===== REQUETES ===== */
function load_user(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, phone, numero_bancaire, code_ecole, created_at, updated_at
                           FROM users
                          WHERE id = ?
                          LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function username_taken(PDO $pdo, string $username, int $exceptId): bool {
    $st = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
    $st->execute([$username, $exceptId]);
    return (bool)$st->fetchColumn();
}
function email_taken(PDO $pdo, string $email, int $exceptId): bool {
    $st = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $st->execute([$email, $exceptId]);
    return (bool)$st->fetchColumn();
}

/* ===== CHARGER PROFIL ===== */
$profil = load_user($pdo, $userId);
if (!$profil) {
    http_response_code(404);
    echo "Profil introuvable.";
    exit;
}
$code_ecole = (string)($profil['code_ecole'] ?? $_SESSION['code_ecole'] ?? '');

/* ===== POST: update ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException("Jeton CSRF invalide.");
        }

        $username   = trim((string)($_POST['username'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name  = trim((string)($_POST['last_name'] ?? ''));
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $new_pass   = (string)($_POST['new_password'] ?? '');
        $new_conf   = (string)($_POST['confirm_password'] ?? '');

        if ($username === '' || $email === '') {
            throw new RuntimeException("Le nom d’utilisateur et l’email sont obligatoires.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Adresse email invalide.");
        }
        if ($new_pass !== '') {
            if ($new_pass !== $new_conf) {
                throw new RuntimeException("Les deux mots de passe ne correspondent pas.");
            }
            if (strlen($new_pass) < 8) {
                throw new RuntimeException("Le mot de passe doit comporter au moins 8 caractères.");
            }
        }
        if (username_taken($pdo, $username, $userId)) {
            throw new RuntimeException("Ce nom d’utilisateur est déjà pris.");
        }
        if (email_taken($pdo, $email, $userId)) {
            throw new RuntimeException("Cet email est déjà utilisé.");
        }

        // Construction UPDATE
        $sets = ["`username` = ?", "`email` = ?", "`first_name` = ?", "`last_name` = ?", "`phone` = ?"];
        $vals = [$username, $email, $first_name, $last_name, $phone];

        if ($new_pass !== '') {
            $sets[] = "`PASSWORD` = ?";
            $vals[] = password_hash($new_pass, PASSWORD_DEFAULT);
        }
        $vals[] = $userId;

        $sql = "UPDATE users SET ".implode(', ', $sets).", updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute($vals);

        // Rafraîchir la session (navbar & autres)
        $_SESSION['username']   = $username;
        $_SESSION['email']      = $email;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;

        csrf_rotate();
        flash_set('success', 'Profil mis à jour avec succès.');
        header('Location: profil.php'); exit;

    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
        header('Location: profil.php'); exit;
    }
}

/* ===== RELOAD ===== */
$profil = load_user($pdo, $userId);

/* ===== Charger infos école si dispo ===== */
$ecole = null;
if ($code_ecole !== '') {
    $st = $pdo->prepare("SELECT nom_ecole, url_ecole, ville, pays, province_etat, date_creation
                           FROM ecoles
                          WHERE code_ecole = :ce
                          LIMIT 1");
    $st->execute([':ce'=>$code_ecole]);
    $ecole = $st->fetch() ?: null;
}

/* ===== HTML ===== */
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Mon profil | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">

    <!-- CSS (mêmes familles que dashboard admin) -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
        .card-box { background:#fff; border-radius:12px; box-shadow:0 8px 22px rgba(0,0,0,.06); padding:18px; }
        .form-help { font-size:.85rem; opacity:.8; }
        .badge-code { font-family: ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace; }
        .dashboard-summery-one { border-radius: 12px; box-shadow: 0 8px 22px rgba(0,0,0,.06); padding: 18px }
        .item-icon { display:flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:12px }
        .bg-light-blue{background:#e7f0ff}.text-blue{color:#1e6bd6}
        .item-title{font-weight:600;color:#6b7280}.item-number{font-size:1.05rem;font-weight:600}
        .d-none{display:none!important}
    </style>
</head>
<body>
<div id="preloader" class="d-none"></div>

<div id="wrapper" class="wrapper bg-ash">
    <?php $nav = __DIR__.'/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

    <div class="dashboard-page-one">
        <?php $side = __DIR__.'/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3 style="text-transform: uppercase;">Mon profil</h3>
            </div>

            <!-- Flash -->
            <?php if ($f = flash_get()): ?>
                <div class="alert alert-<?php echo h($f['type']); ?>"><?php echo h($f['msg']); ?></div>
            <?php endif; ?>

            <!-- Résumé infos -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="dashboard-summery-one">
                        <div class="row"> 
                            <div class="col-12 col-md-12">
                                <div class="item-contesnt">
                                    <div class="item-title">Informations</div>
                                    <div class="item-number">
                                        <div>
                                            <?php
                                            $full = trim(($profil['first_name'] ?? '').' '.($profil['last_name'] ?? ''));
                                            echo h($full !== '' ? $full : '—');
                                            ?>
                                        </div>
                                        <div>Email : <?php echo h($profil['email'] ?? '—'); ?></div>
                                        <div>Téléphone : <?php echo h($profil['phone'] ?? '—'); ?></div>
                                        <div>École :
                                            <?php
                                            if ($ecole) {
                                                echo h($ecole['nom_ecole']).' — ';
                                                echo '<span class="badge badge-code bg-secondary text-light">'.h($code_ecole).'</span>';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-primary" id="btnEdit">Modifier</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire (caché par défaut) -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card-box d-none" id="editCard">
                        <h5 class="mb-3">Mettre à jour mes informations</h5>
                        <form method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nom d’utilisateur</label>
                                    <input type="text" name="username" class="form-control" required
                                           value="<?php echo h($profil['username']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?php echo h($profil['email']); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="first_name" class="form-control"
                                           value="<?php echo h($profil['first_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postnom</label>
                                    <input type="text" name="last_name" class="form-control"
                                           value="<?php echo h($profil['last_name'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="phone" class="form-control"
                                           value="<?php echo h($profil['phone'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6 mb-3"></div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nouveau mot de passe (optionnel)</label>
                                    <input type="password" name="new_password" class="form-control" minlength="8"
                                           placeholder="Laisser vide pour ne pas changer">
                                    <div class="form-help">8 caractères minimum</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="8">
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Enregistrer</button>
                                <button class="btn btn-outline-secondary" type="button" id="btnCancel">Annuler</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- École (lecture seule) -->
            <?php if ($ecole): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card-box">
                        <h5 class="mb-3">École rattachée <small class="text-muted">(lecture seule)</small></h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle">
                                <thead><tr>
                                    <th>Nom</th><th>Slug/URL</th><th>Ville</th><th>Pays</th><th>Province/État</th><th>Créée le</th>
                                </tr></thead>
                                <tbody>
                                <tr>
                                    <td><?php echo h($ecole['nom_ecole']); ?></td>
                                    <td><?php echo h($ecole['url_ecole']); ?></td>
                                    <td><?php echo h($ecole['ville']); ?></td>
                                    <td><?php echo h($ecole['pays']); ?></td>
                                    <td><?php echo h($ecole['province_etat']); ?></td>
                                    <td><?php echo h($ecole['date_creation']); ?></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($ecole['url_ecole'])):
                            $slug = preg_replace('~[^a-z0-9\-]~i','',(string)$ecole['url_ecole']);
                            $pub  = $slug ? ('https://kelasi.education/@'.$slug) : '#';
                        ?>
                        <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?php echo h($pub); ?>">
                            Voir la page publique
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php $foot = __DIR__.'/layout/footer.php'; if (file_exists($foot)) require $foot; ?>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../js/jquery-3.3.1.min.js"></script>
<script src="../js/plugins.js"></script>
<script src="../js/popper.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/jquery.counterup.min.js"></script>
<script src="../js/jquery.waypoints.min.js"></script>
<script src="../js/jquery.scrollUp.min.js"></script>
<script src="../js/jquery.dataTables.min.js"></script>
<script src="../js/main.js"></script>

<script>
(function(){
    const btnEdit   = document.getElementById('btnEdit');
    const btnCancel = document.getElementById('btnCancel');
    const editCard  = document.getElementById('editCard');

    function showForm(){ editCard.classList.remove('d-none'); window.scrollTo({ top: editCard.offsetTop-80, behavior: 'smooth' }); }
    function hideForm(){ editCard.classList.add('d-none'); }

    if (btnEdit)   btnEdit.addEventListener('click', showForm);
    if (btnCancel) btnCancel.addEventListener('click', hideForm);

    <?php if (isset($_SESSION['flash']) && $_SESSION['flash']['type'] === 'danger'): ?>
        showForm(); // en cas d'erreur validation, on ré-affiche le formulaire
    <?php endif; ?>
})();
</script>

<?php if (DEBUG): ?>
<script>
console.log('DEBUG profil admin', <?php
echo json_encode([
    'user_id' => $userId,
    'role'    => $role,
    'code_ecole' => $code_ecole,
    'db_file' => $db_file
], JSON_UNESCAPED_UNICODE);
?>);
</script>
<?php endif; ?>
</body>
</html>
