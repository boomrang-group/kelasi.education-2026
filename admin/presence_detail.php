<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__.'/../database/db_connect.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$codeEcole = $_SESSION['code_ecole'] ?? '';
$eleveId   = (int)($_GET['eleve_id'] ?? 0);

if ($eleveId <= 0) {
    exit('<div class="alert alert-danger">Élève introuvable.</div>');
}

$sql = "
SELECT
    p.date_presence,
    p.statut,

    s.first_name,
    s.last_name,

    c.classe,
    c.description AS classe_description,

    n.description AS niveau,
    sec.description AS section_name,
    opt.description AS option_name

FROM presence_eleve p

INNER JOIN students s
    ON s.id = p.eleve_id

LEFT JOIN classes c
    ON c.id = p.class_id

LEFT JOIN niveau n
    ON n.id = c.niveau

LEFT JOIN section sec
    ON sec.id = c.section

LEFT JOIN options opt
    ON opt.id = c.options

WHERE
    p.eleve_id = :eleve
    AND s.code_ecole = :code_ecole

ORDER BY p.date_presence DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':eleve'      => $eleveId,
    ':code_ecole' => $codeEcole
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    ?>
<div class="alert alert-warning text-center mb-0">
    Aucun détail de présence disponible.
</div>
<?php
    exit;
}

$eleve = $rows[0];

$classe = trim(
    ($eleve['classe'] ?? '') . ' ' .
    ($eleve['classe_description'] ?? '') . ' ' .
    ($eleve['niveau'] ?? '') . ' ' .
    ($eleve['section_name'] ?? '') . ' ' .
    ($eleve['option_name'] ?? '')
);

$totalPresent = 0;
$totalAbsent = 0;

foreach ($rows as $row) {

    if ($row['statut'] == 'present') {
        $totalPresent++;
    } else {
        $totalAbsent++;
    }
}
?>

<div class="row mb-3">

    <div class="col-md-8">
        <h4 class="mb-1">
            <?= h($eleve['first_name'].' '.$eleve['last_name']) ?>
        </h4>

        <span class="text-muted">
            <?= h($classe) ?>
        </span>
    </div>

    <div class="col-md-4 text-right">

        <span class="badge badge-success p-2">
            Présences : <?= $totalPresent ?>
        </span>

        <span class="badge badge-danger p-2">
            Absences : <?= $totalAbsent ?>
        </span>

    </div>

</div>

<div class="table-responsive">

    <table class="table table-bordered table-striped">

        <thead class="bg-primary text-white">

            <tr>
                <th width="40%">Date</th>
                <th width="60%">Statut</th>
            </tr>

        </thead>

        <tbody>

            <?php foreach ($rows as $row): ?>

            <tr>

                <td>
                    <?= date('d/m/Y', strtotime($row['date_presence'])) ?>
                </td>

                <td>

                    <?php if ($row['statut'] == 'present'): ?>

                    <span class="badge badge-success">
                        Présent
                    </span>

                    <?php else: ?>

                    <span class="badge badge-danger">
                        Absent
                    </span>

                    <?php endif; ?>

                </td>

            </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>