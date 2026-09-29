<?php
// my_school/profil.php
declare(strict_types=1);

/* ===== DEBUG OPTIONNEL (?debug=1) ===== */
 // DEBUG handled in db_connect.php


/* ===== SESSION ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ===== HANDLER D'EXCEPTION (éviter 500 silencieux) ===== */
set_exception_handler(function(Throwable $e){
    http_response_code(500);
    if (DEBUG) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "[profil.php] ".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n";
    } else {
        echo "Une erreur est survenue. Réessaie plus tard ou ouvre avec <code>?debug=1</code> pour le détail.";
    }
    exit;
});

/* ===== HELPERS ===== */
function first_existing(array $paths): ?string { foreach ($paths as $p) if (file_exists($p)) return $p; return null; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function flash_set(string $type, string $msg): void { $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function flash_get(): ?array { if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_token(): string { return $_SESSION['csrf_token'] ?? ''; }
function csrf_ok(?string $t): bool { return is_string($t) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t); }
function csrf_rotate(): void { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

/* ===== CONNEXION PDO (mêmes conventions que le reste de l’app) ===== */
$db_file = first_existing([
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../databases/config.php',
    __DIR__ . '/../../databases/config.php',
]);
if (!$db_file) { throw new RuntimeException("database/db_connect.php introuvable."); }
require_once $db_file;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("Connexion PDO \$pdo non initialisée par $db_file.");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* ===== UTILISATEUR COURANT (cohérent avec ton flux) ===== */
function current_user_from_session(PDO $pdo): ?array {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
        return $_SESSION['user'];
    }
    if (!empty($_SESSION['user_id'])) {
        $id = (int)$_SESSION['user_id'];
        $st = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, phone, numero_bancaire, code_ecole, created_at, updated_at
                               FROM users WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }
    return null;
}
$user = current_user_from_session($pdo);
if (!$user || empty($user['id'])) {
    $loginUrl = '../login/index.php?msg=login_required';
    if (!headers_sent()) { header("Location: $loginUrl"); exit; }
    echo '<p>Non connecté. <a href="'.h($loginUrl).'">Se connecter</a></p>'; exit;
}
$USER_ID = (int)$user['id'];
$ROLE    = strtolower((string)($user['role'] ?? ''));

/* ===== REQUÊTES ===== */
function load_user(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, phone, numero_bancaire, code_ecole, created_at, updated_at
                           FROM users WHERE id = ? LIMIT 1");
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
$profil = load_user($pdo, $USER_ID);
if (!$profil) { throw new RuntimeException("Profil introuvable."); }

/* ===== POST: UPDATE ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException("Jeton CSRF invalide.");
        }

        $username        = trim((string)($_POST['username'] ?? ''));
        $email           = trim((string)($_POST['email'] ?? ''));
        $first_name      = trim((string)($_POST['first_name'] ?? ''));
        $last_name       = trim((string)($_POST['last_name'] ?? ''));
        $phone           = trim((string)($_POST['phone'] ?? ''));
        $numero_bancaire = trim((string)($_POST['numero_bancaire'] ?? ''));
        $new_password    = (string)($_POST['new_password'] ?? '');
        $confirm_password= (string)($_POST['confirm_password'] ?? '');

        if ($username === '' || $email === '') {
            throw new RuntimeException("Le nom d’utilisateur et l’email sont obligatoires.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Adresse email invalide.");
        }
        if ($new_password !== '') {
            if ($new_password !== $confirm_password) {
                throw new RuntimeException("Les deux mots de passe ne correspondent pas.");
            }
            if (strlen($new_password) < 8) {
                throw new RuntimeException("Le mot de passe doit comporter au moins 8 caractères.");
            }
        }
        if (username_taken($pdo, $username, $USER_ID)) {
            throw new RuntimeException("Ce nom d’utilisateur est déjà pris.");
        }
        if (email_taken($pdo, $email, $USER_ID)) {
            throw new RuntimeException("Cet email est déjà utilisé.");
        }

        // Construction UPDATE
        $fields = [
            'username'        => $username,
            'email'           => $email,
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'phone'           => $phone,
            'numero_bancaire' => $numero_bancaire,
        ];
        $sets = []; $vals = [];
        foreach ($fields as $k=>$v) { $sets[] = "`$k` = ?"; $vals[] = $v; }
        if ($new_password !== '') {
            $sets[] = "`PASSWORD` = ?";
            $vals[] = password_hash($new_password, PASSWORD_DEFAULT);
        }
        $vals[] = $USER_ID;

        $sql = "UPDATE users SET ".implode(', ',$sets)." WHERE id = ? LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute($vals);

        csrf_rotate();
        flash_set('success', "Profil mis à jour avec succès.");
        header('Location: profil.php'); exit;

    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
        header('Location: profil.php'); exit;
    }
}

/* ===== RELOAD ===== */
$profil = load_user($pdo, $USER_ID);

/* ===== ÉCOLES DU PROMOTEUR ===== */
$schools = [];
if ($ROLE === 'promoteur') {
    $st = $pdo->prepare("SELECT id, nom_ecole, code_ecole, url_ecole, ville, pays, date_creation
                           FROM ecoles
                          WHERE id_promoteur = ?
                          ORDER BY date_creation DESC");
    $st->execute([$USER_ID]);
    $schools = $st->fetchAll() ?: [];
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Mon profil</title>
    <meta name="description" content="Gestion du profil utilisateur">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">

    <!-- CSS (mêmes que finances.php) -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <!-- Modernizr -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
      .card-box { background:#fff; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,.04); }
      .label-muted{font-size:.85rem; opacity:.75}
      .value-strong{font-weight:600}
      .divider{height:1px;background:#eee;margin:.5rem 0 1rem}
      .badge-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
      .table-sm td, .table-sm th { padding:.5rem .6rem; }
    </style>
</head>

<body>
    <!-- Preloader -->
    <div id="preloader" class="d-none"></div>

    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php
        $nav = __DIR__ . '/layouts/navbar.php';
        if (file_exists($nav)) { require $nav; }
        ?>

        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php
            $side = __DIR__ . '/layouts/sidebar.php';
            if (file_exists($side)) { require $side; }
            ?>

            <div class="dashboard-content-one">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Mon profil</h3>
                </div>

                <!-- Messages flash -->
                <?php if ($f = flash_get()): ?>
                <div class="alert alert-<?php echo h($f['type']); ?>">
                    <?php echo h($f['msg']); ?>
                </div>
                <?php endif; ?>

                <!-- INFOS + BOUTON MODIFIER -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card-box p-4 mb-3">
                            <h5 class="mb-3">Informations</h5>

                            <div class="row gy-2">
                                <div class="col-md-4">
                                    <div class="label-muted">Email</div>
                                    <div class="value-strong"><?php echo h($profil['email'] ?? '—'); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="label-muted">Nom complet</div>
                                    <div class="value-strong">
                                        <?php
                                          $full = trim(($profil['first_name'] ?? '').' '.($profil['last_name'] ?? ''));
                                          echo h($full !== '' ? $full : '—');
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="label-muted">Téléphone</div>
                                    <div class="value-strong"><?php echo h($profil['phone'] ?? '—'); ?></div>
                                </div>
                            </div>

                            <div class="divider"></div>

                            <button id="btn-edit" class="btn btn-md btn-primary">
                                <i class="fa fa-edit me-1"></i> Modifier
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FORMULAIRE (caché par défaut) -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div id="profil-form" class="card-box p-4 mb-4 d-none">
                            <h5 class="mb-3">Mettre à jour mes informations</h5>
                            <form method="post" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom d’utilisateur</label>
                                        <input type="text" name="username" class="form-control" required value="<?php echo h($profil['username']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required value="<?php echo h($profil['email']); ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo h($profil['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Postnom</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo h($profil['last_name'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Téléphone</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo h($profil['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Numéro bancaire</label>
                                        <input type="text" name="numero_bancaire" class="form-control" value="<?php echo h($profil['numero_bancaire'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nouveau mot de passe (optionnel)</label>
                                        <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Laisser vide pour ne pas changer">
                                        <div class="label-muted">8 caractères minimum</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirmer le mot de passe</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="8">
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                                    <button id="btn-cancel" class="btn btn-outline-secondary" type="button">Annuler</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($ROLE === 'promoteur'): ?>
                <!-- Écoles rattachées -->
                <div class="row">
                    <div class="col-12">
                        <div class="card-box p-4">
                            <h5 class="mb-3">Mes écoles rattachées <small class="text-muted">(lecture seule)</small></h5>
                            <?php if (empty($schools)): ?>
                                <p class="text-muted mb-0">Aucune école rattachée pour l’instant.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Code</th>
                                                <th>URL</th>
                                                <th>Ville</th>
                                                <th>Pays</th>
                                                <th>Créée le</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schools as $s): ?>
                                            <tr>
                                                <td><?php echo h($s['nom_ecole']); ?></td>
                                                <td><span class="badge bg-secondary text-light badge-code"><?php echo h($s['code_ecole']); ?></span></td>
                                                <td><?php echo h($s['url_ecole']); ?></td>
                                                <td><?php echo h($s['ville']); ?></td>
                                                <td><?php echo h($s['pays']); ?></td>
                                                <td><?php echo h($s['date_creation']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                $foot = __DIR__ . '/layouts/footer.php';
                if (file_exists($foot)) { require $foot; }
                ?>
            </div>
        </div>
    </div>

    <!-- JS (mêmes que finances.php) -->
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
        const btnEdit  = document.getElementById('btn-edit');
        const btnCancel= document.getElementById('btn-cancel');
        const formCard = document.getElementById('profil-form');

        function showForm(show) {
            if (!formCard) return;
            formCard.classList.toggle('d-none', !show);
            if (btnEdit) btnEdit.innerHTML = show ? '<i class="fa fa-times me-1"></i> Annuler' : '<i class="fa fa-edit me-1"></i> Modifier';
            if (show) { formCard.scrollIntoView({behavior:'smooth', block:'start'}); }
        }

        if (btnEdit) {
            btnEdit.addEventListener('click', function(e){
                e.preventDefault();
                const hidden = formCard.classList.contains('d-none');
                showForm(hidden);
            });
        }
        if (btnCancel) {
            btnCancel.addEventListener('click', function(e){
                e.preventDefault();
                showForm(false);
            });
        }
    })();
    </script>

    <?php if (DEBUG): ?>
    <script>
    console.log('profil', <?php echo json_encode([
        'user'=>['id'=>$USER_ID,'role'=>$ROLE,'email'=>$profil['email'] ?? null],
        'db_file'=>$db_file
    ], JSON_UNESCAPED_UNICODE); ?>);
    </script>
    <?php endif; ?>
</body>
</html>
