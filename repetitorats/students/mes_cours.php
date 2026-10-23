<?php
// repetitorats/students/mes_cours.php
declare(strict_types=1);
require_once __DIR__ . '/services/cours-services.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Mes Cours & Leçons</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>

    <style>
    .card-box {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 22px;
        margin-bottom: 20px;
    }

    .table th {
        font-weight: 600;
        color: #37474f;
        background-color: #f8f9fa;
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
                    <h3 style="text-transform: uppercase;">MES COURS & LEÇONS</h3>
                    <p class="muted mb-3">Consultez vos supports de cours et leçons mis à disposition par votre
                        répétiteur.</p>
                </div>

                <div class="card-box">
                    <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>
                        Liste des leçons disponibles</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Matière / Cours</th>
                                    <th>Titre de la leçon</th>
                                    <th>Support / Pièce jointe</th>
                                    <th>Date de publication</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mesCours)): foreach ($mesCours as $idx => $c): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><span class="badge bg-primary fs-6"><?= h($c['cours_nom']) ?></span></td>
                                    <td><strong><?= h($c['lecon_titre']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($c['type_contenu'])): 
                                            $icon = 'bi-file-earmark';
                                            if ($c['type_contenu'] === 'pdf') $icon = 'bi-file-earmark-pdf text-danger';
                                            elseif ($c['type_contenu'] === 'image') $icon = 'bi-file-earmark-image text-success';
                                            elseif ($c['type_contenu'] === 'audio') $icon = 'bi-file-earmark-music text-warning';
                                            elseif ($c['type_contenu'] === 'video') $icon = 'bi-file-earmark-play text-info';
                                        ?>
                                        <span class="text-uppercase fw-bold"><i class="bi <?= $icon ?> me-1 fs-5"></i>
                                            <?= h($c['type_contenu']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted small">Texte simple</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($c['lecon_date']) ?></td>
                                    <td class="text-end">
                                        <!-- Redirection vers reviser-cours.php avec l'ID de la leçon -->
                                        <a href="reviser-cours.php?id=<?= (int)$c['lecon_id'] ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Réviser
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Aucun cours disponible pour le
                                        moment.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>