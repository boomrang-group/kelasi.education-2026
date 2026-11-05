<?php
// repetitorats/teachers/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/services/dashboard-services.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | <?= h($nomComplet) ?> - Tableau de bord Répétiteur</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../css/fullcalendar.min.css">
    <link rel="stylesheet" href="../../css/animate.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>
    <script src="../../js/Chart.min.js"></script>

    <style>
    .dashboard-summery-one .item-number {
        font-weight: 700;
    }

    .card-box {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 22px;
        margin-bottom: 18px;
    }

    .table-sm td,
    .table-sm th {
        padding: .5rem .6rem;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <!-- Inclusion de la Navbar -->
        <?php require_once(__DIR__ . '/includes/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Inclusion de la Sidebar -->
            <?php require_once(__DIR__ . '/includes/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Tableau de bord Répétiteur</h3>
                    <p class="muted mb-3">Bienvenue dans votre espace : <strong><?= h($nomComplet) ?></strong> | École :
                        <strong><?= h($nomEcole) ?></strong>
                    </p>
                </div>

                <!-- KPIs (Indicateurs clés du Répétiteur) -->
                <div class="row gutters-20">
                    <div class="col-xl-4 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-green">
                                        <i class="flaticon-classmates text-green"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Élèves Encadrés</div>
                                        <div class="item-number"><span><?= (int)($totalEleves ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-blue">
                                        <i class="flaticon-books text-blue"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Cours Publiés</div>
                                        <div class="item-number"><span><?= (int)($totalCours ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-yellow">
                                        <i class="flaticon-checklist text-orange"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Quiz & Évaluations</div>
                                        <div class="item-number"><span><?= (int)($totalQuizzes ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques de synthèse -->
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card-box">
                            <h5 class="mb-3 fw-bold"><i class="bi bi-bar-chart-line text-primary me-2"></i> Vue
                                d'ensemble de l'activité</h5>
                            <div style="position: relative; height: 260px; width: 100%;">
                                <canvas id="tutorBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card-box">
                            <h5 class="mb-3 fw-bold"><i class="bi bi-pie-chart text-warning me-2"></i> Proportion des
                                modules</h5>
                            <div style="position: relative; height: 260px; width: 100%;">
                                <canvas id="tutorDoughnutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
 

    <script>
    (function() {
        const totalEleves = <?= (int)($totalEleves ?? 0) ?>;
        const totalCours = <?= (int)($totalCours ?? 0) ?>;
        const totalQuizzes = <?= (int)($totalQuizzes ?? 0) ?>;

        // Bar Chart
        const elBar = document.getElementById('tutorBarChart');
        if (elBar) {
            new Chart(elBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Élèves Encadrés', 'Cours Publiés', 'Quiz & Évaluations'],
                    datasets: [{
                        label: 'Volume',
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
        }

        // Doughnut Chart
        const elDonut = document.getElementById('tutorDoughnutChart');
        if (elDonut) {
            new Chart(elDonut.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Élèves', 'Cours', 'Quiz'],
                    datasets: [{
                        data: [
                            totalEleves > 0 ? totalEleves : 1,
                            totalCours > 0 ? totalCours : 1,
                            totalQuizzes > 0 ? totalQuizzes : 1
                        ],
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
        }
    })();
    </script>
</body>

</html>