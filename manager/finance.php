<?php
// /mykelasi/my_school/finances.php
declare(strict_types=1);

/* ===== DEBUG FACULTATIF (ajoute ?debug=1 dans l’URL) ===== */
 // DEBUG handled in db_connect.php


/* ===== SESSION (simple) ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ===== CHARGER LES DONNÉES =====
   On essaye d’inclure le service où tu calcules :
   $somme_inscription, $somme_minerval, $somme_autres, $total_general
*/
$somme_inscription = $somme_minerval = $somme_autres = $total_general = 0.0;

$service_paths = [
    __DIR__ . '/service/dashboard.service.php',
    __DIR__ . '/../service/dashboard.service.php',
    __DIR__ . '/dashboard.service.php',
];
foreach ($service_paths as $sp) {
    if (file_exists($sp)) { require_once $sp; break; }
}

/* Valeurs par défaut si non définies par le service */
$somme_inscription = isset($somme_inscription) ? (float)$somme_inscription : 0.0;
$somme_minerval    = isset($somme_minerval)    ? (float)$somme_minerval    : 0.0;
$somme_autres      = isset($somme_autres)      ? (float)$somme_autres      : 0.0;
$total_general     = isset($total_general)     ? (float)$total_general     : 0.0;
if ($total_general <= 0) {
    $total_general = $somme_inscription + $somme_minerval + $somme_autres;
}

/* Helper affichage monétaire */
function fmt_money(float $v): string {
    return number_format($v, 2, ',', ' ');
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Finances</title>
    <meta name="description" content="Synthèse des finances">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">

    <!-- CSS corrects -->
    <link rel="stylesheet" href="../css/normalize.css"><!-- corrigé -->
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <!-- Modernizr -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>
    <!-- Preloader Start Here -->
    <div id="preloader" class="d-none"></div>
    <!-- Preloader End Here -->

    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php
        $nav = __DIR__ . '/layouts/navbar.php';
        if (file_exists($nav)) { require $nav; }
        ?>

        <!-- Page Area Start Here -->
        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php
            $side = __DIR__ . '/layouts/sidebar.php';
            if (file_exists($side)) { require $side; }
            ?>

            <div class="dashboard-content-one">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Finances</h3>
                </div>

                <!-- Résumés -->
                <div class="row">
                    <!-- Inscription -->
                    <div class="col-3-xxxl col-sm-4 col-12">
                        <div class="dashboard-summery-one">
                            <div class="row">
                                <div class="col-6">
                                    <div class="item-icon bg-light-red">
                                        <i class="flaticon-money text-red"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Inscription</div>
                                        <div class="item-number"><span>$</span>
                                            <span><?= fmt_money($somme_inscription) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Minervale -->
                    <div class="col-3-xxxl col-sm-4 col-12">
                        <div class="dashboard-summery-one">
                            <div class="row">
                                <div class="col-6">
                                    <div class="item-icon bg-light-magenta">
                                        <i class="flaticon-shopping-list text-magenta"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Minervale</div>
                                        <div class="item-number"><span>$</span>
                                            <span><?= fmt_money($somme_minerval) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Autre frais -->
                    <div class="col-3-xxxl col-sm-4 col-12">
                        <div class="dashboard-summery-one">
                            <div class="row">
                                <div class="col-6">
                                    <div class="item-icon bg-light-yellow">
                                        <i class="flaticon-mortarboard text-orange"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Autres frais</div>
                                        <div class="item-number"><span>$</span>
                                            <span><?= fmt_money($somme_autres) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total (sur toute la ligne sur mobile) -->
                    <div class="col-12-xxxl col-sm-12 col-12">
                        <div class="dashboard-summery-one">
                            <div class="row">
                                <div class="col-6">
                                    <div class="item-icon bg-light-blue">
                                        <i class="flaticon-money text-blue"></i>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="item-content">
                                        <div class="item-title">Total Finances</div>
                                        <div class="item-number"><span>$</span>
                                            <span><?= fmt_money($total_general) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                $foot = __DIR__ . '/layouts/footer.php';
                if (file_exists($foot)) { require $foot; }
                ?>
            </div>
        </div>
        <!-- Page Area End Here -->
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.counterup.min.js"></script>
    <script src="../js/jquery.waypoints.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>

    <?php if (DEBUG): ?>
    <script>
    console.log('DEBUG: sommes =', {
        inscription: '<?= $somme_inscription ?>',
        minervale: '<?= $somme_minerval ?>',
        autres: '<?= $somme_autres ?>',
        total: '<?= $total_general ?>'
    });
    </script>
    <?php endif; ?>
</body>

</html>