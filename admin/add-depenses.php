<?php
require_once('service/get-depense.php');
?>

<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Dépenses</title>

    <link rel="shortcut icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">

            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">

                <div class="breadcrumbs-area">
                    <h3>
                        <?= $editData ? 'Modification de la Dépense' : 'Création des Dépenses' ?>
                    </h3>
                </div>

                <div class="card height-auto">
                    <div class="card-body">

                        <?php if (!empty($flash)): ?>
                        <div class="alert alert-info">
                            <?= htmlspecialchars($flash) ?>
                        </div>
                        <?php endif; ?>

                        <form class="new-added-form" method="POST" action="service/get-depense.php">

                            <input type="hidden" name="action" value="save">

                            <?php if ($editData): ?>
                            <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
                            <?php endif; ?>

                            <div class="row">

                                <div class="col-xl-6 col-lg-6 col-12 form-group">
                                    <label>Bénéficiaire *</label>
                                    <input type="text" name="beneficiaire" class="form-control" required
                                        value="<?= htmlspecialchars($editData['beneficiaire'] ?? '') ?>">
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12 form-group">
                                    <label>Montant *</label>
                                    <input type="number" step="0.01" name="montant" class="form-control" required
                                        value="<?= htmlspecialchars($editData['montant'] ?? '') ?>">
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12 form-group">
                                    <label>Date de dépense *</label>
                                    <input type="date" name="date_depense" class="form-control" required
                                        value="<?= htmlspecialchars($editData['date_depense'] ?? '') ?>">
                                </div>

                                <div class="col-xl-12 col-lg-12 col-12 form-group">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control"
                                        rows="4"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
                                </div>

                                <div class="col-12 form-group mg-t-8">

                                    <button type="submit" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                                        <?= $editData ? 'Modifier la dépense' : 'Enregistrer la dépense' ?>
                                    </button>

                                    <a href="all-depenses.php" class="btn-fill-lg bg-blue-dark btn-hover-yellow">
                                        Retour
                                    </a>

                                </div>

                            </div>

                        </form>

                    </div>
                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>

            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/main.js"></script>

</body>

</html>