<?php
// repetitorats/students/mes_quiz.php
declare(strict_types=1);
require_once __DIR__ . '/services/quiz-services.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Mes Quiz & Évaluations</title>
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
                    <h3 style="text-transform: uppercase;">MES QUIZ & ÉVALUATIONS</h3>
                    <p class="muted mb-3">Travailler vos devoirs, répondez aux QCM et testez vos compétences.</p>
                </div>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Félicitations ! Votre quiz a été corrigé et enregistré
                    avec succès.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card-box">
                    <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-ui-checks text-primary me-2"></i> Liste des
                        évaluations disponibles</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre du Quiz</th>
                                    <th>Cours / Matière</th>
                                    <th>Format</th>
                                    <th>Statut</th>
                                    <th>Note</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quizzesList)): foreach ($quizzesList as $idx => $q): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= h($q['titre']) ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= h($q['cours_nom'] ?? 'Général') ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $t = strtoupper($q['type_quiz']);
                                        $badgeClass = 'bg-secondary';
                                        if ($t === 'QCM') $badgeClass = 'bg-primary';
                                        elseif ($t === 'QR') $badgeClass = 'bg-warning text-dark';
                                        elseif ($t === 'PJ') $badgeClass = 'bg-info text-dark';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $t ?></span>
                                    </td>
                                    <td>
                                        <?php if ($q['resultat_id'] !== null): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i>
                                            Terminé</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> À
                                            faire</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($q['resultat_id'] !== null): 
                                            $note = (float)$q['note_obtenue'];
                                            $bCol = $note >= 10 ? 'bg-success' : 'bg-danger';
                                            if ($note >= 14) $bCol = 'bg-primary';
                                        ?>
                                        <span class="badge <?= $bCol ?> fs-6"><?= $note ?> / 20</span>
                                        <?php else: ?>
                                        <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($q['resultat_id'] !== null): ?>
                                        <a href="passer_quiz.php?id=<?= (int)$q['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i> Revoir
                                        </a>
                                        <?php else: ?>
                                        <a href="passer_quiz.php?id=<?= (int)$q['id'] ?>"
                                            class="btn btn-sm btn-primary fw-bold">
                                            <i class="bi bi-pencil-square me-1"></i> Composer
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Aucun quiz disponible pour le
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