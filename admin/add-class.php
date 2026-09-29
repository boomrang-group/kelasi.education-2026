<?php
declare(strict_types=1);

 // DEBUG handled in db_connect.php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$userId  = $_SESSION['user_id'] ?? null;
$roleRaw = $_SESSION['role'] ?? '';
$role    = strtolower(trim((string)$roleRaw));
$isAdmin = in_array($role, ['admin', 'administrateur'], true);

$pdo = null;
$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur (DB).');
}

$codeEcole = $_SESSION['code_ecole'] ?? '';
$editId = (isset($_GET['id']) && ctype_digit($_GET['id']))
    ? (int)$_GET['id']
    : 0;

$editData = null;

if ($editId > 0) {
    $st = $pdo->prepare("
        SELECT * FROM classes
        WHERE id = :id AND code_ecole = :ce
        LIMIT 1
    ");
    $st->execute([
        ':id' => $editId,
        ':ce' => $codeEcole
    ]);
    $editData = $st->fetch(PDO::FETCH_ASSOC);
}

if (!$codeEcole && $userId) {
    try {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $userId]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
    } catch (Throwable $e) {
        if (DEBUG) error_log('get code_ecole: ' . $e->getMessage());
    }
}

if (!$isAdmin || !$codeEcole) {
    http_response_code(403);
    exit("Accès refusé. (admin requis et code école manquant)");
}

$nivList = $secList = $optList = [];
try {
    $nivList = $pdo->query("SELECT id, description FROM niveau ORDER BY description ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $secList = $pdo->query("SELECT id, description FROM section ORDER BY description ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $optList = $pdo->query("SELECT id, description FROM options ORDER BY description ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if (DEBUG) error_log('chargement listes: ' . $e->getMessage());
}

$flash = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $flash = "✅ Classe créée avec succès.";
    if ($_GET['msg'] === 'error')   $flash = "❌ Erreur lors de la création de la classe.";
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Ajouter une classe</title>
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
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <?php
    $nav = __DIR__ . '/layout/navbar.php';  if (file_exists($nav))  require $nav;
    ?>

        <div class="dashboard-page-one">
            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3>
                        <?= $editData ? 'Modification de la Classe' : 'Création des Classes' ?>
                    </h3>
                </div>

                <div class="card height-auto">
                    <div class="card-body">

                        <?php if ($flash): ?>
                        <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
                        <?php endif; ?>

                        <form class="new-added-form" method="POST"
                            action="service/add-classe.php<?= $editData ? '?id='.(int)$editData['id'] : '' ?>"
                            autocomplete="off">

                            <?php
                                require_once __DIR__ . '/../service/security_helpers.php';
                                csrf_input();
                                ?>

                            <?php if ($editData): ?>
                            <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Niveau *</label>
                                    <select class="select2 form-control" name="niveau" id="niveau" required>
                                        <option value="">Please Select *</option>
                                        <?php foreach ($nivList as $r): ?>
                                        <option value="<?= (int)$r['id'] ?>"
                                            <?= (($editData['niveau'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['description']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Section *</label>
                                    <select class="select2 form-control" name="section" id="section"
                                        <?= empty($editData) ? 'required' : '' ?>>

                                        <option value="">Veuillez sélectionner la section *</option>

                                        <?php foreach ($secList as $r): ?>
                                        <option value="<?= (int)$r['id'] ?>"
                                            <?= (($editData['section'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['description']) ?>
                                        </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Option *</label>
                                    <select class="select2 form-control" name="options" id="options"
                                        <?= empty($editData) ? 'required' : '' ?>>

                                        <option value="">Veuillez sélectionner l'option *</option>
                                        <?php foreach ($optList as $r): ?>
                                        <option value="<?= (int)$r['id'] ?>"
                                            <?= (($editData['options'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['description']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group"></div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Classe *</label>
                                    <input type="number" name="classe" class="form-control" required
                                        value="<?= htmlspecialchars($editData['classe'] ?? '') ?>">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Description *</label>
                                    <select class="select2 form-control" name="description" required>
                                        <option value="">Please Select *</option>
                                        <?php foreach (range('A','Z') as $letter): ?>
                                        <option value="<?= $letter ?>"
                                            <?= (($editData['description'] ?? '') === $letter) ? 'selected' : '' ?>>
                                            <?= $letter ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group"></div>

                                <div class="col-12 form-group mg-t-8">
                                    <button type="submit" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                                        <?= $editData ? 'Modifier la classe' : 'Ajouter la classe' ?>
                                    </button>
                                    <button type="reset" class="btn-fill-lg bg-blue-dark btn-hover-yellow"
                                        onclick="history.back()">Annuler</button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

    <!-- JS dynamique -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const niveauSelect = document.getElementById('niveau');
        const sectionSelect = document.getElementById('section');
        const optionsSelect = document.getElementById('options');

        const sectionBackup = sectionSelect.innerHTML;
        const optionBackup = optionsSelect.innerHTML;

        niveauSelect.addEventListener('change', function() {
            const selectedText = niveauSelect.options[niveauSelect.selectedIndex].text.toLowerCase();

            if (selectedText.includes('primaire') || selectedText.includes('secondaire')) {
                // Désactiver et vider Section et Option
                sectionSelect.innerHTML = '<option value="">N/A</option>';
                sectionSelect.disabled = true;

                optionsSelect.innerHTML = '<option value="">N/A</option>';
                optionsSelect.disabled = true;
            } else {
                // Activer et restaurer Section et Option
                sectionSelect.innerHTML = sectionBackup;
                sectionSelect.disabled = false;

                optionsSelect.innerHTML = optionBackup;
                optionsSelect.disabled = false;
            }
        });
    });
    </script>
</body>

</html>