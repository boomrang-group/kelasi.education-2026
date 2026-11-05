<?php
// Protection de session tolérante
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

// Détection de la page courante pour la classe 'active'
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
<link rel="stylesheet" href="../../../css/normalize.css">
<link rel="stylesheet" href="../../../css/main.css">
<link rel="stylesheet" href="../../../css/bootstrap.min.css">
<link rel="stylesheet" href="../../../css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../../../fonts/flaticon.css">
<link rel="stylesheet" href="../../../css/fullcalendar.min.css">
<link rel="stylesheet" href="../../../css/animate.min.css">
<link rel="stylesheet" href="../../../style.css">
<script src="../../../js/modernizr-3.6.0.min.js"></script>
<script src="../../../js/Chart.min.js"></script>

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
            <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($appUrl('img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="logo">
            </a>
        </div>
    </div>

    <div class="sidebar-menu-content">
        <ul class="nav nav-sidebar-menu sidebar-toggle-view">

            <li class="nav-item <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>

            <li class="nav-item <?= ($currentPage === 'mes_cours_lessons.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/mes_cours_lessons.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-book-open"></i>
                    <span>Mes cours</span>
                </a>
            </li>

            <li class="nav-item <?= (in_array($currentPage, ['students.php', 'eleves.php'], true)) ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/students.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-user-graduate"></i>
                    <span>Mes élèves</span>
                </a>
            </li>

            <li
                class="nav-item <?= (in_array($currentPage, ['mes_quiz.php', 'details_quiz.php'], true)) ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/mes_quiz.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-tasks"></i>
                    <span>Mes quiz</span>
                </a>
            </li>

            <li class="nav-item <?= ($currentPage === 'notes_eleves.php') ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/notes_eleves.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-award"></i>
                    <span>Relevé des cotes</span>
                </a>
            </li>

            <li class="nav-item <?= (in_array($currentPage, ['accounts.php', 'profil.php'], true)) ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($appUrl('repetitorats/teachers/accounts.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link">
                    <i class="icon-sidebar fas fa-user-circle"></i>
                    <span>Mon profil</span>
                </a>
            </li>

            <li class="nav-item mt-3">
                <a href="<?= htmlspecialchars($appUrl('login/logout.php?msg=logout'), ENT_QUOTES, 'UTF-8') ?>"
                    class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt text-danger"></i>
                    <span class="text-danger fw-bold">Déconnexion</span>
                </a>
            </li>

        </ul>
    </div>
</div>


<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/jquery.counterup.min.js"></script>
<script src="../../../js/moment.min.js"></script>
<script src="../../../js/jquery.waypoints.min.js"></script>
<script src="../../../js/jquery.scrollUp.min.js"></script>
<script src="../../../js/fullcalendar.min.js"></script>
<script src="../../../js/Chart.min.js"></script>
<script src="../../../js/main.js"></script>