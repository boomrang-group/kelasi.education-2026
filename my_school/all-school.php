<?php
// /mykelasi/my_school/all-school.php
declare(strict_types=1);

// -------- DEBUG ----------
 // DEBUG handled in db_connect.php


// -------- SESSION --------
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// -------- HELPERS LOCAUX (pas d'include externe) --------
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

// -------- PROTECTION: connecté --------
if (uid() <= 0) {
    $login = '../login/';
    $redir = (str_ends_with($login, '/')
        ? $login . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')
        : $login . '&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')
    );
    header("Location: $redir");
    exit;
}

// -------- DB CONNECT (robuste) --------
$pdo = null;
$loaded_db_path = null;
$db_candidates = [
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/database/db_connect.php',
];
foreach ($db_candidates as $cand) {
    if (file_exists($cand)) { $loaded_db_path = $cand; require_once $cand; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (DEBUG) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERREUR: \$pdo non défini. Chemins testés:\n";
        foreach ($db_candidates as $cand) echo " - $cand : " . (file_exists($cand) ? 'OK' : 'absent') . "\n";
    }
    http_response_code(500);
    exit('Erreur serveur (DB).');
}

// -------- RÉSOLUTION RÔLE PROMOTEUR (local) --------
// 1) lire users.role
// 2) si != 'promoteur', vérifier possession d’au moins une école
// 3) si vrai -> forcer la session en 'promoteur'
$USER_ID = uid();
try {
    $st = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id' => $USER_ID]);
    $dbRoleOk = norm_role($st->fetchColumn()) === 'promoteur';

    $st2 = $pdo->prepare("SELECT COUNT(*) FROM ecoles WHERE id_promoteur = :id");
    $st2->execute([':id' => $USER_ID]);
    $ownsSchools = ((int)$st2->fetchColumn()) > 0;

    if ($dbRoleOk || $ownsSchools) {
        $_SESSION['role'] = 'promoteur';
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['role'] = 'promoteur';
        }
    }
} catch (Throwable $e) {
    error_log('all-school resolve role error: ' . $e->getMessage());
}

// -------- PROTECTION: UNIQUEMENT promoteur --------
if (!has_role('promoteur')) {
    http_response_code(403);
    exit('Accès refusé (rôle requis : promoteur).');
}

// -------- Query KPI par école --------
$promoteurId = $USER_ID;
$sql = "
SELECT
    e.id,
    e.nom_ecole,
    e.code_ecole,
    e.nom_responsable,
    e.postnom_responsable,
    e.telephone1,
    e.adress,
    COALESCE(cls.cnt_class, 0)     AS nb_classes,
    COALESCE(stu.cnt_students, 0)  AS nb_eleves,
    COALESCE(tch.cnt_teachers, 0)  AS nb_enseignants,
    COALESCE(pay.total_paye, 0)    AS total_paye
FROM ecoles e
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_class    FROM classes  GROUP BY code_ecole) cls ON cls.code_ecole = e.code_ecole
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_students FROM students GROUP BY code_ecole) stu ON stu.code_ecole = e.code_ecole
LEFT JOIN (SELECT code_ecole, COUNT(*) AS cnt_teachers FROM teacher  GROUP BY code_ecole) tch ON tch.code_ecole = e.code_ecole
LEFT JOIN (
    SELECT s.code_ecole, SUM(p.montant_paye) AS total_paye
    FROM paiement p
    JOIN students s ON s.id = p.eleve
    GROUP BY s.code_ecole
) pay ON pay.code_ecole = e.code_ecole
WHERE e.id_promoteur = :promoteur
ORDER BY e.nom_ecole ASC;
";

$schools = [];
$last_error = null;
try {
    $st = $pdo->prepare($sql);
    $st->execute([':promoteur' => $promoteurId]);
    $schools = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $last_error = $e->getMessage();
    error_log('all-school query error: ' . $e->getMessage());
}

// -------- Panneau DEBUG optionnel --------
if (DEBUG) {
    echo '<div style="position:fixed;z-index:9999;top:10px;right:10px;background:#111;color:#fff;padding:12px;border-radius:8px;font:12px monospace;max-width:50vw">';
    echo '<div><b>DEBUG all-school.php</b></div>';
    echo '<div>user_id    : '.htmlspecialchars((string)$USER_ID).'</div>';
    echo '<div>promoteurId: '.htmlspecialchars((string)$promoteurId).'</div>';
    echo '<div>db_connect : '.htmlspecialchars((string)$loaded_db_path).'</div>';
    echo '<div>schools    : '.count($schools).'</div>';
    if ($last_error) echo '<div style="color:#ff6">PDO error: '.htmlspecialchars($last_error).'</div>';
    echo '</div>';
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Mes écoles</title>
    <meta name="description" content="Liste des écoles du promoteur">
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
    <!--<link rel="stylesheet" href="../fonts/flaticon.css">-->
    <!-- Animate CSS -->
    <link rel="stylesheet" href="../css/animate.min.css">
    <!-- Data Table CSS -->
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">
    <!-- Modernize js -->
    <script src="../js/modernizr-3.6.0.min.js"></script>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php $nav = __DIR__ . '/layouts/navbar.php'; if (file_exists($nav)) require $nav; ?>
        <div class="dashboard-page-one">
            <?php $side = __DIR__ . '/layouts/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <div class="item-title d-flex align-items-center justify-content-between w-100">
                        <h3 class="mb-0">Toutes mes écoles</h3>
                        <a href="mykelasi-school.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">Créer une école</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12 col-12">
                        <div class="card dashboard-card-eleven">
                            <div class="card-body">
                                <div class="table-box-wrap">
                                    <div class="table-responsive result-table-box">
                                        <table class="table display data-table text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nom</th>
                                                    <th>Nom admin</th>
                                                    <th>Téléphone</th>
                                                    <th>Nb classes</th>
                                                    <th>Nb élèves</th>
                                                    <th>Nb enseignants</th>
                                                    <th>Situation financière</th>
                                                    <th>Adresse</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (!empty($schools)): ?>
                                                <?php foreach ($schools as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars((string)$row['id']) ?></td>
                                                        <td><?= htmlspecialchars((string)$row['nom_ecole']) ?></td>
                                                        <td><?= htmlspecialchars(trim(($row['nom_responsable'] ?? '') . ' ' . ($row['postnom_responsable'] ?? ''))) ?></td>
                                                        <td><?= htmlspecialchars($row['telephone1'] ?: 'N/A') ?></td>
                                                        <td><?= number_format((int)$row['nb_classes'], 0, ',', ' ') ?></td>
                                                        <td><?= number_format((int)$row['nb_eleves'], 0, ',', ' ') ?></td>
                                                        <td><?= number_format((int)$row['nb_enseignants'], 0, ',', ' ') ?></td>
                                                        <td><?= number_format((float)$row['total_paye'], 2, ',', ' ') ?> $</td>
                                                        <td><?= htmlspecialchars($row['adress'] ?: 'N/A') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">
                                                        <?= $last_error ? 'Erreur lors du chargement des écoles.' : 'Aucune école trouvée.' ?>
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

    <!-- jquery-->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <!-- Plugins js -->
    <script src="../js/plugins.js"></script>
    <!-- Popper js -->
    <script src="../js/popper.min.js"></script>
    <!-- Bootstrap js -->
    <script src="../js/bootstrap.min.js"></script>
    <!-- Counterup Js -->
    <script src="../js/jquery.counterup.min.js"></script>
    <!-- Waypoints Js -->
    <script src="../js/jquery.waypoints.min.js"></script>
    <!-- Scroll Up Js -->
    <script src="../js/jquery.scrollUp.min.js"></script>
    <!-- Data Table Js -->
    <script src="../js/jquery.dataTables.min.js"></script>
    <!-- Custom Js -->
    <script src="../js/main.js"></script>
</body>
</html>
