<?php
// /mykelasi/my_school/dashboard.php

// ========= MODE DEV : afficher les erreurs (à enlever en prod) =========
 // display_errors handled in db_connect.php
 // display_startup_errors handled in db_connect.php
 // error_reporting handled in db_connect.php

require_once 'service/dashboard.service.php';
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Tableau de bord | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon (chemin depuis /my_school/) -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- Normalize CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="../css/all.min.css">
    <!-- Flaticon CSS -->
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="../css/animate.min.css">
    <!-- Data Table CSS -->
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css"><!-- <= CORRIGÉ -->
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Modernize js -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <!-- FA CDN (optionnel) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Chart.js (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
    .dashboard-summery-one {
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 18px
    }

    .item-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 12px
    }

    .bg-light-red {
        background: #ffe5e7
    }

    .text-red {
        color: #e74c3c
    }

    .bg-light-magenta {
        background: #ffe5ff
    }

    .text-magenta {
        color: #9b59b6
    }

    .bg-light-yellow {
        background: #fff5dd
    }

    .text-orange {
        color: #f39c12
    }

    .bg-light-blue {
        background: #e7f0ff
    }

    .text-blue {
        color: #1e6bd6
    }

    .item-title {
        font-weight: 600;
        color: #6b7280
    }

    .item-number {
        font-size: 1.4rem;
        font-weight: 700
    }

    .card-box {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 18px;
        margin-bottom: 18px
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
        <?php require_once('layouts/navbar.php') ?>

        <div class="dashboard-page-one">
            <?php require_once('layouts/sidebar.php') ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Tableau de bord</h3>
                </div>
                <!-- /row KPI centrée -->
                <div class="row justify-content-center text-center">
                    <!-- Élèves -->
                    <div class="col-12 col-sm-3 col-xxxl-3 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-red mb-2">
                                <i class="fas fa-user-graduate text-red fa-2x"></i>
                            </div>
                            <div class="item-title">Élèves</div>
                            <div class="item-number">
                                <span><?= number_format((int)$eleves, 0, ',', ' ') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Enseignants -->
                    <div class="col-12 col-sm-3 col-xxxl-3 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-magenta mb-2">
                                <i class="fas fa-chalkboard-teacher text-magenta fa-2x"></i>
                            </div>
                            <div class="item-title">Enseignants</div>
                            <div class="item-number">
                                <span><?= number_format((int)$profs, 0, ',', ' ') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Classes -->
                    <div class="col-12 col-sm-3 col-xxxl-3 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-yellow mb-2">
                                <i class="fas fa-school text-orange fa-2x"></i>
                            </div>
                            <div class="item-title">Classes</div>
                            <div class="item-number">
                                <span><?= number_format((int)$classes, 0, ',', ' ') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Toutes les écoles -->
                    <div class="col-12 col-sm-3 col-xxxl-3 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-blue mb-2">
                                <i class="fas fa-building text-blue fa-2x"></i>
                            </div>
                            <div class="item-title">Toutes les écoles</div>
                            <div class="item-number">
                                <span><?= number_format((int)$ecoles, 0, ',', ' ') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Situation des écoles -->
                <div class="row justify-content-center text-center mt-3">
                    <div class="col-12 col-sm-3 col-md-4 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-green mb-2">
                                <i class="fas fa-check-circle text-success fa-2x"></i>
                            </div>
                            <div class="item-title">Approuver</div>
                            <div class="item-number">
                                <span><?= $ecoles_par_statut['approuver'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-3 col-md-4 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-yellow mb-2">
                                <i class="fas fa-hourglass-half text-warning fa-2x"></i>
                            </div>
                            <div class="item-title">En attente</div>
                            <div class="item-number">
                                <span><?= $ecoles_par_statut['en attente'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-3 col-md-4 mb-3">
                        <div class="dashboard-summery-one d-flex flex-column align-items-center">
                            <div class="item-icon bg-light-red mb-2">
                                <i class="fas fa-times-circle text-danger fa-2x"></i>
                            </div>
                            <div class="item-title">Non approuver</div>
                            <div class="item-number">
                                <span><?= $ecoles_par_statut['non approuver'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php require_once('layouts/footer.php') ?>
            </div>
        </div>


    </div>

    <!-- JS -->
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
    (function() {
        // Données PHP -> JS
        const labels = <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>;
        const serieIns = <?= json_encode($chart_inscription, JSON_NUMERIC_CHECK) ?>;
        const serieMin = <?= json_encode($chart_minerval, JSON_NUMERIC_CHECK) ?>;
        const serieAut = <?= json_encode($chart_autres, JSON_NUMERIC_CHECK) ?>;

        const topLabels = <?= json_encode($top_ecoles_labels, JSON_UNESCAPED_UNICODE) ?>;
        const topValues = <?= json_encode($top_ecoles_values, JSON_NUMERIC_CHECK) ?>;

        // Chart 1: ligne multi-séries
        const ctx1 = document.getElementById('chart6mois');
        if (ctx1 && labels && labels.length) {
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Inscription',
                            data: serieIns,
                            tension: .3
                        },
                        {
                            label: 'Minerval',
                            data: serieMin,
                            tension: .3
                        },
                        {
                            label: 'Autres',
                            data: serieAut,
                            tension: .3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => '$ ' + v
                            }
                        }
                    }
                }
            });
        }

        // Chart 2: donut répartition
        const ctx2 = document.getElementById('chartDonut');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Inscription', 'Minerval', 'Autres'],
                    datasets: [{
                        data: [
                            <?= json_encode((float)$somme_inscription) ?>,
                            <?= json_encode((float)$somme_minerval) ?>,
                            <?= json_encode((float)$somme_autres) ?>
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Chart 3: bar top écoles
        const ctx3 = document.getElementById('chartTopEcoles');
        if (ctx3 && topLabels && topLabels.length) {
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: topLabels,
                    datasets: [{
                        label: 'Montant',
                        data: topValues
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => '$ ' + v
                            }
                        }
                    }
                }
            });
        }
    })();
    </script>
</body>

</html>