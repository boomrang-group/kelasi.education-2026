<?php
// /mykelasi/my_school/all-school.php
declare(strict_types=1);

 // DEBUG handled in db_connect.php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function cu(): ?array {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'         => (int) $_SESSION['user_id'],
            'role'       => $_SESSION['role']       ?? null,
            'email'      => $_SESSION['email']      ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
        ];
    }
    return null;
}
function uid(): int { $u = cu(); return isset($u['id']) ? (int)$u['id'] : 0; }
function norm_role(?string $r): string { return strtolower(trim((string)$r)); }
function has_role($roles): bool {
    $u = cu(); if (!$u || empty($u['role'])) return false;
    $cur = norm_role($u['role']);
    $arr = is_array($roles) ? $roles : [$roles];
    foreach ($arr as $r) if ($cur === norm_role($r)) return true;
    return false;
}

// -------- PROTECTION --------
if (uid() <= 0) {
    $login = '../login/';
    $redir = (str_ends_with($login, '/') ? $login . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '') : $login . '&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    header("Location: $redir");
    exit;
}

// -------- DB CONNECT --------
$pdo = null;
$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur DB'); }

// -------- Vérification promoteur/manager --------
if (!has_role('manager')) { http_response_code(403); exit('Accès refusé'); }

// -------- Query toutes les écoles --------
$sql = "
SELECT
    e.*,
    COALESCE(cls.cnt_class, 0)    AS nb_classes,
    COALESCE(stu.cnt_students, 0) AS nb_eleves,
    COALESCE(tch.cnt_teachers, 0) AS nb_enseignants,
    COALESCE(pay.total_paye, 0)   AS total_paye
FROM ecoles e
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_class FROM classes GROUP BY code_ecole) cls ON cls.code_ecole = e.code_ecole
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_students FROM students GROUP BY code_ecole) stu ON stu.code_ecole = e.code_ecole
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_teachers FROM teacher GROUP BY code_ecole) tch ON tch.code_ecole = e.code_ecole
LEFT JOIN (
    SELECT s.code_ecole, SUM(p.montant_paye) AS total_paye
    FROM paiement p
    JOIN students s ON s.id = p.eleve
    GROUP BY s.code_ecole
) pay ON pay.code_ecole = e.code_ecole
ORDER BY e.nom_ecole ASC;
";

$schools = [];
$last_error = null;
try {
    $st = $pdo->query($sql);
    $schools = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $last_error = $e->getMessage();
    error_log('all-school query error: ' . $e->getMessage());
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Toutes les écoles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon (chemin depuis /my_school/) -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">

    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">

    <style>
    .table-sm td,
    .table-sm th {
        padding: .5rem .6rem;
    }

    .table td,
    .table th {
        vertical-align: middle;
        text-align: center;
    }
    </style>
</head>

<body>
    <div id="wrapper" class="wrapper bg-ash">
        <?php $nav = __DIR__ . '/layouts/navbar.php'; if (file_exists($nav)) require $nav; ?>
        <div class="dashboard-page-one">
            <?php $side = __DIR__ . '/layouts/sidebar.php'; if (file_exists($side)) require $side; ?>
            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <div class="item-title d-flex align-items-center justify-content-between w-100">
                        <h3 class="mb-0">Toutes mes écoles</h3>
                        <a href="mykelasi-school.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">Créer
                            une école</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12 col-12">
                        <div class="card dashboard-card-eleven">
                            <div class="card-body">
                                <div class="table-box-wrap">
                                    <div class="table-responsive result-table-box">
                                        <table class="table display data-table text-nowrap table-sm">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nom</th>
                                                    <th>Nom admin</th>
                                                    <th>Téléphone</th>
                                                    <th>Nb classes</th>
                                                    <th>Nb élèves</th>
                                                    <th>Nb enseignants</th>
                                                    <!-- <th>Situation financière</th> -->
                                                    <th>Adresse</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(!empty($schools)): ?>
                                                <?php foreach($schools as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(strval($row['id'])) ?></td>
                                                    <td><?= htmlspecialchars(strval($row['nom_ecole'])) ?></td>
                                                    <td><?= htmlspecialchars(trim(strval(($row['nom_responsable'] ?? '') . ' ' . ($row['postnom_responsable'] ?? '')))) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars(strval($row['telephone1'] ?: 'N/A')) ?>
                                                    </td>
                                                    <td><?= number_format((int)$row['nb_classes'],0,',',' ') ?></td>
                                                    <td><?= number_format((int)$row['nb_eleves'],0,',',' ') ?></td>
                                                    <td><?= number_format((int)$row['nb_enseignants'],0,',',' ') ?></td>
                                                    <!-- <td><?= number_format((float)$row['total_paye'],2,',',' ') ?> $</td> -->
                                                    <td><?= htmlspecialchars(strval($row['adress'] ?: 'N/A')) ?></td>
                                                    <td>
                                                        <form method="POST" action="update-status.php">
                                                            <input type="hidden" name="ecole_id"
                                                                value="<?= htmlspecialchars(strval($row['id'])) ?>">
                                                            <select name="statut" class="form-select form-select-sm form-control">
                                                                <?php
                                                                $statuses = ['approuver', 'en attente', 'non approuver'];
                                                                foreach($statuses as $s) {
                                                                    $sel = ($row['statut'] === $s) ? 'selected' : '';
                                                                    echo '<option value="'.htmlspecialchars($s).'" '.$sel.'>'.ucfirst($s).'</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                            <button type="submit"
                                                                class="btn btn-md btn-primary">Modifier</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center">
                                                        <?= $last_error ? 'Erreur: '.$last_error : 'Aucune école trouvée.' ?>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div><!-- /card-body -->
                        </div><!-- /card -->
                    </div>
                </div>

                <footer class="footer-wrap-layout1">
                    <div class="copyright">© MyKelasi <?= date('Y') ?>. Tous droits réservés.</div>
                </footer>
            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.counterup.min.js"></script>
    <script src="../js/jquery.waypoints.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>
</body>

</html>