<?php
declare(strict_types=1);

session_start();
require_once '../../../database/db_connect.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$code_ecole = $_SESSION['code_ecole'] ?? '';
$classId    = $_SESSION['active_class_id'] ?? null;

if (!$classId) die("Classe active introuvable.");

$today = date('Y-m-d');
$month = date('Y-m');

// MODE
$mode = $_GET['mode'] ?? 'month';

/* =====================
   SAVE PRESENCE
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'take') {

    $data = $_POST['presence'] ?? [];
    $comments = $_POST['commentaire'] ?? [];

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare("
            SELECT id FROM presence_eleve
            WHERE eleve_id=:e AND class_id=:c AND date_presence=:d
        ");

        $insert = $pdo->prepare("
            INSERT INTO presence_eleve
            (eleve_id, class_id, date_presence, statut, commentaire)
            VALUES (:e,:c,:d,:s,:com)
        ");

        $update = $pdo->prepare("
            UPDATE presence_eleve
            SET statut=:s, commentaire=:com
            WHERE eleve_id=:e AND class_id=:c AND date_presence=:d
        ");

        foreach ($data as $eid => $st) {

            if (!in_array($st, ['present','absent','retard'], true)) continue;

            $params = [
                ':e'=>(int)$eid,
                ':c'=>(int)$classId,
                ':d'=>$today,
                ':s'=>$st,
                ':com'=>$comments[$eid] ?? null
            ];

            $check->execute([
                ':e'=>$params[':e'],
                ':c'=>$params[':c'],
                ':d'=>$params[':d']
            ]);

            if ($check->fetchColumn()) {
                $update->execute($params);
            } else {
                $insert->execute($params);
            }
        }

        $pdo->commit();
        header("Location: ?mode=take&ok=1");
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        die($e->getMessage());
    }
}

/* =====================
   ELEVES
===================== */
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM students
    WHERE class_id=:c AND code_ecole=:ce
");
$stmt->execute([':c'=>$classId, ':ce'=>$code_ecole]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   PRESENCE MAP JOUR
===================== */
$stmt = $pdo->prepare("
    SELECT eleve_id, statut, commentaire
    FROM presence_eleve
    WHERE class_id=:c AND date_presence=:d
");
$stmt->execute([':c'=>$classId, ':d'=>$today]);

$map = [];
foreach ($stmt as $r) $map[$r['eleve_id']] = $r;

/* =====================
   STATS MOIS GLOBAL
===================== */
$stmt = $pdo->prepare("
    SELECT 
        SUM(statut='present') p,
        SUM(statut='absent') a,
        SUM(statut='retard') r
    FROM presence_eleve
    WHERE class_id=:c AND DATE_FORMAT(date_presence,'%Y-%m')=:m
");
$stmt->execute([':c'=>$classId, ':m'=>$month]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/* =====================
   DETAIL PAR ELEVE (MOIS)
===================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM presence_eleve
    WHERE class_id=:c AND DATE_FORMAT(date_presence,'%Y-%m')=:m
    ORDER BY date_presence DESC
");
$stmt->execute([':c'=>$classId, ':m'=>$month]);

$group = [];
foreach ($stmt as $r) {
    $group[$r['eleve_id']][] = $r;
}
?>

<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Présence des élèves</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>

    <style>
    .badge-stat {
        font-size: 12px;
        margin: 2px;
        padding: 4px 8px;
    }

    .presence-radio input[type="radio"] {
        display: none;
    }

    .presence-radio label {
        cursor: pointer;
        margin-right: 6px;
        padding: 6px 10px;
        font-size: 12px;
        opacity: 0.6;
        transition: 0.2s;
        border-radius: 20px;
    }

    .presence-radio input[type="radio"]:checked+label {
        opacity: 1;
        transform: scale(1.05);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <?php include '../layout/navbar.php'; ?>
        <div class="dashboard-page-one">
            <?php include '../layout/sidebar.php'; ?>

            <div class="dashboard-content-one">

                <div class="card mt-5">
                    <div class="card-body">

                        <!-- HEADER -->
                        <div class="d-flex justify-content-between mb-3">
                            <h3>Présence élèves</h3>

                            <div>
                                <a href="?mode=take" class="btn btn-md btn-success">Faire présence du jour</a>
                                <a href="?mode=month" class="btn btn-md btn-primary">Présence mois</a>
                            </div>
                        </div>

                        <!-- STATS -->
                        <div class="mb-3">
                            <span class="badge bg-success badge-stat">P: <?= (int)$stats['p'] ?></span>
                            <span class="badge bg-danger badge-stat">A: <?= (int)$stats['a'] ?></span>
                            <span class="badge bg-warning text-dark badge-stat">R: <?= (int)$stats['r'] ?></span>
                        </div>

                        <?php if($mode==='month'): ?>

                        <!-- TABLE -->
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>P</th>
                                    <th>A</th>
                                    <th>R</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach($students as $s):

                                $p=$a=$r=0;

                                if(isset($group[$s['id']])) {
                                    foreach($group[$s['id']] as $d){
                                        if($d['statut']=='present') $p++;
                                        if($d['statut']=='absent') $a++;
                                        if($d['statut']=='retard') $r++;
                                    }
                                }
                                ?>

                                <tr>
                                    <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>

                                    <td><span class="badge bg-success"><?= $p ?></span></td>
                                    <td><span class="badge bg-danger"><?= $a ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?= $r ?></span></td>

                                    <td>
                                        <button class="btn btn-sm btn-primary" data-toggle="modal"
                                            data-target="#m<?= $s['id'] ?>">
                                            Détails
                                        </button>
                                    </td>
                                </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>

                        <!-- MODAL + ACCORDION -->
                        <?php foreach($students as $s): ?>
                        <div class="modal fade" id="m<?= $s['id'] ?>">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5><?= e($s['first_name'].' '.$s['last_name']) ?></h5>
                                    </div>

                                    <div class="modal-body">

                                        <div id="acc<?= $s['id'] ?>">

                                            <?php
                                        if(!empty($group[$s['id']])):

                                        $months = [];
                                        foreach($group[$s['id']] as $d){
                                            $m = date('Y-m', strtotime($d['date_presence']));
                                            $months[$m][] = $d;
                                        }

                                        foreach($months as $m => $rows):
                                        ?>
                                            <div class="card mb-2">

                                                <div class="card-header" data-toggle="collapse"
                                                    data-target="#c<?= $s['id'].$m ?>">
                                                    <?= $m ?>
                                                </div>

                                                <div id="c<?= $s['id'].$m ?>" class="collapse">

                                                    <table class="table table-sm">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Statut</th>
                                                            <th>Commentaire</th>
                                                        </tr>

                                                        <?php foreach($rows as $r): ?>
                                                        <tr>
                                                            <td><?= e($r['date_presence']) ?></td>
                                                            <td><?= e($r['statut']) ?></td>
                                                            <td><?= e($r['commentaire']) ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>

                                                    </table>

                                                </div>
                                            </div>

                                            <?php endforeach; ?>

                                            <?php else: ?>
                                            <p>Aucune donnée</p>
                                            <?php endif; ?>

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php else: ?>

                        <!-- MODE TAKE -->
                        <form method="POST">

                            <table class="table table-striped">
                                <tbody>

                                    <?php foreach($students as $s):
                                    $st = $map[$s['id']]['statut'] ?? '';
                                    $com = $map[$s['id']]['commentaire'] ?? '';
                                    ?>

                                    <tr>
                                        <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>

                                        <td style="min-width:260px">

                                            <div class="presence-radio">

                                                <input type="radio" name="presence[<?= $s['id'] ?>]"
                                                    id="p<?= $s['id'] ?>_present" value="present"
                                                    <?= $st==='present'?'checked':'' ?>>

                                                <label class="badge bg-success" for="p<?= $s['id'] ?>_present">
                                                    Présent
                                                </label>

                                                <input type="radio" name="presence[<?= $s['id'] ?>]"
                                                    id="p<?= $s['id'] ?>_absent" value="absent"
                                                    <?= $st==='absent'?'checked':'' ?>>

                                                <label class="badge bg-danger" for="p<?= $s['id'] ?>_absent">
                                                    Absent
                                                </label>

                                                <input type="radio" name="presence[<?= $s['id'] ?>]"
                                                    id="p<?= $s['id'] ?>_retard" value="retard"
                                                    <?= $st==='retard'?'checked':'' ?>>

                                                <label class="badge bg-warning text-dark" for="p<?= $s['id'] ?>_retard">
                                                    Retard
                                                </label>

                                            </div>

                                        </td>

                                        <td>
                                            <input type="text" name="commentaire[<?= $s['id'] ?>]" class="form-control"
                                                value="<?= e($com) ?>" placeholder="Laissez un remarque">
                                        </td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>
                            </table>

                            <button class="btn btn-md btn-primary">Valider la présence</button>

                        </form>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/plugins.js"></script>
    <script src="../../../js/popper.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script src="../../../js/jquery.counterup.min.js"></script>
    <script src="../../../js/jquery.waypoints.min.js"></script>
    <script src="../../../js/jquery.scrollUp.min.js"></script>
    <script src="../../../js/jquery.dataTables.min.js"></script>
    <script src="../../../js/Chart.min.js"></script>
    <script src="../../../js/main.js"></script>

</body>

</html>