<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
$role = strtolower((string)($_SESSION['role'] ?? ''));

if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden');
    exit;
}

/* =========================
   TOGGLE STATUS (IMPORTANT)
========================= */
if (isset($_GET['toggle_id'])) {
    require_once __DIR__.'/../database/db_connect.php';
    $id = (int)$_GET['toggle_id'];

    if ($id > 0) {

        $stmt = $pdo->prepare("SELECT status FROM classes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class) {

            $newStatus = ((int)$class['status'] === 1) ? 0 : 1;

            $update = $pdo->prepare("
                UPDATE classes 
                SET status = :status 
                WHERE id = :id
            ");

            $update->execute([
                ':status' => $newStatus,
                ':id' => $id
            ]);
        }
    }

    header("Location: all-class.php?msg=updated");
    exit;
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title> MyKelasi | All Classes</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- Normalize CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="../css/all.min.css">
    <!-- Flaticon CSS -->
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="../css/animate.min.css">
    <!-- Data Table CSS -->
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Modernize js -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    .modal-header {
        background: #e53935 !important;
    }

    .modal-body h5 {
        font-weight: 600;
        color: #333;
    }

    .btn-danger {
        background: #e53935;
        border: none;
    }

    .btn-danger:hover {
        background: #c62828;
    }

    .row-active {
        background-color: #e8f5e9;
        /* vert clair */
    }

    .row-inactive {
        background-color: #ffebee;
        /* rouge clair */
    }

    /* Texte */
    .row-active td {
        color: #2e7d32;
        font-weight: 500;
    }

    .row-inactive td {
        color: #c62828;
        font-weight: 500;
    }
    </style>
</head>

<body>
    <!-- Preloader Start Here -->
    <div id="preloader" class="d-none"></div>
    <!-- Preloader End Here -->
    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header Menu Area Start Here -->
        <?php require_once('layout/navbar.php'); ?>
        <!-- Header Menu Area End Here -->
        <!-- Page Area Start Here -->
        <div class="dashboard-page-one">
            <!-- Sidebar Area Start Here -->
            <?php require_once('layout/sidebar.php'); ?>
            <!-- Sidebar Area End Here -->
            <div class="dashboard-content-one">
                <!-- Breadcubs Area Start Here -->
                <div class="breadcrumbs-area">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                        <h3 class="mb-0 text-uppercase">
                            Classes
                        </h3>

                        <a href="add-class.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark text-nowrap">
                            Créer une classe
                        </a>

                    </div>
                    <!-- <ul>
                        <li>
                            <a href="index.php">Home</a>
                        </li>
                        <li>All Classes</li>
                    </ul> -->
                </div>
                <!-- Breadcubs Area End Here -->
                <!-- Class Table Area Start Here -->
                <div class="card height-auto">
                    <div class="card-body">
                        <!-- <div class="heading-layout1">
                            <div class="item-title">
                                <div class="d-block justify-content-between w-100 p-2">
                                    <span class="badge bg-success text-white" style="margin-left 10px;">
                                        ✅ CLASSE ACTIVE
                                    </span>
                                    <span class="badge bg-danger text-white" style="margin-left 10px;">
                                        ❌ CLASSE INACTIVE
                                    </span>

                                </div>
                            </div>
                        </div> -->
                        <!-- <form class="mg-b-20">
                            <div class="row gutters-8">
                                <div class="col-3-xxxl col-xl-3 col-lg-3 col-12 form-group">
                                    <input type="text" placeholder="Search by ID ..." class="form-control">
                                </div>
                                <div class="col-4-xxxl col-xl-4 col-lg-3 col-12 form-group">
                                    <input type="text" placeholder="Search by Name ..." class="form-control">
                                </div>
                                <div class="col-4-xxxl col-xl-3 col-lg-3 col-12 form-group">
                                    <input type="text" placeholder="Search by Class ..." class="form-control">
                                </div>
                                <div class="col-1-xxxl col-xl-2 col-lg-3 col-12 form-group">
                                    <button type="submit" class="fw-btn-fill btn-gradient-yellow">SEARCH</button>
                                </div>
                            </div>
                        </form> -->

                        <div class="table-responsive">
                            <table class="table display data-table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <!-- <th>Photo</th> -->
                                        <th>Classe</th>
                                        <!-- <th>Gender</th> -->
                                        <th>Niveau</th>
                                        <th>Section</th>
                                        <th>Option</th>
                                        <!-- <th>Enseignant</th>
                                        <th>Nbre élèves</th> -->
                                        <!-- <th>E-mail</th> -->
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- PHP loop to populate students START -->
                                    <?php
    try {
        // Requête corrigée avec jointures logiques
        $stmt = $pdo->query("
            SELECT 
            classes.id AS identity, 
            classes.description AS description,
            classes.classe AS classe, 
            classes.status AS status, 
            niveau.description AS niveau,
            section.description AS section, 
            options.description AS options
            FROM classes
            LEFT JOIN section ON classes.section = section.id
            LEFT JOIN niveau ON classes.niveau = niveau.id
            LEFT JOIN options ON classes.options = options.id
            WHERE classes.code_ecole = '$codeEcole';
        ");

        $students = $stmt->fetchAll();

        if ($students) {
            foreach ($students as $student) {
                echo '<tr class="'.($student['status'] == 1 ? 'row-active' : 'row-inactive').'">';
                echo '<td><a href="detail_classe.php?id=' . $student['identity'] . '" class="btn btn-primary">'
                    . ($student['identity'] ?? 'N/A') .
                    '</a></td>';          
                echo "<td>" . htmlspecialchars($student['classe'] . ' ' . $student['description']) . "</td>";
                echo "<td>" . htmlspecialchars($student['niveau'] ?: 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($student['section'] ?: 'N/A') . "</td>";  
                echo "<td>" . htmlspecialchars($student['options'] ?: 'N/A') . "</td>";  
                echo '<td>
                            <a href="#"
                                class="btn btn-'.($student['status'] == 1 ? 'warning' : 'success').' btn-lg"
                                data-id="'.$student['identity'].'"
                                data-status="'.$student['status'].'"
                                data-label="'.htmlspecialchars($student['classe'].' '.$student['description']).'"
                                onclick="openStatusModal(this)">
                                '.($student['status'] == 1 ? 'Désactiver' : 'Activer').'
                            </a>

                            <a href="add-class.php?id='.$student['identity'].'"
                                class="btn btn-secondary btn-lg">Modifier</a>

                            <a href="detail_classe.php?id='.$student['identity'].'"
                                class="btn btn-primary btn-lg">Voir</a>
                        </td>';
                        echo "</tr>";
                        }
                        } else {
                        echo "<tr>
                            <td colspan='5' class='text-center'>Aucune classe trouvée.</td>
                        </tr>";
                        }
                        } catch (PDOException $e) {
                        echo "<tr>
                            <td colspan='5' class='text-center text-danger'>Erreur : " .
                                htmlspecialchars($e->getMessage()) . "</td>
                        </tr>";
                        }
                        ?>
                                    <!-- PHP loop to populate students END -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Class Table Area End Here -->
                <footer class="footer-wrap-layout1">
                    <div class="copyright">© Copyrights <a href="#">akkhor</a> 2019. All rights reserved. Designed by <a
                            href="#">PsdBosS</a></div>
                </footer>
            </div>
        </div>
        <!-- Page Area End Here -->
    </div>
    <!-- DELETE MODAL -->
    <div class="modal fade" id="statusClassModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">⚙️ Changement de statut</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body text-center p-4">
                    <h5 id="classLabel" class="mb-3"></h5>

                    <p class="text-muted">
                        Voulez-vous vraiment changer le statut de cette classe ?
                    </p>
                </div>

                <div class="modal-footer justify-content-center">
                    <a id="confirmStatusBtn" href="#" class="btn btn-warning btn-lg px-4">
                        Confirmer
                    </a>

                    <button type="button" class="btn btn-secondary btn-lg px-4" data-dismiss="modal">
                        Annuler
                    </button>
                </div>

            </div>
        </div>
    </div>
    <!-- jquery-->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <!-- Plugins js -->
    <script src="../js/plugins.js"></script>
    <!-- Popper js -->
    <script src="../js/popper.min.js"></script>
    <!-- Bootstrap js -->
    <script src="../js/bootstrap.min.js"></script>
    <!-- Scroll Up Js -->
    <script src="../js/jquery.scrollUp.min.js"></script>
    <!-- Data Table Js -->
    <script src="../js/jquery.dataTables.min.js"></script>
    <!-- Custom Js -->
    <script src="../js/main.js"></script>

    <script>
    function openStatusModal(el) {
        const id = el.getAttribute('data-id');
        const label = el.getAttribute('data-label');

        document.getElementById('classLabel').innerText = label;

        document.getElementById('confirmStatusBtn').href =
            'all-class.php?toggle_id=' + encodeURIComponent(id);

        $('#statusClassModal').modal('show');
    }
    </script>
</body>

</html>