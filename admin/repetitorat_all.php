<?php require_once('service/repetitorat-service.php'); ?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Inscriptions Répétitorat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- CSS existants -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
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

    .badge-status {
        font-size: .85rem;
        padding: .35em .65em;
        border-radius: 4px;
        font-weight: 600;
    }
    .badge-attente { background-color: #ffe17d; color: #856404; }
    .badge-confirmee { background-color: #d4edda; color: #155724; }
    .badge-annulee { background-color: #f8d7da; color: #721c24; }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php require_once('layout/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <!-- Header + Bouton -->
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <div>
                        <h3>Demandes de Répétitorat</h3>
                        <p class="muted mb-0">École : <span class="chip"><?= $codeEcole ? h($codeEcole) : '—' ?></span></p>
                    </div>
                </div>

                <!-- Zone de recherche -->
                <div class="card">
                    <div class="card-body">
                        <div class="search-wrap">
                            <input id="tableSearch" type="text" class="form-control"
                                placeholder="Rechercher (Élève, Parent, Classe, Matières, Téléphone)...">
                        </div>
                    </div>
                </div>

                <!-- Table répétitorat -->
                <div class="card height-auto">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="repetitoratTable" class="table display data-table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom de l'élève</th>
                                        <th>Classe</th>
                                        <th>Parent / Tuteur</th>
                                        <th>WhatsApp / Tél</th>
                                        <th>Matières</th>
                                        <th>Mode</th>
                                        <th>Statut</th>
                                        <th>Date Demande</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($reservations): ?>
                                    <?php foreach ($reservations as $res):
                                        $fullName = trim(($res['nom_eleve'] ?? '').' '.($res['postnom_eleve'] ?? '').' '.($res['prenom_eleve'] ?? ''));
                                        $createdAt = $res['created_at'] ? date('d/m/Y H:i', strtotime((string)$res['created_at'])) : '—';
                                        
                                        // Badge statut
                                        $st = $res['statut'] ?? 'en_attente';
                                        $badgeClass = 'badge-attente';
                                        $stLabel = 'En attente';
                                        if ($st === 'confirmee') {
                                            $badgeClass = 'badge-confirmee';
                                            $stLabel = 'Confirmée';
                                        } elseif ($st === 'annulee') {
                                            $badgeClass = 'badge-annulee';
                                            $stLabel = 'Annulée';
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?= (int)$res['id'] ?></td>
                                        <td><strong><?= h($fullName) ?></strong></td>
                                        <td><?= h($res['classe'] ?: '—') ?></td>
                                        <td><?= h($res['nom_parent'] ?: '—') ?></td>
                                        <td>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', (string)$res['whatsapp_parent']) ?>" target="_blank" class="text-success fw-bold">
                                                <i class="fab fa-whatsapp me-1"></i><?= h($res['whatsapp_parent']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 180px;" title="<?= h($res['matieres']) ?>">
                                                <?= h($res['matieres'] ?: '—') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= h(ucfirst((string)$res['mode'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= $badgeClass ?>"><?= $stLabel ?></span>
                                        </td>
                                        <td><?= h($createdAt) ?></td>
                                        <td class="text-nowrap">
                                            <!-- Bouton Modifier statut / Action -->
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Changer Statut
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="confirmee">
                                                            <button type="submit" class="dropdown-item text-success"><i class="fa fa-check me-2"></i>Confirmer</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="annulee">
                                                            <button type="submit" class="dropdown-item text-danger"><i class="fa fa-times me-2"></i>Annuler</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_statut">
                                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                                            <input type="hidden" name="statut" value="en_attente">
                                                            <button type="submit" class="dropdown-item text-warning"><i class="fa fa-clock me-2"></i>En attente</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                            <button class="btn btn-sm btn-danger ms-1" onclick="deleteReservation(<?= (int)$res['id'] ?>)">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center muted">Aucune inscription de répétitorat trouvée.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php require_once('layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    // Filtrage instantané
    $(function() {
        const $rows = $('#repetitoratTable tbody tr');
        $('#tableSearch').on('input', function() {
            const q = $(this).val().toString().trim().toLowerCase();
            if (!q) {
                $rows.show();
                return;
            }
            $rows.each(function() {
                const txt = $(this).text().toLowerCase();
                $(this).toggle(txt.indexOf(q) !== -1);
            });
        });
    });

    // Suppression
    function deleteReservation(id) {
        if (!id) return;
        if (confirm('Voulez-vous vraiment supprimer la réservation #' + id + ' ?')) {
            window.location.href = 'delete_repetitorat.php?id=' + encodeURIComponent(id);
        }
    }
    </script>
</body>

</html>