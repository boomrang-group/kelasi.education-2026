<?php
// repetitorats/teachers/mes_cours_lessons.php
declare(strict_types=1);
require_once __DIR__ . '/services/cours-lessons-services.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Gestion des Cours & Leçons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

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
        font-size: 1.4rem;
        color: #fff;
        text-decoration: none;
        gap: 8px;
    }

    .brand-box span {
        font-size: 0.65rem;
        display: block;
        font-weight: 400;
        text-transform: uppercase;
    }

    .topbar-center {
        margin-left: 260px;
    }

    .topbar-school-name {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
        margin: 0;
    }

    .topbar-school-url {
        color: #0d6efd;
        font-size: 0.8rem;
        text-decoration: none;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-name-title {
        font-weight: 700;
        font-size: 0.9rem;
        margin: 0;
        text-align: right;
        color: #333;
    }

    .user-role-title {
        font-size: 0.75rem;
        color: #6c757d;
        margin: 0;
        text-align: right;
    }

    .user-avatar-circle {
        width: 38px;
        height: 38px;
        background: #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        font-size: 1.1rem;
    }

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
        padding: 13px 20px;
        color: #b0bec5;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
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
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
        border-left-color: var(--primary-orange);
    }

    .sidebar-menu li.logout-item a {
        color: #ff5252;
    }

    .sidebar-menu li.logout-item a:hover {
        background: rgba(255, 82, 82, 0.1);
        border-left-color: #ff5252;
        color: #fff;
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

    <header class="navbar-top">
        <a href="dashboard.php" class="brand-box"><i class="fa fa-graduation-cap"></i> Kelasi <span>Education</span></a>
        <div class="topbar-center">
            <h6 class="topbar-school-name"><?= htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8') ?></h6>
            <a href="#" class="topbar-school-url"><?= htmlspecialchars($urlEcole, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="topbar-right">
            <div>
                <p class="user-name-title"><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="user-role-title">Répétiteur</p>
            </div>
            <div class="user-avatar-circle"><i class="fa fa-user"></i></div>
        </div>
    </header>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-title-box">
            <h2>GESTION DES COURS & LEÇONS</h2>
            <p>Créez vos supports pédagogiques multimédias et publiez-les pour vos élèves encadrés.</p>
        </div>

        <?php if ($successMsg !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i> <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulaire de création -->
        <div class="card-custom">
            <h4 class="text-primary fw-bold mb-4"><i class="bi bi-plus-circle me-2"></i> Ajouter une nouvelle leçon /
                cours</h4>
            <form action="mes_cours_lessons.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_cours">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom du Cours / Matière</label>
                        <input type="text" name="nom_cours" class="form-control" list="cours-existants"
                            placeholder="Sélectionnez ou tapez un cours..." required>
                        <datalist id="cours-existants">
                            <?php foreach ($existingCourses as $cName): ?>
                            <option value="<?= htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Titre de la Leçon</label>
                        <input type="text" name="titre_lecon" class="form-control"
                            placeholder="Ex: Chapitre 1 : Les équations" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Description / Notes de la leçon</label>
                    <textarea name="description_contenu" class="form-control" rows="3"
                        placeholder="Détails ou consignes pour l'élève..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Fichier multimédia (PDF, Image, Audio ou Vidéo)</label>
                    <input type="file" name="fichier_media" class="form-control">
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" name="is_published" class="form-check-input" id="pubCheck" value="1" checked>
                    <label class="form-check-label fw-bold text-success" for="pubCheck">Publier immédiatement (visible
                        par les élèves)</label>
                </div>

                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                    <i class="fa fa-upload me-2"></i> Enregistrer et Publier le cours
                </button>
            </form>
        </div>

        <!-- Liste des cours existants -->
        <div class="card-custom">
            <h4 class="text-dark fw-bold mb-4"><i class="bi bi-journal-text me-2"></i> Mes cours et leçons enregistrés
            </h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Intitulé du Cours</th>
                            <th>Leçon associée</th>
                            <th>Pièce Jointe</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($mesCours)): foreach ($mesCours as $idx => $c): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><strong><?= htmlspecialchars($c['cours_nom'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($c['lecon_titre'] ?? 'Aucune leçon', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (!empty($c['type_contenu'])): ?>
                                <span class="badge bg-info text-dark text-uppercase"><i class="bi bi-paperclip"></i>
                                    <?= htmlspecialchars($c['type_contenu'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                <span class="text-muted small">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['lecon_id'])): ?>
                                <select class="form-select form-select-sm status-select"
                                    data-lecon-id="<?= (int)$c['lecon_id'] ?>" style="width: 120px;">
                                    <option value="1" <?= ((int)$c['is_published'] === 1) ? 'selected' : '' ?>>Publié
                                    </option>
                                    <option value="0" <?= ((int)$c['is_published'] === 0) ? 'selected' : '' ?>>Brouillon
                                    </option>
                                </select>
                                <?php else: ?>
                                <span class="badge bg-light text-muted">Vide</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($c['lecon_created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end">
                                <?php if (!empty($c['lecon_id'])): ?>
                                <button class="btn btn-sm btn-outline-primary view-btn"
                                    data-cours="<?= htmlspecialchars($c['cours_nom'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-lecon="<?= htmlspecialchars($c['lecon_titre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-type="<?= htmlspecialchars($c['type_contenu'] ?? 'Aucun', ENT_QUOTES, 'UTF-8') ?>"
                                    data-bs-toggle="modal" data-bs-target="#viewModal" title="Voir">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary edit-btn"
                                    data-id="<?= (int)$c['lecon_id'] ?>"
                                    data-titre="<?= htmlspecialchars($c['lecon_titre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-pub="<?= (int)$c['is_published'] ?>" data-bs-toggle="modal"
                                    data-bs-target="#editModal" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn"
                                    data-id="<?= (int)$c['lecon_id'] ?>" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Vous n'avez pas encore créé de cours.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Voir -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Détails de la Leçon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Cours :</strong> <span id="modalCours"></span></p>
                    <p><strong>Leçon :</strong> <span id="modalLecon"></span></p>
                    <p><strong>Type de média :</strong> <span id="modalType" class="text-uppercase"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="mes_cours_lessons.php" method="POST">
                    <input type="hidden" name="action" value="edit_lecon">
                    <input type="hidden" name="lecon_id" id="editLeconId">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Modifier la Leçon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Titre de la Leçon</label>
                            <input type="text" name="titre_lecon" id="editTitreLecon" class="form-control" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_published" class="form-check-input" id="editPubCheck"
                                value="1">
                            <label class="form-check-label fw-bold" for="editPubCheck">Publié</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
        integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous">
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Changement de statut instantané
        document.querySelectorAll('.status-select').forEach(function(select) {
            select.addEventListener('change', function() {
                let leconId = this.getAttribute('data-lecon-id');
                let newStatus = this.value;
                let selectElement = this;

                let formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('lecon_id', leconId);
                formData.append('is_published', newStatus);

                fetch('mes_cours_lessons.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            selectElement.style.borderColor = '#198754';
                            setTimeout(() => {
                                selectElement.style.borderColor = '';
                            }, 1500);
                        } else {
                            alert('Erreur: ' + (data.message || 'Inconnue'));
                        }
                    });
            });
        });

        // Remplir le modal Voir
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('modalCours').textContent = this.getAttribute(
                    'data-cours');
                document.getElementById('modalLecon').textContent = this.getAttribute(
                    'data-lecon');
                document.getElementById('modalType').textContent = this.getAttribute(
                    'data-type');
            });
        });

        // Remplir le modal Modifier
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editLeconId').value = this.getAttribute('data-id');
                document.getElementById('editTitreLecon').value = this.getAttribute(
                    'data-titre');
                document.getElementById('editPubCheck').checked = (this.getAttribute(
                    'data-pub') === '1');
            });
        });

        // Suppression
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Voulez-vous vraiment supprimer cette leçon ?')) {
                    let leconId = this.getAttribute('data-id');
                    let formData = new FormData();
                    formData.append('action', 'delete_lecon');
                    formData.append('lecon_id', leconId);

                    fetch('mes_cours_lessons.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Erreur: ' + (data.message || 'Inconnue'));
                            }
                        });
                }
            });
        });
    });
    </script>
</body>

</html>