<?php
// repetitorats/students/reviser-cours.php
declare(strict_types=1);
require_once __DIR__ . '/services/reviser-cours-services.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!$lecon) {
    header('Location: mes_cours.php?msg=not_found');
    exit;
}

// Résolution des noms de fichiers physiques à partir des tables de contenu si nécessaire
if (!empty($contenus) && isset($pdo)) {
    foreach ($contenus as &$cnt) {
        $cnt['filename'] = '';
        $t = $cnt['type_contenu'];
        $cid = (int)$cnt['contenu_id'];
        
        try {
            if ($t === 'pdf') {
                $stF = $pdo->prepare('SELECT filename FROM pdfs WHERE id = :id LIMIT 1');
                $stF->execute([':id' => $cid]);
                $cnt['filename'] = $stF->fetchColumn() ?: '';
            } elseif ($t === 'image') {
                $stF = $pdo->prepare('SELECT filename FROM images WHERE id = :id LIMIT 1');
                $stF->execute([':id' => $cid]);
                $cnt['filename'] = $stF->fetchColumn() ?: '';
            } elseif ($t === 'audio') {
                $stF = $pdo->prepare('SELECT fichier FROM audios WHERE id = :id LIMIT 1');
                $stF->execute([':id' => $cid]);
                $cnt['filename'] = $stF->fetchColumn() ?: '';
            } elseif ($t === 'video') {
                $stF = $pdo->prepare('SELECT filename FROM videos WHERE id = :id LIMIT 1');
                $stF->execute([':id' => $cid]);
                $cnt['filename'] = $stF->fetchColumn() ?: '';
            }
        } catch (Throwable $e) {}
    }
    unset($cnt);
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Révision - <?= h($lecon['titre']) ?></title>
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
        padding: 30px;
        margin-bottom: 25px;
    }
    .lesson-header {
        border-bottom: 2px solid #f4f6f9;
        padding-bottom: 15px;
        margin-bottom: 20px;
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
                <div class="breadcrumbs-area mb-4">
                    <a href="mes_cours.php" class="btn btn-sm btn-secondary mb-3"><i class="bi bi-arrow-left me-1"></i> Retour à mes cours</a>
                    <h3 style="text-transform: uppercase;"><?= h($lecon['cours_nom']) ?></h3>
                    <p class="muted">Espace de révision et consultation des supports pédagogiques.</p>
                </div>

                <div class="card-box">
                    <div class="lesson-header">
                        <span class="badge bg-primary mb-2 text-uppercase text-white"><?= h($lecon['cours_nom']) ?></span>
                        <h2 class="text-dark fw-bold"><?= h($lecon['titre']) ?></h2>
                        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> Publié le : <?= h($lecon['created_at']) ?></small>
                    </div>

                    <!-- Affichage des contenus / médias de la leçon -->
                    <div class="lesson-body">
                        <?php if (!empty($contenus)): ?>
                            <div class="alert alert-info mb-4">
                                <i class="bi bi-info-circle-fill me-2"></i> Cette leçon contient <strong><?= count($contenus) ?></strong> support(s) multimédia(s).
                            </div>

                            <div class="row g-4">
                                <?php foreach ($contenus as $idx => $cnt): 
                                    $filePath = !empty($cnt['filename']) ? '../../uploads/cours_repetitorats/' . $cnt['filename'] : '';
                                ?>
                                    <div class="col-12">
                                        <div class="card border p-3 shadow-sm bg-light">
                                            <h5 class="fw-bold text-secondary mb-3">
                                                Support #<?= $idx + 1 ?> <span class="badge bg-dark text-uppercase text-white ms-2"><?= h($cnt['type_contenu']) ?></span>
                                            </h5>
                                            
                                            <?php if ($filePath && file_exists(__DIR__ . '/../../uploads/cours_repetitorats/' . $cnt['filename'])): ?>
                                                <!-- Affichage selon le type de contenu -->
                                                <?php if ($cnt['type_contenu'] === 'pdf'): ?>
                                                    <div class="ratio ratio-16x9">
                                                        <iframe src="<?= h($filePath) ?>" class="border rounded"></iframe>
                                                    </div>
                                                    <div class="mt-3 text-end">
                                                        <a href="<?= h($filePath) ?>" target="_blank" class="btn btn-lg btn-dark">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i> Ouvrir / Télécharger le PDF
                                                        </a>
                                                    </div>
                                                <?php elseif ($cnt['type_contenu'] === 'image'): ?>
                                                    <div class="text-center">
                                                        <img src="<?= h($filePath) ?>" alt="Support image" class="img-fluid rounded shadow-sm" style="max-height: 500px;">
                                                    </div>
                                                    <div class="mt-3 text-end">
                                                        <a href="<?= h($filePath) ?>" target="_blank" class="btn btn-sm btn-dark">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i> Afficher l'image en grand
                                                        </a>
                                                    </div>
                                                <?php elseif ($cnt['type_contenu'] === 'video'): ?>
                                                    <div class="ratio ratio-16x9">
                                                        <video controls class="rounded shadow-sm">
                                                            <source src="<?= h($filePath) ?>" type="video/mp4">
                                                            Votre navigateur ne supporte pas la lecture de vidéos.
                                                        </video>
                                                    </div>
                                                <?php elseif ($cnt['type_contenu'] === 'audio'): ?>
                                                    <div class="p-3 text-center bg-white rounded border">
                                                        <audio controls class="w-100">
                                                            <source src="<?= h($filePath) ?>" type="audio/mpeg">
                                                            Votre navigateur ne supporte pas la lecture audio.
                                                        </audio>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    <i class="bi bi-exclamation-triangle me-2"></i> Le fichier associé est introuvable sur le serveur.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x fs-1 text-muted"></i>
                                <p class="text-muted mt-3">Aucun fichier multimédia n'est attaché à cette leçon pour le moment. Votre répétiteur n'a publié que le titre.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>