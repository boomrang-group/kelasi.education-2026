<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ---- DB ----
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

// ---- Auth ----
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = strtolower((string)($_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin','administrateur'], true);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
$mois = date('Y-m');

if ($userId && !$codeEcole) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $codeEcole = (string)($st->fetchColumn() ?: '');
    if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
}
if (!$isAdmin || !$codeEcole) { http_response_code(403); exit('Accès refusé'); }

$classeFilter = $_GET['classe'] ?? '';

// classes (description seulement)
$classes = $pdo->query("
    SELECT 
        c.id,
        TRIM(
            CONCAT(
                COALESCE(c.classe, ''),
                ' ',
                COALESCE(c.description, ''),
                ' ',
                COALESCE(n.description, ''),
                ' ',
                COALESCE(sec.description, ''),
                ' ',
                COALESCE(opt.description, '')
            )
        ) AS description
    FROM classes c
    LEFT JOIN niveau n ON n.id = c.niveau
    LEFT JOIN section sec ON sec.id = c.section
    LEFT JOIN options opt ON opt.id = c.options
    WHERE c.code_ecole = " . $pdo->quote($codeEcole) . "
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// requête présence mois courant
$sql = "
SELECT 
    p.eleve_id,
    s.first_name,
    s.last_name,
    CONCAT_WS(
        ' ',
        NULLIF(TRIM(c.classe), ''),
        NULLIF(TRIM(c.description), ''),
        NULLIF(TRIM(niv.description), ''),
        NULLIF(TRIM(sec.description), ''),
        NULLIF(TRIM(opt.description), '')
    ) AS classe,
    COUNT(CASE WHEN p.statut='present' THEN 1 END) AS total_present,
    COUNT(CASE WHEN p.statut='absent' THEN 1 END) AS total_absent,
    COUNT(p.id) AS total_jours
FROM presence_eleve p
INNER JOIN students s ON s.id = p.eleve_id
INNER JOIN classes c ON c.id = p.class_id
LEFT JOIN niveau niv ON niv.id = c.niveau
LEFT JOIN section sec ON sec.id = c.section
LEFT JOIN options opt ON opt.id = c.options
WHERE s.code_ecole = :ce
AND DATE_FORMAT(p.date_presence,'%Y-%m') = :mois
";

$params = [
    ':ce' => $codeEcole,
    ':mois' => $mois
];

if ($classeFilter) {
    $sql .= " AND c.id = :classe ";
    $params[':classe'] = $classeFilter;
}

$sql .= "
GROUP BY p.eleve_id
ORDER BY s.last_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Présence des élèves | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="row mt-3 mb-3">
                    <div class="card ui-tab-card w-100">
                        <div class="card-body">

                            <h3>Présence des élèves (<?= $mois ?>)</h3>

                            <!-- FILTRE -->
                            <form method="GET" class="mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <select name="classe" class="form-control">
                                            <option value="">-- Toutes les classes --</option>
                                            <?php foreach($classes as $c): ?>
                                            <option value="<?= $c['id'] ?>"
                                                <?= ($classeFilter==$c['id'])?'selected':'' ?>>
                                                <?= h($c['description']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <button class="btn btn-primary">
                                            Filtrer
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- TABLE -->
                            <div class="table-responsive">
                                <table class="table table-striped">

                                    <thead>
                                        <tr>
                                            <th>Élève</th>
                                            <th>Classe</th>
                                            <th>Présences</th>
                                            <th>Absences</th>
                                            <th>Total jours</th>
                                            <th></th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php if($rows): ?>
                                        <?php foreach($rows as $r): ?>
                                        <tr>
                                            <td><?= h($r['first_name'].' '.$r['last_name']) ?></td>
                                            <td><?= h($r['classe']) ?></td>
                                            <td><span class="text-success"><?= $r['total_present'] ?></span></td>
                                            <td><span class="text-danger"><?= $r['total_absent'] ?></span></td>
                                            <td><?= $r['total_jours'] ?></td>

                                            <td>
                                                <button class="btn btn-sm btn-primary btn-detail"
                                                    data-id="<?= $r['eleve_id'] ?>" data-toggle="modal"
                                                    data-target="#modalDetail">
                                                    Détails
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                Aucun enregistrement pour ce mois
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                    </tbody>

                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL -->
        <div class="modal fade" id="modalDetail">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header text-white">
                        <h5>Détails présence élève</h5>
                    </div>

                    <div class="modal-body" id="detailContent">
                        Chargement...
                    </div>

                </div>
            </div>
        </div>

        <script src="../js/jquery-3.3.1.min.js"></script>
        <script src="../js/plugins.js"></script>
        <script src="../js/popper.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/jquery.counterup.min.js"></script>
        <script src="../js/moment.min.js"></script>
        <script src="../js/jquery.waypoints.min.js"></script>
        <script src="../js/jquery.scrollUp.min.js"></script>
        <script src="../js/fullcalendar.min.js"></script>
        <script src="../js/Chart.min.js"></script>
        <script src="../js/main.js"></script>

        <script>
        $('.btn-detail').click(function() {

            let id = $(this).data('id');

            console.log(id);

            $('#detailContent').html('Chargement...');

            $('#detailContent').load(
                'presence_detail.php?eleve_id=' + id,
                function(response, status, xhr) {

                    console.log(status);
                    console.log(response);

                    if (status == "error") {
                        $('#detailContent').html(
                            '<div class="alert alert-danger">Erreur ' + xhr.status + '</div>'
                        );
                    }

                }
            );

        });
        </script>

</body>

</html>