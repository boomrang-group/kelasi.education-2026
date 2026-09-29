<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* =========================
   DATABASE
========================= */

$pdo = null;

$dbPaths = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php'
];

foreach ($dbPaths as $path) {

    if (file_exists($path)) {

        require_once $path;
        break;
    }
}

if (!$pdo) {
    die("Connexion DB impossible");
}

/* =========================
   ECOLE
========================= */

$codeEcole = $_SESSION['code_ecole'] ?? '';

if (empty($codeEcole)) {

    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId > 0) {

        $stmt = $pdo->prepare("
            SELECT code_ecole
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $codeEcole = $stmt->fetchColumn() ?: '';

        if (!empty($codeEcole)) {
            $_SESSION['code_ecole'] = $codeEcole;
        }
    }
}

if (empty($codeEcole)) {
    die("Impossible de déterminer l'école de l'utilisateur.");
}

/* =========================================================
   EXPORT CSV
========================================================= */
$isExport = isset($_GET['export']) && $_GET['export'] == '1';

if ($isExport) {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rapport_paiements.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Élève',
        'Classe',
        'Type',
        'À payer',
        'Payé',
        'Reste',
        'Observation'
    ]);
}

/* =========================================================
   FILTRES
========================================================= */
$type = $_GET['type'] ?? 'Minerval';
$sort = $_GET['sort'] ?? 'classe';
$classeFilter = $_GET['classe'] ?? '';

$types = ['Inscription', 'Minerval', 'Autre'];

if (!in_array($type, $types)) {
    $type = 'Minerval';
}

/* =========================================================
   CLASSES
========================================================= */
$sqlClasses = "
SELECT
    c.id,

    CONCAT(
        c.classe, ' ',
        c.description, ' ',
        COALESCE(n.description, ''), ' ',
        COALESCE(s.description, ''), ' ',
        COALESCE(o.description, '')
    ) AS nom

FROM classes c

LEFT JOIN niveau n
ON n.id = c.niveau

LEFT JOIN section s
ON s.id = c.section

LEFT JOIN `options` o
ON o.id = c.`options`

WHERE c.code_ecole = ?

ORDER BY c.classe ASC
";

$stmtClasses = $pdo->prepare($sqlClasses);
$stmtClasses->execute([$codeEcole]);

$classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   CONFIG FRAIS
========================================================= */
$config = [];

$sqlConfig = "
SELECT
    c.id AS classe_id,

    COALESCE(fi.montant, 0) AS inscription,
    COALESCE(m.montant, 0) AS minerval,
    COALESCE(af.montant, 0) AS autre

FROM classes c

LEFT JOIN frais_d_inscription fi
ON fi.classe = c.id
AND fi.code_ecole = c.code_ecole

LEFT JOIN minerval m
ON m.classe = c.id
AND m.code_ecole = c.code_ecole

LEFT JOIN autres_frais af
ON af.classe = c.id
AND af.code_ecole = c.code_ecole

WHERE c.code_ecole = ?
";

$stmtConfig = $pdo->prepare($sqlConfig);
$stmtConfig->execute([$codeEcole]);

while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {

    $config[$row['classe_id']] = [

        'inscription' => (float)$row['inscription'],
        'minerval'    => (float)$row['minerval'],
        'autre'       => (float)$row['autre']
    ];
}

/* =========================================================
   ÉLÈVES
========================================================= */
$sqlEleves = "
SELECT

    p.eleve,
    p.statut,

    s.id,
    s.class_id,

    CONCAT(
        s.first_name,' ',
        s.last_name
    ) AS nom,

    CONCAT_WS(
    ' ',
    NULLIF(TRIM(c.classe),''),
    NULLIF(TRIM(c.description),''),
    NULLIF(TRIM(niv.description),''),
    NULLIF(TRIM(sec.description),''),
    NULLIF(TRIM(opt.description),'')
) AS classe_nom,

    SUM(p.montant_paye) AS total_paye

FROM paiement p

INNER JOIN students s
ON s.id = p.eleve

LEFT JOIN classes c
ON c.id = s.class_id

LEFT JOIN niveau niv
ON niv.id = c.niveau

LEFT JOIN section sec
ON sec.id = c.section

LEFT JOIN `options` opt
ON opt.id = c.options

WHERE

    s.code_ecole = ?
    AND p.is_validated = 1
    AND p.statut = ?

GROUP BY
    s.id,
    s.class_id,
    nom,
    classe_nom

ORDER BY
    classe_nom,
    nom
";

$stmtEleves = $pdo->prepare($sqlEleves);
$stmtEleves->execute([
    $codeEcole,
    $type
]);

$eleves = $stmtEleves->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   FILTRE CLASSE
========================================================= */
if (!empty($classeFilter)) {

    $eleves = array_filter($eleves, function ($e) use ($classeFilter) {

        return (int)$e['class_id'] === (int)$classeFilter;
    });
}

/* =========================================================
   TRI
========================================================= */
usort($eleves, function ($a, $b) use ($sort) {

    if ($sort === 'eleve') {
        return strcmp($a['nom'], $b['nom']);
    }

    return strcmp($a['classe_nom'], $b['classe_nom']);
});

/* =========================================================
   EXPORT DATA
========================================================= */
if ($isExport) {

    foreach ($eleves as $e) {

        $classeId = (int)$e['class_id'];

        $montant = $config[$classeId][strtolower($type)] ?? 0;

        $paye = (float)$e['total_paye'];

        $reste = $montant - $paye;

        fputcsv($output, [

            $e['nom'],
            $e['classe_nom'],
            $type,
            number_format($montant, 2),
            number_format($paye, 2),
            number_format($reste, 2),
            $reste <= 0 ? 'Soldé' : 'Dette'
        ]);
    }

    fclose($output);
    exit;
}
?>

<!doctype html>
<html lang="fr">

<head>

    <meta charset="utf-8">

    <title>Mykelasi | Suivi Paiements</title>

    <link rel="shortcut icon" href="../../img/favicon.png">

    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../style.css">

    <style>
    body {
        background: #f5f7fa;
    }

    .form-control {
        border: 1px solid #dfdfdf !important;
    }

    .badge-classe {
        background: #007bff;
        padding: 6px 10px;
        border-radius: 10px;
        color: #fff;
    }

    .reste-ok {
        color: #198754;
        font-weight: bold;
    }

    .reste-ko {
        color: #dc3545;
        font-weight: bold;
    }

    .badge-retard {
        background: #ffc107;
        padding: 4px 8px;
        border-radius: 8px;
    }

    tr.group-sep td {
        background: #f1f3f5;
        height: 5px;
        border: none;
    }

    .filter-btn a {
        margin-right: 10px;
    }
    </style>
</head>

<body>

    <div class="wrapper">

        <?php require __DIR__.'/layout/navbar.php'; ?>

        <div class="dashboard-page-one">

            <?php require __DIR__.'/layout/sidebar.php'; ?>

            <div class="dashboard-content-one page-container mt-5">

                <h3>
                    <i class="fas fa-chart-line"></i>
                    Suivi des paiements
                </h3>

                <div class="mb-3 filter-btn">
                    <a href="?type=Minerval"
                        class="btn <?= $type=='Minerval' ? 'btn-warning btn-lg' : 'btn-outline-warning btn-lg' ?>">
                        Minerval
                    </a>
                    <a href="?type=Inscription"
                        class="btn <?= $type=='Inscription' ? 'btn-success btn-lg' : 'btn-outline-success btn-lg' ?>">
                        Inscription
                    </a>
                    <a href="?type=Autre"
                        class="btn <?= $type=='Autre' ? 'btn-primary btn-lg' : 'btn-outline-primary btn-lg' ?>">
                        Autres
                    </a>
                </div>

                <form method="GET" class="mb-3 gap-2 form-group">

                    <div class="d-flex align-items-center gap-2 flex-nowrap overflow-auto">

                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

                        <select name="classe" class="form-control" style="width:250px; flex-shrink:0;">

                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $c): ?>

                            <option value="<?= (int)$c['id'] ?>"
                                <?= ((string)$classeFilter === (string)$c['id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($c['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="input-group" style="width:250px; flex-shrink:0; margin-left:1rem;">

                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher élève...">
                        </div>

                        <button class="btn-fill-lg btn-hover-bluedark bg-dark"
                            style="margin-left:1rem;margin-right:1rem;">
                            <i class="fas fa-filter me-1"></i>
                            Filtrer
                        </button>

                        <a href="?type=<?= urlencode($type) ?>&classe=<?= urlencode((string)$classeFilter) ?>&sort=<?= urlencode($sort) ?>&export=1"
                            class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                            <i class="fas fa-file-excel me-1"></i>
                            Exporter
                        </a>
                    </div>

                </form>

                <div class="table-responsive">
                    <table class="table table-hover" id="tableEleves">

                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Type</th>
                                <th>À payer</th>
                                <th>Payé</th>
                                <th>Reste</th>
                                <th>Observation</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($eleves as $e):

$classeId = (int)$e['class_id'];

$montant = $config[$classeId][strtolower($type)] ?? 0;

$paye = (float)$e['total_paye'];

$reste = $montant - $paye;

?>

                            <tr>

                                <td class="nom">
                                    <?= htmlspecialchars($e['nom']) ?>
                                </td>

                                <td>

                                    <span class="badge badge-classe">
                                        <?= htmlspecialchars($e['classe_nom']) ?>
                                    </span>

                                </td>

                                <td><?= htmlspecialchars($type) ?></td>

                                <td><?= number_format($montant, 2) ?> $</td>

                                <td><?= number_format($paye, 2) ?> $</td>

                                <td class="<?= $reste <= 0 ? 'reste-ok' : 'reste-ko' ?>">
                                    <?= number_format($reste, 2) ?> $
                                </td>

                                <td>
                                    <?= $reste <= 0
? '✔ Soldé'
: '<span class="badge-retard">En retard</span>' ?>
                                </td>

                            </tr>

                            <tr class="group-sep">
                                <td colspan="7"></td>
                            </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php require __DIR__ . '/layout/footer.php'; ?>

            </div>
        </div>
    </div>

    <script>
    document.getElementById('searchInput').addEventListener('keyup', function() {

        let val = this.value.toLowerCase();

        document.querySelectorAll("#tableEleves tbody tr").forEach(row => {

            let name = row.querySelector(".nom");

            if (!name) return;

            row.style.display =
                name.innerText.toLowerCase().includes(val) ?
                "" :
                "none";
        });
    });
    </script>

</body>

</html>