<?php
// repetitorats/teachers/dashboard.php
declare(strict_types=1);

// Inclusion du service backend
require_once __DIR__ . '/services/dashboard-services.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Tableau de bord Répétiteur</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    :root {
        --sidebar-width: 250px;
        --sidebar-bg: #071932;
        --topbar-bg: #ffffff;
        --body-bg: #f4f6f9;
        --primary-orange: #ff9800;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--body-bg);
        margin: 0;
        color: #333;
    }

    /* Top Navbar */
    .navbar-top {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: var(--topbar-bg);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        z-index: 1000;
    }

    .brand-box {
        width: var(--sidebar-width);
        background: var(--primary-orange);
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        left: 0;
        top: 0;
        font-weight: 800;
        font-size: 1.5rem;
        color: #fff;
        text-decoration: none;
        letter-spacing: 0.5px;
    }

    .brand-box span {
        font-size: 0.7rem;
        display: block;
        font-weight: 400;
        text-transform: uppercase;
    }

    .topbar-center {
        margin-left: 270px;
    }

    .topbar-school-name {
        font-weight: 600;
        color: #333;
        font-size: 1rem;
        margin: 0;
    }

    .topbar-school-url {
        color: #0d6efd;
        font-size: 0.85rem;
        text-decoration: none;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-name-title {
        font-weight: 700;
        font-size: 0.95rem;
        margin: 0;
        text-align: right;
    }

    .user-role-title {
        font-size: 0.75rem;
        color: #6c757d;
        margin: 0;
        text-align: right;
    }

    .user-avatar-circle {
        width: 40px;
        height: 40px;
        background: #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        font-size: 1.2rem;
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 70px;
        left: 0;
        width: var(--sidebar-width);
        height: calc(100vh - 70px);
        background: var(--sidebar-bg);
        padding-top: 15px;
        z-index: 999;
        overflow-y: auto;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li a {
        display: flex;
        align-items: center;
        padding: 14px 20px;
        color: #b0bec5;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.2s;
        border-left: 4px solid transparent;
    }

    .sidebar-menu li a i {
        margin-right: 12px;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .sidebar-menu li a:hover,
    .sidebar-menu li.active a {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        border-left-color: var(--primary-orange);
    }

    .sidebar-menu li.logout-item a {
        color: #ef5350;
    }

    .sidebar-menu li.logout-item a:hover {
        background: rgba(239, 83, 80, 0.1);
        border-left-color: #ef5350;
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: 70px;
        padding: 30px;
    }

    .page-title-box h2 {
        font-weight: 800;
        font-size: 1.6rem;
        color: #263238;
        margin-bottom: 5px;
    }

    .page-title-box p {
        color: #546e7a;
        font-size: 0.95rem;
        margin-bottom: 25px;
    }

    /* Stat Cards */
    .stat-card-custom {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 100%;
    }

    .stat-icon-badge {
        width: 65px;
        height: 65px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .bg-icon-green {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .bg-icon-blue {
        background: #e3f2fd;
        color: #1976d2;
    }

    .bg-icon-orange {
        background: #fff8e1;
        color: #f57f17;
    }

    .stat-info {
        text-align: right;
    }

    .stat-info .label {
        font-size: 0.9rem;
        color: #78909c;
        font-weight: 600;
        text-transform: uppercase;
    }

    .stat-info .value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #263238;
        margin: 0;
    }

    /* Chart card */
    .card-custom {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        margin-top: 25px;
    }

    .chart-container {
        position: relative;
        height: 260px;
        width: 100%;
    }
    </style>
</head>

<body>

    <!-- TOP NAVBAR -->
    <header class="navbar-top">
        <a href="dashboard.php" class="brand-box">
            Kelasi <span>Education</span>
        </a>
        <div class="topbar-center">
            <h6 class="topbar-school-name"><?= htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8') ?></h6>
            <a href="#" class="topbar-school-url"><?= htmlspecialchars($urlEcole, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="topbar-right">
            <div>
                <p class="user-name-title"><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="user-role-title">Répétiteur</p>
            </div>
            <div class="user-avatar-circle">
                <i class="fa fa-user"></i>
            </div>
        </div>
    </header>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-title-box">
            <h2>TABLEAU DE BORD !</h2>
            <p>Bienvenue dans votre espace : <strong><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-4">
            <div class="col-md-4">
                <div class="stat-card-custom">
                    <div class="stat-icon-badge bg-icon-green">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-info">
                        <span class="label">Élèves Encadrés</span>
                        <h3 class="value"><?= (int)$totalEleves ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-custom">
                    <div class="stat-icon-badge bg-icon-blue">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div class="stat-info">
                        <span class="label">Cours Publiés</span>
                        <h3 class="value"><?= (int)$totalCours ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-custom">
                    <div class="stat-icon-badge bg-icon-orange">
                        <i class="bi bi-ui-checks-grid"></i>
                    </div>
                    <div class="stat-info">
                        <span class="label">Quiz & Évaluations</span>
                        <h3 class="value"><?= (int)$totalQuizzes ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="row">
            <div class="col-xl-8">
                <div class="card-custom">
                    <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-line text-primary me-2"></i> Activité générale
                    </h5>
                    <div class="chart-container">
                        <canvas id="tutorActivityChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card-custom">
                    <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-warning me-2"></i> Répartition</h5>
                    <div class="chart-container">
                        <canvas id="tutorPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const totalEleves = <?= (int)$totalEleves ?>;
        const totalCours = <?= (int)$totalCours ?>;
        const totalQuizzes = <?= (int)$totalQuizzes ?>;

        // Bar Chart
        const ctxBar = document.getElementById('tutorActivityChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['Élèves Encadrés', 'Cours Publiés', 'Quiz & Évaluations'],
                datasets: [{
                    label: 'Statistiques',
                    data: [totalEleves, totalCours, totalQuizzes],
                    backgroundColor: ['#2e7d32', '#1976d2', '#f57f17'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Doughnut Chart
        const ctxDoughnut = document.getElementById('tutorPieChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['Élèves', 'Cours', 'Quiz'],
                datasets: [{
                    data: [totalEleves || 1, totalCours || 1, totalQuizzes || 1],
                    backgroundColor: ['#2e7d32', '#1976d2', '#f57f17']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
    </script>
</body>

</html>