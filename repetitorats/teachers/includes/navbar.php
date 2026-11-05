<?php
// --- Détection dynamique de la racine ($appUrl) ---
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$rootCandidates = [
    dirname(__DIR__, 3),
    dirname(__DIR__, 2),
    dirname(__DIR__),
    __DIR__
];
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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Session répétiteur
$userId     = $_SESSION['user_id'] ?? null;
$role       = strtolower(trim((string)($_SESSION['role'] ?? '')));
$codeEcole  = $_SESSION['code_ecole'] ?? '';

// Récupération sécurisée du nom de l'utilisateur
$firstName  = $_SESSION['first_name'] ?? '';
$lastName   = $_SESSION['last_name'] ?? '';
$username   = $_SESSION['username'] ?? 'Répétiteur';
$nomComplet = trim($firstName . ' ' . $lastName) ?: $username;

$nomEcole   = $_SESSION['nom_ecole'] ?? 'Raynée International School';
$urlEcole   = $_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international';

// Connexion BDD tolérante
$pdo = null;
$db_candidates = [
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}

if ($codeEcole && (!$nomEcole || !$urlEcole) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $st = $pdo->prepare("SELECT nom_ecole, url_ecole FROM ecoles WHERE code_ecole = :c LIMIT 1");
        $st->execute([':c' => $codeEcole]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $nomEcole = $row['nom_ecole'] ?? $nomEcole;
            $urlEcole = $row['url_ecole'] ?? $urlEcole;
            $_SESSION['nom_ecole'] = $nomEcole;
            $_SESSION['url_ecole'] = $urlEcole;
        }
    } catch (Throwable $e) {}
}

$ecoleSlug  = preg_replace('~[^a-z0-9\-]~i', '', (string)$urlEcole);
$publicLink = $ecoleSlug ? ('https://kelasi.education/@' . $ecoleSlug) : '#';
?>

<!-- Feuilles de styles globales -->
<link rel="stylesheet" href="<?= htmlspecialchars($appUrl('css/normalize.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($appUrl('css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($appUrl('css/all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars($appUrl('css/animate.min.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($appUrl('style.css'), ENT_QUOTES, 'UTF-8') ?>">

<style>
/* Animations Dropdown Profil */
.header-admin .dropdown-menu {
    display: block !important;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(15px);
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease !important;
}

.header-admin .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
}

.topbar-school-name {
    font-weight: 700;
    color: #263238;
    margin: 0;
}

.topbar-school-url {
    font-size: 0.82rem;
    color: #0d6efd;
    text-decoration: none;
}
</style>

<div class="navbar navbar-expand-md header-menu-one bg-light">
    <div class="nav-bar-header-one">
        <div class="header-logo">
            <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($appUrl('img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="logo Kelasi" style="max-height: 45px;">
            </a>
        </div>
        <div class="toggle-button sidebar-toggle">
            <button type="button" class="item-link">
                <span class="btn-icon-wrap"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>

    <div class="d-md-none mobile-nav-bar">
        <button class="navbar-toggler pulse-animation" type="button" data-toggle="collapse" data-target="#mobile-navbar" aria-expanded="false">
            <i class="far fa-arrow-alt-circle-down"></i>
        </button>
        <button type="button" class="navbar-toggler sidebar-toggle-mobile">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="header-main-menu collapse navbar-collapse" id="mobile-navbar">
        <ul class="navbar-nav">
            <li class="navbar-item header-search-bar">
                <div class="input-group stylish-input-group py-2">
                    <div>
                        <h6 class="topbar-school-name"><?= htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8') ?></h6>
                        <?php if ($ecoleSlug): ?>
                        <a href="<?= htmlspecialchars($publicLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="topbar-school-url">
                            <i class="bi bi-globe me-1"></i>kelasi.education/@<?= htmlspecialchars($ecoleSlug, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            <li class="navbar-item dropdown header-admin">
                <a class="navbar-nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
                    <div class="admin-title text-end">
                        <h5 class="item-title mb-0 fw-bold"><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></h5>
                        <span class="badge bg-warning text-dark">Répétiteur</span>
                    </div>
                    <div class="admin-img">
                        <div class="user-avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-user fs-5"></i>
                        </div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-sm border-0 rounded-3 p-2">
                    <ul class="settings-list list-unstyled mb-0">
                        <li><a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/accounts.php'), ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item py-2"><i class="bi bi-person me-2"></i> Mon profil</a></li>
                        <li><a href="<?= htmlspecialchars($appUrl('login/logout.php?msg=logout'), ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item text-danger py-2"><i class="bi bi-box-arrow-right me-2"></i> Déconnexion</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</div>