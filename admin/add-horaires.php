<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ======================================
// DB
// ======================================
$pdo = null;

$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];

foreach ($db_candidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur (DB).');
}

// ======================================
// AUTH
// ======================================
$userId  = $_SESSION['user_id'] ?? null;
$roleRaw = $_SESSION['role'] ?? '';
$role    = strtolower(trim((string)$roleRaw));

$isAdmin = in_array($role, ['admin', 'administrateur'], true);

// ======================================
// ECOLE
// ======================================
$codeEcole = $_SESSION['code_ecole'] ?? '';

if (!$codeEcole && $userId) {

    try {

        $st = $pdo->prepare("
            SELECT code_ecole
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $st->execute([
            ':id' => $userId
        ]);

        $codeEcole = (string)($st->fetchColumn() ?: '');

        if ($codeEcole) {
            $_SESSION['code_ecole'] = $codeEcole;
        }

    } catch(Throwable $e){
        die($e->getMessage());
    }
}

if (!$isAdmin || !$codeEcole) {
    http_response_code(403);
    exit("Accès refusé");
}

// ======================================
// CLASSES
// ======================================
$classes = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.classe,
            c.description,
            n.description AS niveau
        FROM classes c
        LEFT JOIN niveau n
            ON n.id = c.niveau
        WHERE c.code_ecole = ?
        ORDER BY c.classe ASC
    ");

    $stmt->execute([$codeEcole]);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e){
    die($e->getMessage());
}

$flash = '';

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === 'created') {
        $flash = "✅ Horaire enregistré avec succès.";
    }

    if ($_GET['msg'] === 'error') {
        $flash = "❌ Erreur lors de l'enregistrement.";
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>

    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>MyKelasi | Création Horaire Classe</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Assets -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">

    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../style.css">

    <script src="../js/modernizr-3.6.0.min.js"></script>

    <style>
    .horaire-table input {
        min-width: 130px;
    }

    .horaire-table th {
        white-space: nowrap;
        font-size: 14px;
    }

    .btn-action {
        min-width: 40px;
    }
    </style>

</head>

<body>

    <div id="preloader" class="d-none"></div>

    <div id="wrapper" class="wrapper bg-ash">

        <?php
        $nav = __DIR__ . '/layout/navbar.php';
        if (file_exists($nav)) require $nav;
        ?>

        <div class="dashboard-page-one">

            <?php
            $side = __DIR__ . '/layout/sidebar.php';
            if (file_exists($side)) require $side;
            ?>

            <div class="dashboard-content-one">

                <div class="breadcrumbs-area">
                    <h3>Création Horaire de Classe</h3>
                </div>

                <div class="card height-auto">

                    <div class="card-body">

                        <?php if($flash): ?>
                        <div class="alert alert-info">
                            <?= htmlspecialchars($flash) ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="service/add-horaire.php" class="new-added-form">
                            <?php
                            require_once __DIR__ . '/../service/security_helpers.php';
                            csrf_input();
                            ?>

                            <div class="row">

                                <div class="col-xl-4 col-lg-6 col-12 form-group">

                                    <label>Classe *</label>

                                    <select name="class_id" id="class_id" class="select2 form-control" required>

                                        <option value="">
                                            Veuillez sélectionner
                                        </option>

                                        <?php foreach($classes as $c): ?>

                                        <option value="<?= (int)$c['id'] ?>">

                                            <?= htmlspecialchars(
                                                $c['niveau'].' - '.
                                                $c['classe'].' '.
                                                $c['description']
                                            ) ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <hr>

                            <div class="table-responsive">

                                <table class="table table-bordered horaire-table" id="horaireTable">

                                    <thead class="bg-dark text-white">

                                        <tr>

                                            <th>Heure Début</th>
                                            <th>Heure Fin</th>

                                            <th>Lundi</th>
                                            <th>Mardi</th>
                                            <th>Mercredi</th>
                                            <th>Jeudi</th>
                                            <th>Vendredi</th>
                                            <th>Samedi</th>

                                            <th width="70">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <tr>

                                            <td>
                                                <input type="time" name="heure_debut[]" class="form-control" required>
                                            </td>

                                            <td>
                                                <input type="time" name="heure_fin[]" class="form-control" required>
                                            </td>

                                            <td>
                                                <input type="text" name="lundi[]" class="form-control"
                                                    placeholder="Cours">
                                            </td>

                                            <td>
                                                <input type="text" name="mardi[]" class="form-control">
                                            </td>

                                            <td>
                                                <input type="text" name="mercredi[]" class="form-control">
                                            </td>

                                            <td>
                                                <input type="text" name="jeudi[]" class="form-control">
                                            </td>

                                            <td>
                                                <input type="text" name="vendredi[]" class="form-control">
                                            </td>

                                            <td>
                                                <input type="text" name="samedi[]" class="form-control">
                                            </td>

                                            <td class="text-center">

                                                <button type="button" class="btn btn-danger text-white remove-row">

                                                    <i class="fas fa-trash"></i>

                                                </button>

                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                            <div class="mt-3">

                                <button type="button" class="btn btn-primary" id="addRow">

                                    <i class="fas fa-plus"></i>
                                    Ajouter une ligne

                                </button>

                            </div>

                            <div class="mt-4">

                                <button type="submit" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">

                                    Enregistrer l'horaire

                                </button>

                                <button type="reset" class="btn-fill-lg bg-blue-dark btn-hover-yellow">

                                    Réinitialiser

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

                <?php
                $foot = __DIR__ . '/layout/footer.php';
                if (file_exists($foot)) require $foot;
                ?>

            </div>

        </div>

    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
    (function() {

        let addBtn = document.getElementById('addRow');
        let tbody = document.querySelector('#horaireTable tbody');
        let select = document.getElementById('class_id');

        function createRow(data = {}) {

            let tr = document.createElement('tr');

            tr.innerHTML = `
        <td><input type="time" name="heure_debut[]" class="form-control" required value="${data.heure_debut ?? ''}"></td>
        <td><input type="time" name="heure_fin[]" class="form-control" required value="${data.heure_fin ?? ''}"></td>

        <td><input type="text" name="lundi[]" class="form-control" value="${data.lundi ?? ''}"></td>
        <td><input type="text" name="mardi[]" class="form-control" value="${data.mardi ?? ''}"></td>
        <td><input type="text" name="mercredi[]" class="form-control" value="${data.mercredi ?? ''}"></td>
        <td><input type="text" name="jeudi[]" class="form-control" value="${data.jeudi ?? ''}"></td>
        <td><input type="text" name="vendredi[]" class="form-control" value="${data.vendredi ?? ''}"></td>
        <td><input type="text" name="samedi[]" class="form-control" value="${data.samedi ?? ''}"></td>

        <td class="text-center">
            <button type="button" class="btn btn-danger remove-row">
                <i class="fas fa-trash"></i>
            </button>
        </td>
        `;

            tr.querySelector('.remove-row').addEventListener('click', function() {
                if (tbody.querySelectorAll('tr').length > 1) {
                    tr.remove();
                }
            });

            tbody.appendChild(tr);
        }

        function resetTable() {
            tbody.innerHTML = '';
            createRow(); // ligne vide par défaut
        }

        function loadHoraires(classId) {

            if (!classId) return resetTable();

            fetch('service/add-horaire.php?class_id=' + classId)
                .then(res => res.json())
                .then(data => {

                    tbody.innerHTML = '';

                    if (data.length === 0) {
                        createRow();
                        return;
                    }

                    data.forEach(row => createRow(row));
                })
                .catch(() => resetTable());
        }

        // ADD ROW
        addBtn.addEventListener('click', function() {
            createRow();
        });

        // CHANGE CLASS → LOAD DATA
        select.addEventListener('change', function() {
            loadHoraires(this.value);
        });

        // INIT
        createRow();

    })();
    </script>
</body>

</html>