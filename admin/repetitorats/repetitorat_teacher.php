<?php
// kelasi/admin/repetitorats/repetitorat_teacher.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

require_once('service/repetitorat-service-teacher.php');
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Kelasi | Gestion Répétiteurs & Affectations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../css/animate.min.css">
    <link rel="stylesheet" href="../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>
    <style>
    .search-wrap {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-wrap .form-control {
        max-width: 360px;
    }

    .btn-sm {
        padding: .25rem .5rem;
    }

    .table td,
    .table th {
        vertical-align: middle;
    }

    .chip {
        display: inline-block;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: .1rem .55rem;
        font-size: .8rem;
        background: #fafafa;
    }

    .muted {
        color: #6b7280;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('../layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('../layout/sidebar.php'); ?>
            <div class="dashboard-content-one">
                <!-- En-tête avec Bouton de création au-dessus -->
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between my-3">
                    <div>
                        <h3>Gestion et Affectation des Répétiteurs</h3>
                        <p class="muted mb-0">École : <span class="chip"><?= $codeEcole ? h($codeEcole) : '—' ?></span>
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success fw-bold" data-bs-toggle="modal"
                            data-bs-target="#createRepetiteurModal">
                            <i class="fa fa-user-plus me-1"></i> + Créer un Répétiteur
                        </button>
                    </div>
                </div>

                <?= $alertMsg ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="search-wrap">
                            <input id="tableSearch" type="text" class="form-control"
                                placeholder="Rechercher (Élève, Répétiteur, Matière)...">
                        </div>
                    </div>
                </div>

                <div class="card height-auto">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="repetiteurTable" class="table display data-table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Élève encadré</th>
                                        <th>Classe</th>
                                        <th>Matières demandées</th>
                                        <th>Répétiteur Assigné</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($reservations): ?>
                                    <?php foreach ($reservations as $res):
                                        $fullName = trim(($res['nom_eleve'] ?? '').' '.($res['postnom_eleve'] ?? '').' '.($res['prenom_eleve'] ?? ''));
                                        $assignedTeacher = trim((string)($res['repetiteur_pref'] ?? ''));
                                    ?>
                                    <tr>
                                        <td>#<?= (int)$res['id'] ?></td>
                                        <td><strong><?= h($fullName) ?></strong></td>
                                        <td><?= h($res['classe'] ?: '—') ?></td>
                                        <td><?= h($res['matieres'] ?: '—') ?></td>
                                        <td>
                                            <?php if ($assignedTeacher !== '' && $assignedTeacher !== 'Non spécifié'): ?>
                                            <span class="badge bg-success text-white"><i
                                                    class="fa fa-user-check me-1"></i><?= h($assignedTeacher) ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">Non assigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info text-dark"><?= h($res['statut']) ?></span></td>
                                        <td class="text-nowrap">
                                            <!-- Bouton pour associer un répétiteur existant -->
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#assignRepetiteurModal" data-id="<?= (int)$res['id'] ?>"
                                                data-eleve="<?= h($fullName) ?>">
                                                <i class="fa fa-link me-1"></i> Affecter / Modifier Répétiteur
                                            </button>

                                            <button class="btn btn-sm btn-danger ms-1"
                                                onclick="deleteReservation(<?= (int)$res['id'] ?>)">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center muted">Aucune demande trouvée.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php require_once('../layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- MODAL 1 : CRÉER UN NOUVEAU RÉPÉTITEUR (Global) -->
    <div class="modal fade" id="createRepetiteurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title text-white"><i class="fa fa-user-plus me-2"></i>Nouveau Répétiteur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_repetiteur_global">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Genre</label>
                                <select class="form-select" name="gender">
                                    <option value="homme">Masculin</option>
                                    <option value="femme">Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom d'utilisateur (Username)</label>
                                <input type="text" class="form-control" name="username" required
                                    placeholder="ex: jean.dupont">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mot de passe provisoire</label>
                                <input type="text" class="form-control" name="password" value="123456" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail (optionnel)</label>
                                <input type="email" class="form-control" name="email"
                                    placeholder="repetiteur@ecole.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Matières dispensées / Spécialisation</label>
                                <input type="text" class="form-control" name="specialization"
                                    placeholder="ex: Mathématiques, Physique">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check me-1"></i> Créer le
                            Répétiteur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2 : AFFECTER UN RÉPÉTITEUR EXISTANT -->
    <div class="modal fade" id="assignRepetiteurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title text-white"><i class="fa fa-link me-2"></i>Affecter un Répétiteur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_repetiteur">
                        <input type="hidden" name="reservation_id" id="modal_assign_res_id">

                        <p>Élève : <strong id="modal_eleve_name" class="text-primary"></strong></p>

                        <div class="mb-3">
                            <label class="form-label">Sélectionner un répétiteur enregistré :</label>
                            <select class="form-select" name="teacher_name" required>
                                <option value="">-- Sélectionner un répétiteur --</option>
                                <?php if ($repetiteursExistants): ?>
                                <?php foreach ($repetiteursExistants as $r): 
                                        $rName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                                        if (!$rName) $rName = $r['username'];
                                    ?>
                                <option value="<?= h($rName) ?>"><?= h($rName) ?> (<?= h($r['username']) ?>)</option>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <option value="" disabled>Aucun répétiteur trouvé avec le rôle 'repetiteur'</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check me-1"></i> Valider
                            l'affectation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/plugins.js"></script>
    <script src="../../js/popper.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/jquery.scrollUp.min.js"></script>
    <script src="../../js/jquery.dataTables.min.js"></script>
    <script src="../../js/main.js"></script>
    <script>
    $(function() {
        const assignModal = document.getElementById('assignRepetiteurModal');
        if (assignModal) {
            assignModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('modal_assign_res_id').value = button.getAttribute('data-id');
                document.getElementById('modal_eleve_name').textContent = button.getAttribute(
                    'data-eleve');
            });
        }
    });

    function deleteReservation(id) {
        if (!id) return;
        if (confirm('Voulez-vous supprimer cette demande ?')) {
            window.location.href = 'service/repetitorat-service-teacher.php?action=delete&id=' + encodeURIComponent(id);
        }
    }
    </script>
</body>

</html>