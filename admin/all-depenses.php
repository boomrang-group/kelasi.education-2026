<?php require_once('service/get-depense.php'); ?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Dépenses</title>

    <link rel="shortcut icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">

            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">

                <div class="breadcrumbs-area">
                    <h3 class="text-uppercase">Liste des Dépenses</h3>
                    <a href="add-depenses.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                        + Nouvelle dépense
                    </a>
                </div>

                <div class="card height-auto">
                    <div class="card-body">

                        <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-info">
                            <?php
                                if ($_GET['msg']=='created') echo "✅ Ajouté";
                                if ($_GET['msg']=='updated') echo "✏️ Modifié";
                                if ($_GET['msg']=='deleted') echo "🗑️ Supprimé";
                            ?>
                        </div>
                        <?php endif; ?>

                        <!-- <div class="d-flex justify-content-between mb-3">
                            <h5 class="mb-0">Toutes les dépenses</h5>                            
                        </div> -->

                        <div class="table-responsive">
                            <table class="table display data-table text-nowrap">

                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Bénéficiaire</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($depenses as $i => $d): ?>
                                    <tr>
                                        <td><?= $i+1 ?></td>
                                        <td><?= htmlspecialchars($d['beneficiaire']) ?></td>
                                        <td><strong><?= number_format($d['montant'],2) ?> $</strong></td>
                                        <td><?= $d['date_depense'] ?></td>

                                        <td>
                                            <!-- ✏️ EDIT -->
                                            <a href="add-depenses.php?id=<?= $d['id'] ?>"
                                                class="btn btn-secondary btn-lg">
                                                Modifier
                                            </a>
                                            <!-- 👁 VOIR -->
                                            <button class="btn btn-primary btn-lg" onclick="showDepense(
                                                    '<?= htmlspecialchars($d['beneficiaire']) ?>',
                                                    '<?= number_format($d['montant'],2) ?>',
                                                    '<?= $d['date_depense'] ?>',
                                                    `<?= htmlspecialchars($d['description'] ?? '') ?>`
                                                )">
                                                Voir
                                            </button>

                                            <!-- 🗑 DELETE -->
                                            <!-- <a href="service/get-depense.php?action=delete&id=<?= $d['id'] ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Supprimer cette dépense ?')">
                                                🗑
                                            </a> -->

                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                </tbody>

                            </table>
                        </div>

                    </div>
                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>

            </div>
        </div>
    </div>

    <!-- ================= MODAL ================= -->
    <div class="modal fade" id="depenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Détail de la dépense</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <p><strong>Bénéficiaire :</strong> <span id="mBenef"></span></p>
                    <p><strong>Montant :</strong> <span id="mMontant"></span> $</p>
                    <p><strong>Date :</strong> <span id="mDate"></span></p>

                    <hr>

                    <p><strong>Description :</strong></p>
                    <div id="mDesc" class="border p-2 bg-light rounded"></div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>

            </div>

        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>

    <script>
    function showDepense(benef, montant, date, desc) {
        document.getElementById('mBenef').innerText = benef;
        document.getElementById('mMontant').innerText = montant;
        document.getElementById('mDate').innerText = date;
        document.getElementById('mDesc').innerText = desc || 'Aucune description';

        $('#depenseModal').modal('show');
    }
    </script>

</body>

</html>