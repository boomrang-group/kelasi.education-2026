<?php
// repetitorats/teachers/notes_eleves.php
declare(strict_types=1);
require_once __DIR__ . '/services/notes-eleves-services.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Suivi des Cotes & Moyennes des Élèves</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    :root {
        --sidebar-width: 240px;
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

    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: 70px;
        padding: 30px;
    }

    .page-title-box h2 {
        font-weight: 800;
        font-size: 1.5rem;
        color: #263238;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .page-title-box p {
        color: #546e7a;
        font-size: 0.95rem;
        margin-bottom: 25px;
    }

    .card-custom {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        margin-bottom: 25px;
    }

    .table th {
        font-weight: 600;
        color: #37474f;
        background-color: #f8f9fa;
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <!-- Inclusion de la Navbar -->
        <?php require_once(__DIR__ . '/includes/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Inclusion de la Sidebar -->
            <?php require_once(__DIR__ . '/includes/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">COTES ET MOYENNES DES ÉLÈVES</h3>
                    <p>Consultez les notes obtenues par vos élèves aux différentes évaluations et suivez leur moyenne
                        générale.
                    </p>

                </div>
                <!-- Tableau de suivi des cotes -->
                <div class="card-custom">
                    <h4 class="text-dark fw-bold mb-4"><i class="bi bi-journal-check me-2"></i> Récapitulatif des
                        performances
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nom & Prénom de l'élève</th>
                                    <th>Identifiant</th>
                                    <th>Quizé(s) évalué(s)</th>
                                    <th>Moyenne générale</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($elevesNotes)): foreach ($elevesNotes as $idx => $eleve): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars(trim(($eleve['last_name'] ?? '') . ' ' . ($eleve['first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?: htmlspecialchars($eleve['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </td>
                                    <td><code><?= htmlspecialchars($eleve['username'], ENT_QUOTES, 'UTF-8') ?></code>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= (int)($eleve['total_quiz_passes'] ?? 0) ?>
                                            évaluation(s)</span></td>
                                    <td>
                                        <?php 
                                $moyenne = $eleve['moyenne_notes'] !== null ? round((float)$eleve['moyenne_notes'], 2) : null;
                                if ($moyenne !== null):
                                    $badgeColor = $moyenne >= 10 ? 'bg-success' : 'bg-danger';
                                    if ($moyenne >= 14) $badgeColor = 'bg-primary';
                                ?>
                                        <span class="badge <?= $badgeColor ?> fs-6"><?= $moyenne ?> / 20</span>
                                        <?php else: ?>
                                        <span class="text-muted small">Aucune cote enregistrée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="eleves.php" class="btn btn-sm btn-outline-primary"
                                            title="Voir l'élève">
                                            <i class="bi bi-eye"></i> Détails
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Aucun élève encadré ou aucune
                                        note
                                        enregistrée pour le moment.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>