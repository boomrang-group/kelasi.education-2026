<?php
// repetitorats/students/mes_notes.php
declare(strict_types=1);
require_once __DIR__ . '/services/notes-services.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Mes Notes & Bulletins</title>
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

    .stats-card {
        background: linear-gradient(135deg, #071932 0%, #1a365d 100%);
        color: white;
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 25px;
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
                    <h3 style="text-transform: uppercase;">MES NOTES & BULLETINS</h3>
                    <p class="muted mb-3">Consultez vos cotes obtenues aux quiz et suivez votre moyenne générale.</p>
                </div>

                <!-- Carte de résumé de la moyenne -->
                <div class="stats-card row align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h4 class="text-white fw-bold mb-1"><i class="bi bi-award-fill text-warning me-2"></i>
                            Récapitulatif global</h4>
                        <p class="text-white-50 mb-0">Évaluations réalisées :
                            <strong><?= (int)$totalEvaluations ?></strong></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="d-block text-white-50 small text-uppercase">Moyenne Générale</span>
                        <h2 class="fw-bold text-warning mb-0">
                            <?= $moyenneGenerale !== null ? $moyenneGenerale . ' / 20' : '— / 20' ?>
                        </h2>
                    </div>
                </div>

                <!-- Tableau détaillé des notes -->
                <div class="card-box">
                    <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-journal-check text-primary me-2"></i> Détails de
                        vos notes par évaluation</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre de l'évaluation</th>
                                    <th>Cours / Matière</th>
                                    <th>Format</th>
                                    <th>Note Obtenue</th>
                                    <th>Date de réalisation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mesNotes)): foreach ($mesNotes as $idx => $n): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= h($n['quiz_titre']) ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= h($n['cours_nom'] ?? 'Général') ?></span>
                                    </td>
                                    <td><span
                                            class="badge bg-info text-dark text-uppercase"><?= h($n['type_quiz']) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $note = (float)($n['note_obtenue'] ?? 0);
                                        $badgeColor = $note >= 10 ? 'bg-success' : 'bg-danger';
                                        if ($note >= 14) $badgeColor = 'bg-primary';
                                        ?>
                                        <span class="badge <?= $badgeColor ?> fs-6"><?= $note ?> / 20</span>
                                    </td>
                                    <td><?= h($n['submitted_at'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Aucune note enregistrée. Vous
                                        n'avez pas encore répondu à un quiz.</td>
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