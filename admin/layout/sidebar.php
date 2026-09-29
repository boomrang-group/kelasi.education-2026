<?php 
 '../customs/session_check.php'; 
require_once 'service/user_connecter.php';
// protectPage();
?>


<style>
/* --- TRANSITIONS & ANIMATIONS SIDEBAR --- */
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

/* Effet Hover dynamique */
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

/* Animation de sous-menu lorsqu'il se déplie */
.nav-sidebar-menu .sub-group-menu {
    transition: max-height 0.35s ease-in-out, opacity 0.3s ease;
}

/* Animation douce à l'affichage des éléments */
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
            <a href="index.php"><img src="../img/logo.png" alt="logo"></a> <!-- Changed from index.html -->
        </div>
    </div>
    <div class="sidebar-menu-content">
        <ul class="nav nav-sidebar-menu sidebar-toggle-view">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="icon-sidebar fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="all-class.php" class="nav-link">
                    <i class="icon-sidebar fas fa-school"></i>
                    <span>Classes</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="all-teacher.php" class="nav-link">
                    <i class="icon-sidebar fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="all-students.php" class="nav-link">
                    <i class="icon-sidebar fas fa-user-graduate"></i>
                    <span>Élève</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="nos_cours.php" class="nav-link">
                    <i class="icon-sidebar fas fa-book-open"></i>
                    <span>Nos cours</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="paiement.php" class="nav-link">
                    <i class="icon-sidebar fas fa-coins"></i>
                    <span>Finances (Scolarité)</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="profil.php" class="nav-link">
                    <i class="icon-sidebar fas fa-user-circle"></i>
                    <span>Mon profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="icon-sidebar fas fa-cog"></i>
                    <span>Paramétres</span>
                </a>
            </li>

            <li class="d-none nav-item sidebar-nav-item">
                <a href="javascript:void(0);" class="nav-link">
                    <i class="icon-sidebar fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
                <ul class="nav sub-group-menu">
                    <li class="nav-item">
                        <a href="setting-school.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Profil Établissement
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="all-depenses.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Dépenses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="presence_student.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Présence des élèves
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="add-horaires.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Horaires de cours
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Rapports & Stats
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="controle.php" class="nav-link">
                            <i class="fas fa-angle-right"></i> Contrôle FS
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a href="../login/logout.php?msg=logout" class="text-danger nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="text-danger">Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>
</div>