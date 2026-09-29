<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Sécurité
$userId  = $_SESSION['user_id'] ?? null;
$role    = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin','administrateur'], true);

if (!$userId) {
    exit("Accès refusé");
}
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Paramètres du Système</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- Assets CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">

    <style>
    /* Grille configurée pour afficher 4 éléments par ligne sur écran standard */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 10px;
    }

    /* Adaptation tablette et mobile */
    @media (max-width: 992px) {
        .settings-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }

    .settings-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 24px 16px 20px;
        text-decoration: none !important;
        color: #2b2d42;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        overflow: hidden;
    }

    .settings-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07);
        border-color: #cbd5e1;
    }

    /* Boîte d'icône en déclinaisons de gris */
    .settings-card .icon-box {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
        background-color: #f1f5f9;
        color: #475569;
        transition: all 0.3s ease;
    }

    .settings-card:hover .icon-box {
        background-color: #e2e8f0;
        color: #0f172a;
    }

    .settings-card .icon-box i {
        font-size: 24px;
        transition: transform 0.3s ease;
    }

    .settings-card:hover .icon-box i {
        transform: scale(1.1);
    }

    /* Titres & Textes */
    .settings-card span.card-title {
        font-weight: 700;
        font-size: 15px;
        color: #1e293b;
        text-align: center;
        margin-bottom: 4px;
    }

    .settings-card span.card-desc {
        font-size: 12px;
        color: #64748b;
        text-align: center;
    }

    /* Flèche discrète au survol */
    .settings-card .action-arrow {
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 11px;
        color: #94a3b8;
        opacity: 0;
        transform: translateX(-5px);
        transition: all 0.3s ease;
    }

    .settings-card:hover .action-arrow {
        opacity: 1;
        transform: translateX(0);
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">

            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">

                <!-- Breadcrumb Section -->
                <!--<div class="breadcrumbs-area d-flex justify-content-between align-items-center flex-wrap mb-4">-->
                <!--    <div>-->
                <!--        <h3>Paramètres du système</h3>-->
                <!--        <ul>-->
                <!--            <li><a href="index.php">Accueil</a></li>-->
                <!--            <li>Configurations</li>-->
                <!--        </ul>-->
                <!--    </div>-->
                <!--</div>-->

                <!-- Main Card Wrapper -->
                <div class="card height-auto mt-4">
                    <div class="card-body">

                        <div class="heading-layout1 mb-2">
                            <div class="item-title">
                                <h3><i class="fas fa-sliders-h mr-2 text-secondary"></i> Centre de Configuration</h3>
                                <p class="text-muted font-14 mb-0">Accédez rapidement aux modules de gestion
                                    administrative et académique.</p>
                            </div>
                        </div>

                        <div class="settings-grid">

                            <!-- DÉPENSES -->
                            <a href="all-depenses.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <span class="card-title">Dépenses</span>
                                <span class="card-desc">Gestion des sorties</span>
                            </a>

                            <!-- PRÉSENCE ÉLÈVES -->
                            <a href="presence_student.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <span class="card-title">Présence élèves</span>
                                <span class="card-desc">Suivi du pointage</span>
                            </a>

                            <!-- HORAIRES -->
                            <a href="add-horaires.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <span class="card-title">Horaires de cours</span>
                                <span class="card-desc">Emplois du temps</span>
                            </a>

                            <!-- RAPPORTS -->
                            <a href="reports.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <span class="card-title">Rapports & Stats</span>
                                <span class="card-desc">Bilan des activités</span>
                            </a>

                            <!-- CONTRÔLE FS -->
                            <a href="controle.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <span class="card-title">Contrôle FS</span>
                                <span class="card-desc">Vérification frais</span>
                            </a>

                            <!-- PARAMÈTRES ÉCOLE -->
                            <a href="setting-school.php" class="settings-card">
                                <i class="fas fa-chevron-right action-arrow"></i>
                                <div class="icon-box">
                                    <i class="fas fa-school"></i>
                                </div>
                                <span class="card-title">Paramètre école</span>
                                <span class="card-desc">Profil établissement</span>
                            </a>

                        </div>

                    </div>
                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>

            </div>
        </div>
    </div>

    <!-- Scripts JS -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

</body>

</html>