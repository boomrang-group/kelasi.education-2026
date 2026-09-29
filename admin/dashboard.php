<?php
require_once 'service/dashboard-admin.php'; // Définit $nbre_classe, $nbre_prof, $nbre_eleve, $finance_total + séries
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | <?= isset($_SESSION['first_name']) ? h($_SESSION['first_name'])."'s " : "" ?>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/fullcalendar.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <!-- Chart.js (déjà présent en local minifié, mais on garde le tien) -->
    <script src="../js/Chart.min.js"></script>

    <style>
    .dashboard-summery-one .item-number {
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
        padding: .5rem .6rem
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Tableau de bord !</h3>
                    <p class="muted mb-3">Bienvenue à l'école : <strong><?= $nomEcole ? h($nomEcole) : '—' ?>
                            (<?= $codeEcole ? h($codeEcole) : '—' ?>)</strong></p>
                </div>

                <!-- KPIs -->
                <div class="row gutters-20">
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-green">
                                        <i class="flaticon-classmates text-green"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Élèves</div>
                                        <div class="item-number"><span><?= (int)($nbre_eleve ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-blue">
                                        <i class="flaticon-multiple-users-silhouette text-blue"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Enseignants</div>
                                        <div class="item-number"><span><?= (int)($nbre_prof ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-yellow">
                                        <i class="flaticon-couple text-orange"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Classes</div>
                                        <div class="item-number"><span><?= (int)($nbre_classe ?? 0) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total général (conserve ton bloc existant) -->
                    <div class="col-xl-3 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-red">
                                        <i class="flaticon-money text-red"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Finances</div>
                                        <div class="item-number">
                                            <span>$</span>
                                            <span><?= number_format((float)($finance_total ?? 0.0), 2, '.', ' ') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ventilation (Inscription / Minerval / Autres / Total) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card-box">
                            <div class="row text-center">
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="item-title">Inscription</div>
                                    <div class="item-number"><span>$</span>
                                        <span><?= number_format((float)($somme_inscription ?? 0.0), 2, '.', ' ') ?></span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="item-title">Minerval</div>
                                    <div class="item-number"><span>$</span>
                                        <span><?= number_format((float)($somme_minerval ?? 0.0), 2, '.', ' ') ?></span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="item-title">Autres</div>
                                    <div class="item-number"><span>$</span>
                                        <span><?= number_format((float)($somme_autres ?? 0.0), 2, '.', ' ') ?></span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="item-title">Total</div>
                                    <div class="item-number"><span>$</span>
                                        <span><?= number_format((float)($finance_total ?? 0.0), 2, '.', ' ') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card-box">
                            <h5 class="mb-3">Encaissements (6 derniers mois)</h5>
                            <canvas id="chart6mois" height="140"></canvas>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card-box">
                            <h5 class="mb-3">Répartition par statut</h5>
                            <canvas id="chartDonut" height="140"></canvas>
                        </div>
                    </div>
                </div>

                <?php require_once('layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.counterup.min.js"></script>
    <script src="../js/moment.min.js"></script>
    <script src="../js/jquery.waypoints.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/fullcalendar.min.js"></script>
    <script src="../js/Chart.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
    (function() {
        const labels = <?= json_encode($chart_labels ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const serieIns = <?= json_encode($chart_inscription ?? [], JSON_NUMERIC_CHECK) ?>;
        const serieMin = <?= json_encode($chart_minerval ?? [], JSON_NUMERIC_CHECK) ?>;
        const serieAut = <?= json_encode($chart_autres ?? [], JSON_NUMERIC_CHECK) ?>;

        const el1 = document.getElementById('chart6mois');
        if (el1 && Array.isArray(labels) && labels.length) {
            new Chart(el1.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Inscription',
                            data: serieIns,
                            fill: false,
                            tension: .3
                        },
                        {
                            label: 'Minerval',
                            data: serieMin,
                            fill: false,
                            tension: .3
                        },
                        {
                            label: 'Autres',
                            data: serieAut,
                            fill: false,
                            tension: .3
                        },
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
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

        const el2 = document.getElementById('chartDonut');
        if (el2) {
            new Chart(el2.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Inscription', 'Minerval', 'Autres'],
                    datasets: [{
                        data: [
                            <?= json_encode((float)($somme_inscription ?? 0.0)) ?>,
                            <?= json_encode((float)($somme_minerval ?? 0.0)) ?>,
                            <?= json_encode((float)($somme_autres ?? 0.0)) ?>
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
    })();
    </script>
</body>

</html>