<?php
$user_connexion = [
    __DIR__ . '/../../service/user_connecter.php',
    __DIR__ . '/../../../service/user_connecter.php',
    __DIR__ . '/../../../../service/user_connecter.php',
    __DIR__ . '/../service/user_connecter.php',
    __DIR__ . '/service/user_connecter.php',
];

foreach ($user_connexion as $usercon) {
    if (file_exists($usercon)) {
        require_once $usercon;
        break;
    }
}

$user_connexion = [
    __DIR__ . '/../../customs/session_check.php',
    __DIR__ . '/../../../customs/session_check.php',
    __DIR__ . '/../../../../customs/session_check.php',
    __DIR__ . '/../customs/session_check.php',
    __DIR__ . '/customs/session_check.php',
];

foreach ($user_connexion as $usercon) {
    if (file_exists($usercon)) {
        require_once $usercon;
        break;
    }
}

// protectPage();

/*
|--------------------------------------------------------------------------
| Détection de la racine de l'application
|--------------------------------------------------------------------------
*/

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

$rootCandidates = [
    dirname(__DIR__, 2),
    dirname(__DIR__),
    __DIR__,
    dirname(__DIR__, 3),
];

$appRoot = null;

foreach ($rootCandidates as $candidate) {
    if (
        is_dir($candidate . '/css') &&
        is_dir($candidate . '/img')
    ) {
        $appRoot = realpath($candidate);
        break;
    }
}

$appBaseUrl = '';

if ($appRoot && $documentRoot) {
    $rootPath = str_replace('\\', '/', $appRoot);
    $docPath = rtrim(
        str_replace('\\', '/', $documentRoot),
        '/'
    );

    if ($rootPath === $docPath) {
        $appBaseUrl = '';
    } elseif (strpos($rootPath, $docPath . '/') === 0) {
        $appBaseUrl = '/' . trim(
            substr($rootPath, strlen($docPath)),
            '/'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Fonction de génération des URL
|--------------------------------------------------------------------------
*/

$appUrl = static function (string $path) use ($appBaseUrl): string {
    return rtrim($appBaseUrl, '/') . '/' . ltrim($path, '/');
};
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
}

.nav-sidebar-menu .nav-link:hover {
    background-color: rgba(255, 106, 0, 0.12);
    transform: translateX(6px);
}

.nav-sidebar-menu .nav-link:hover .icon-sidebar {
    color: #ff6a00;
    transform: scale(1.15);
}

.nav-sidebar-menu .nav-link:hover span {
    color: #ff6a00;
}

.nav-sidebar-menu .sub-group-menu {
    transition: max-height 0.35s ease-in-out, opacity 0.3s ease;
}

.nav-sidebar-menu .nav-item {
    animation: fadeInSidebar 0.4s ease forwards;
}

@keyframes fadeInSidebar {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>

<div class="sidebar-main sidebar-menu-one sidebar-expand-md sidebar-color">

    <div class="mobile-sidebar-header d-md-none">
        <div class="header-logo">
            <a href="<?= htmlspecialchars($appUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($appUrl('img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="logo">
            </a>
        </div>
    </div>

    <div class="sidebar-menu-content">
        <ul class="nav nav-sidebar-menu sidebar-toggle-view">

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/all-class.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-school"></i>
                    <span>Classes</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/all-teacher.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/all-students.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-user-graduate"></i>
                    <span>Élève</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/nos_cours.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-book-open"></i>
                    <span>Nos cours</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/paiement.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-coins"></i>
                    <span>Finances (Scolarité)</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/profil.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-user-circle"></i>
                    <span>Mon profil</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('/admin/settings.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                    <i class="icon-sidebar fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>

            <li class="d-none nav-item sidebar-nav-item">
                <a href="javascript:void(0);" class="nav-link">
                    <i class="icon-sidebar fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>

                <ul class="nav sub-group-menu">

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('setting-school.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Profil Établissement
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('all-depenses.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Dépenses
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('presence_student.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Présence des élèves
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('add-horaires.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Horaires de cours
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('reports.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Rapports &amp; Stats
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($appUrl('controle.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link">
                            <i class="fas fa-angle-right"></i>
                            Contrôle FS
                        </a>
                    </li>

                </ul>
            </li>

            <li class="nav-item">
                <a href="<?= htmlspecialchars($appUrl('login/logout.php?msg=logout'), ENT_QUOTES, 'UTF-8') ?>"
                    class="text-danger nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="text-danger">Déconnexion</span>
                </a>
            </li>

        </ul>
    </div>
</div>