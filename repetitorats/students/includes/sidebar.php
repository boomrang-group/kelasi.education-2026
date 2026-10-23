<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>

<style>
.sidebar-main {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.icon-sidebar {
    color: #ffffff;
    transition: transform 0.25s ease, color 0.25s ease;
}

.nav-sidebar-menu .nav-link {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #b0bec5;
    font-weight: 500;
}

.nav-sidebar-menu .nav-link:hover,
.nav-sidebar-menu .nav-item.active .nav-link {
    background-color: rgba(255, 106, 0, 0.12);
    color: #ffffff;
    border-left: 4px solid #ff6a00;
}

.nav-sidebar-menu .nav-link:hover .icon-sidebar,
.nav-sidebar-menu .nav-item.active .icon-sidebar {
    color: #ff6a00;
    transform: scale(1.15);
}

.nav-sidebar-menu .nav-link span {
    margin-left: 12px;
}
</style>

<div class="sidebar-main sidebar-menu-one sidebar-expand-md sidebar-color">

    <div class="mobile-sidebar-header d-md-none">
        <div class="header-logo">
            <a href="<?= htmlspecialchars($appUrl('repetitorats/students/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($appUrl('img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="logo">
            </a>
        </div>
    </div>

    <div class="sidebar-menu-content">
        <ul class="nav nav-sidebar-menu sidebar-toggle-view">

            <li class="nav-item <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/students/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>

            <li class="nav-item <?= ($currentPage === 'mes_cours.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/students/mes_cours.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-book-open"></i>
                    <span>Mes cours</span>
                </a>
            </li>

            <li
                class="nav-item <?= (in_array($currentPage, ['mes_quiz.php', 'passer_quiz.php'], true)) ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/students/mes_quiz.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-tasks"></i>
                    <span>Mes quiz & évaluations</span>
                </a>
            </li>

            <li class="nav-item <?= ($currentPage === 'mes_notes.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/students/mes_notes.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-award"></i>
                    <span>Mes notes & bulletins</span>
                </a>
            </li>

            <li class="nav-item mt-3">
                <a href="<?= htmlspecialchars($appUrl('login/logout.php?msg=logout'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link text-danger">
                    <i class="icon-sidebar fas fa-sign-out-alt text-danger"></i>
                    <span class="text-danger fw-bold">Déconnexion</span>
                </a>
            </li>

        </ul>
    </div>
</div>