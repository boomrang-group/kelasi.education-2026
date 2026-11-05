<?php
// repetitorats/students/dashboard.php
declare(strict_types=1);
require_once __DIR__ . '/services/dashboard-services.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Espace Élève - Tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>

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
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <!-- Navbar Élève -->
        <?php require_once __DIR__ . '/includes/navbar.php'; ?>

        <div class="dashboard-page-one">
            <!-- Sidebar Élève -->
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">MON ESPACE D'APPRENTISSAGE</h3>
                    <p class="muted mb-3">Bienvenue <strong><?= h($nomComplet) ?></strong> | Établissement :
                        <strong><?= h($nomEcole) ?></strong>
                    </p>
                </div>

                <!-- KPIs Élève -->
                <div class="row gutters-20">
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
                                        <div class="item-title">Cours Disponibles</div>
                                        <div class="item-number"><span><?= (int)$totalCours ?></span></div>
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
                                        <div class="item-title">Quiz À Réaliser</div>
                                        <div class="item-number"><span><?= (int)$totalQuiz ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-sm-6 col-12">
                        <div class="dashboard-summery-one mg-b-20">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <div class="item-icon bg-light-green">
                                        <i class="fas fa-award text-green"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Moyenne Générale</div>
                                        <div class="item-number">
                                            <span><?= $moyenneEleve !== null ? $moyenneEleve . ' / 20' : '—' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Rapides -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-box">
                            <h5 class="fw-bold mb-3"><i class="bi bi-journal-bookmark text-primary me-2"></i> Révisions
                            </h5>
                            <p class="text-muted">Accédez à l'ensemble de vos cours, leçons et fichiers multimédias mis
                                à disposition par votre répétiteur.</p>
                            <a href="mes_cours.php" class="btn btn-primary btn-sm fw-bold"><i
                                    class="bi bi-arrow-right-circle me-1"></i> Consulter mes cours</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-box">
                            <h5 class="fw-bold mb-3"><i class="bi bi-pencil-square text-warning me-2"></i> Évaluations
                            </h5>
                            <p class="text-muted">Répondez à vos questionnaires (QCM / QR) pour évaluer vos
                                connaissances et consulter vos notes.</p>
                            <a href="mes_quiz.php" class="btn btn-warning text-dark btn-sm fw-bold"><i
                                    class="bi bi-check-circle me-1"></i> Accéder aux quiz</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
</body>

</html>