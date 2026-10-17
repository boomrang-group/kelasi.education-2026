<?php
// --- Base URL dynamique : évite les chemins relatifs ../../, ../, ../../../ ---
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$rootCandidates = [dirname(__DIR__, 2), dirname(__DIR__), __DIR__, dirname(__DIR__, 3)];
$appRoot = null;

foreach ($rootCandidates as $candidate) {
    if (is_dir($candidate . '/css') && is_dir($candidate . '/img')) {
        $appRoot = realpath($candidate);
        break;
    }
}

$appBaseUrl = '';
if ($appRoot && $documentRoot) {
    $rootPath = str_replace('\\', '/', $appRoot);
    $docPath = rtrim(str_replace('\\', '/', $documentRoot), '/');
    if ($rootPath === $docPath) {
        $appBaseUrl = '';
    } elseif (strpos($rootPath, $docPath . '/') === 0) {
        $appBaseUrl = '/' . trim(substr($rootPath, strlen($docPath)), '/');
    }
}

$appUrl = static function (string $path) use ($appBaseUrl): string {
    return rtrim($appBaseUrl, '/') . '/' . ltrim($path, '/');
};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/normalize.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Main CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/main.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Bootstrap CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Fontawesome CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/all.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Flaticon CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('fonts/flaticon.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Full Calender CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/fullcalendar.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Animate CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('css/animate.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Custom CSS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($appUrl('style.css'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Modernize js -->
<script src="<?php echo htmlspecialchars($appUrl('js/modernizr-3.6.0.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php
// Navbar admin — autonome (aucun require externe), tolérante si variables manquent.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Sessions de base ---
$userId     = $_SESSION['user_id'] ?? null;
$roleRaw    = $_SESSION['role'] ?? '';
$role       = strtolower(trim((string)$roleRaw));
$isAdmin    = in_array($role, ['admin', 'administrateur'], true);

// Infos potentiellement déjà en session (posées au login admin historique)
$nomEcole     = $_SESSION['nom_ecole']           ?? '';
$urlEcole     = $_SESSION['url_ecole']           ?? '';
$nomResp      = $_SESSION['nom_responsable']     ?? '';
$postnomResp  = $_SESSION['postnom_responsable'] ?? '';
$codeEcole    = $_SESSION['code_ecole']          ?? '';

// --- Connexion DB (chemins robustes, sans tuer la page si non trouvé) ---
$pdo = null;
$db_candidates = [
    __DIR__ . '/../../database/db_connect.php',   // admin/layouts -> ../../database
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}
// $pdo doit venir de db_connect.php si existant

// --- Si pas de code_ecole en session, on tente de le lire via users (user_id) ---
if (!$codeEcole && $userId && isset($pdo) && $pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $userId]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) {
            $_SESSION['code_ecole'] = $codeEcole;
        }
    } catch (Throwable $e) {
        // on ignore, navbar reste tolérante
    }
}

// --- Si on a un code_ecole mais pas les infos visibles, on les récupère dans ecoles ---
if ($codeEcole && (!$nomEcole || !$urlEcole) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("
            SELECT nom_ecole, url_ecole, nom_responsable, postnom_responsable
            FROM ecoles
            WHERE code_ecole = :c
            LIMIT 1
        ");
        $st->execute([':c' => $codeEcole]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $nomEcole    = $row['nom_ecole']          ?? $nomEcole;
            $urlEcole    = $row['url_ecole']          ?? $urlEcole;
            $nomResp     = $row['nom_responsable']    ?? $nomResp;
            $postnomResp = $row['postnom_responsable']?? $postnomResp;
            // Optionnel: sauvegarder en session pour les prochaines requêtes
            $_SESSION['nom_ecole']           = $nomEcole;
            $_SESSION['url_ecole']           = $urlEcole;
            $_SESSION['nom_responsable']     = $nomResp;
            $_SESSION['postnom_responsable'] = $postnomResp;
        }
    } catch (Throwable $e) {
        // on ignore
    }
}

// Construire le slug & le lien public
$ecoleSlug  = preg_replace('~[^a-z0-9\-]~i', '', (string)$urlEcole);
$publicLink = $ecoleSlug ? ('https://kelasi.education/@' . $ecoleSlug) : '#';

// Si tu veux VRAIMENT forcer une auth admin ici (sinon on affiche juste "Guest"):
if (!$userId || !$isAdmin) { header('Location: ' . $appUrl('login/index.php')); exit; }

$fullName = trim(($nomResp ?: '') . ' ' . ($postnomResp ?: ''));
?>

<style>
/* ==========================================
   ANIMATIONS DYNAMIQUES NAVBAR & DROPDOWN
   ========================================== */

/* 1. Animation du sous-menu Profil Admin (Déroulement + Fondu) */
.header-admin .dropdown-menu {
    display: block !important;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(15px);
    transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        visibility 0.25s ease !important;
}

/* État actif déclenché par Bootstrap (classe .show) */
.header-admin .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
}

/* 2. Animation au survol du bloc Admin */
.header-admin .navbar-nav-link {
    transition: opacity 0.2s ease;
}

.header-admin .navbar-nav-link:hover {
    opacity: 0.85;
}

.header-admin .admin-img img {
    transition: transform 0.3s ease;
}

.header-admin .navbar-nav-link:hover .admin-img img {
    transform: scale(1.08);
}

/* 3. Animation du Menu Mobile (#mobile-navbar) */
@media (max-width: 767px) {
    #mobile-navbar.collapse {
        display: block !important;
        height: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateY(-10px);
        transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1),
            opacity 0.3s ease,
            transform 0.3s ease !important;
    }

    #mobile-navbar.collapse.show {
        height: auto !important;
        opacity: 1;
        transform: translateY(0);
    }
}

/* 4. Transitions sur les boutons Toggle Sidebar & Burger */
.toggle-button .item-link span {
    transition: background-color 0.25s ease, transform 0.25s ease;
}

.toggle-button .item-link:hover span {
    background-color: #ff6a00;
}
</style>
<div class="navbar navbar-expand-md header-menu-one bg-light">
    <div class="nav-bar-header-one">
        <div class="header-logo">
            <a href="https://kelasi.education" target="black">
                <img src="<?php echo htmlspecialchars($appUrl('img/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="logo">
            </a>
        </div>
        <div class="toggle-button sidebar-toggle">
            <button type="button" class="item-link">
                <span class="btn-icon-wrap"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>

    <div class="d-md-none mobile-nav-bar">
        <button class="navbar-toggler pulse-animation" type="button" data-toggle="collapse" data-target="#mobile-navbar"
            aria-expanded="false">
            <i class="far fa-arrow-alt-circle-down"></i>
        </button>
        <button type="button" class="navbar-toggler sidebar-toggle-mobile">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="header-main-menu collapse navbar-collapse" id="mobile-navbar">
        <ul class="navbar-nav">
            <li class="navbar-item header-search-bar">
                <div class="input-group stylish-input-group">
                    <span class="mt-3">
                        <?php echo $nomEcole ? htmlspecialchars($nomEcole) : 'Guest'; ?>
                        <br>
                        <?php if ($ecoleSlug): ?>
                        <a href="<?php echo htmlspecialchars($publicLink); ?>" target="_blank">
                            kelasi.education/@<?php echo htmlspecialchars($ecoleSlug); ?>
                        </a>
                        <?php endif; ?>
                    </span>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav">
            <li class="navbar-item dropdown header-admin">
                <a class="navbar-nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown"
                    aria-expanded="false">
                    <div class="admin-title">
                        <h5 class="item-title">
                            <?php echo $fullName ? htmlspecialchars($fullName) : 'Admin'; ?>
                        </h5>
                        <span>Administrateur</span>
                    </div>
                    <div class="admin-img">
                        <img src="<?php echo htmlspecialchars($appUrl('img/figure/admin.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="logo admin">
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <div class="d-flex">
                        <div class="item-header">
                            <h6 class="item-title">
                                <?php echo $fullName ? htmlspecialchars($fullName) : 'Utilisateur'; ?>
                            </h6>
                        </div>
                        <div class="item-content">
                            <ul class="settings-list">
                                <li><a href="profil.php"><i class="flaticon-user"></i>Mon profil</a></li>
                                <li><a href="settings.php"><i class="flaticon-gear-loading"></i>Paramètres</a></li>
                                <li><a
                                        href="<?php echo htmlspecialchars($appUrl('login/logout.php?msg=logout'), ENT_QUOTES, 'UTF-8'); ?>"><i
                                            class="flaticon-turn-off"></i>Déconnexion</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </li>
            <!-- Messages/Notifications options (désactivés) -->
        </ul>
    </div>
</div>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/js/all.min.js"
    integrity="sha512-6BTOlkauINO65nLhXhthZMtepgJSghyimIalb+crKRPhvhmsCdnIuGcVbR5/aQY2A+260iC1OPy1oCdB6pSSwQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
</script>

<!-- jquery-->
<!--<script src="../../js/jquery-3.3.1.min.js"></script>-->
<!-- Plugins js -->
<script src="<?php echo htmlspecialchars($appUrl('js/plugins.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Popper js -->
<script src="<?php echo htmlspecialchars($appUrl('js/popper.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Bootstrap js -->
<script src="<?php echo htmlspecialchars($appUrl('js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Counterup Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/jquery.counterup.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Moment Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/moment.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Waypoints Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/jquery.waypoints.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Scroll Up Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/jquery.scrollUp.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Full Calender Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/fullcalendar.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Chart Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/Chart.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Custom Js -->
<script src="<?php echo htmlspecialchars($appUrl('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>