<?php
// mykelasi/admin/all-teacher.php — Liste des enseignants avec aperçu d’affectations par class_subject_teacher.class_id

declare(strict_types=1);

 // DEBUG handled in db_connect.php


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

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId && !$codeEcole) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $codeEcole = (string)($st->fetchColumn() ?: '');
    if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
}
if (!$isAdmin || !$codeEcole) { http_response_code(403); exit('Accès refusé'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Recherche ----
$q = trim((string)($_GET['q'] ?? ''));

// ---- 1) Tous les enseignants (table teacher) ----
$teachers = [];
try {
    $sql = "
      SELECT
        t.id                AS teacher_id,
        t.first_name, t.last_name, t.gender, t.phone, t.email, t.adress,
        t.date_of_joining,
        t.classe            AS single_class_id,
        c.classe            AS classe_name,
        c.description       AS classe_desc,
        n.description       AS niveau,
        s.description       AS section,
        o.description       AS opt
      FROM teacher t
      LEFT JOIN classes  c ON t.classe = c.id
      LEFT JOIN niveau   n ON c.niveau  = n.id
      LEFT JOIN section  s ON c.section = s.id
      LEFT JOIN options  o ON c.options = o.id
      WHERE t.code_ecole = :code
    ";
    $params = [':code'=>$codeEcole];

    if ($q !== '') {
        $sql .= " AND (t.first_name LIKE :q OR t.last_name LIKE :q OR t.email LIKE :q OR t.phone LIKE :q) ";
        $params[':q'] = "%$q%";
    }

    $sql .= " ORDER BY t.last_name, t.first_name, t.id ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $teachers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if (DEBUG) { echo $e->getMessage(); exit; }
}

// ---- 2) Associer les teachers à des users par email (pour obtenir users.id) ----
$emailList = [];
foreach ($teachers as $t) {
    $em = strtolower(trim((string)($t['email'] ?? '')));
    if ($em !== '') $emailList[$em] = true;
}

$userByEmail = []; // email(lower) => [id, username, email, created_at, phone, code_ecole]
if ($emailList) {
    $place = implode(',', array_fill(0, count($emailList), '?'));
    try {
        $st = $pdo->prepare("SELECT id, username, email, password, created_at, phone, code_ecole FROM users WHERE LOWER(email) IN ($place)");
        $st->execute(array_keys($emailList));
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $userByEmail[strtolower($row['email'])] = $row;
        }
    } catch (Throwable $e) {
        if (DEBUG) { echo $e->getMessage(); }
    }
}

// ---- 3) Récupérer les affectations via class_subject_teacher (par teacher_user_id) ----
// NB: On utilise UNIQUEMENT class_subject_teacher pour savoir si le prof est affecté.
// S’il n’y a pas d’affectation, on considérera “Non affecté” (peu importe teacher.classe).
$uids = [];
foreach ($teachers as $t) { 
    $emLower = strtolower((string)($t['email'] ?? ''));
    $u = $userByEmail[$emLower] ?? null;
    $uid = $u ? (int)$u['id'] : 0;

    // ✅ Vérification correcte du compte
    $compteActif = false;

    if ($u && isset($u['password']) && trim($u['password']) !== '') {
        $compteActif = true;
    }

    $em = strtolower(trim((string)($t['email'] ?? '')));
    if ($em !== '' && isset($userByEmail[$em]) && !empty($userByEmail[$em]['id'])) {
        $uids[(int)$userByEmail[$em]['id']] = true;
    }
}
$uids = array_keys($uids);

$affectsByUser = [];   // user_id => ['count'=>int, 'labels'=>string]
if ($uids) {
    $in = implode(',', array_fill(0, count($uids), '?'));
    $sqlA = "
        SELECT 
            cst.teacher_user_id AS uid,
            COUNT(*)            AS nb,
            GROUP_CONCAT(
        DISTINCT CONCAT(
            c.classe, ' ',
            COALESCE(c.description, ''), ' ',
            COALESCE(niv.description, ''), ' ',
            COALESCE(sec.description, ''), ' ',
            COALESCE(opt.description, '')
        )
        ORDER BY c.classe
        SEPARATOR ' | '
        ) AS classes_str
        FROM class_subject_teacher cst
        JOIN classes c ON c.id = cst.class_id
        LEFT JOIN niveau niv ON c.niveau = niv.id
        LEFT JOIN section sec ON c.section = sec.id
        LEFT JOIN options opt ON c.options = opt.id
        WHERE cst.code_ecole = ? AND cst.teacher_user_id IN ($in)
        GROUP BY cst.teacher_user_id
    ";
    try {
        $st = $pdo->prepare($sqlA);
        $st->execute(array_merge([$codeEcole], $uids));
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $affectsByUser[(int)$row['uid']] = [
                'count' => (int)$row['nb'],
                'labels'=> (string)$row['classes_str'],
            ];
        }
    } catch (Throwable $e) {
        if (DEBUG) { echo $e->getMessage(); }
    }
}

// ---- 4) Construire les lignes pour l’affichage ----
$rows = [];
foreach ($teachers as $t) {
    $emLower = strtolower((string)($t['email'] ?? ''));
    $u = $userByEmail[$emLower] ?? null;
    $uid = $u ? (int)$u['id'] : 0;

    $nbClasses  = 0;
    $classesStr = '';
    $affecte    = false;

    if ($uid && isset($affectsByUser[$uid])) {
        $nbClasses  = (int)$affectsByUser[$uid]['count'];
        $classesStr = (string)$affectsByUser[$uid]['labels'];
        $affecte    = $nbClasses > 0;
    }

    // NB: on NE bascule plus sur teacher.classe pour décider “affecté / non affecté”.
    // Le statut d’affectation vient STRICTEMENT de class_subject_teacher.
    // On peut toutefois montrer la classe unique en info secondaire si souhaité (ici non, pour éviter confusion).

    $rows[] = [
        'teacher_id'   => (int)$t['teacher_id'],
        'first_name'   => (string)($t['first_name'] ?? ''),
        'last_name'    => (string)($t['last_name'] ?? ''),
        'gender'       => (string)($t['gender'] ?? ''),
        'adress'       => (string)($t['adress'] ?? ''),
        'phone'        => (string)($t['phone'] ?? ($u['phone'] ?? '')),
        'email'        => (string)($t['email'] ?? ''),
        'user_id'      => $uid,
        'username'     => $u['username'] ?? null,
        'created_at'   => $u['created_at'] ?? null,
        'nb_classes'   => $nbClasses,
        'classes_str'  => $classesStr,
        'affecte'      => $affecte,
        'compte_actif' => $compteActif,
    ];
}

?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Liste des enseignants</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/fullcalendar.min.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <!-- Chart.js (déjà présent en local minifié, mais on garde le tien) -->
    <script src="../js/Chart.min.js"></script>

    <style>
    .card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .05)
    }

    .muted {
        color: #6b7280
    }

    .chip {
        display: inline-block;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: .1rem .55rem;
        font-size: .8rem;
        background: #fafafa
    }

    .chip-ok {
        background: #e8f8ef;
        border-color: #cdeee0;
        color: #1f7a4a;
    }

    .chip-warn {
        background: #fff5dd;
        border-color: #f3dfac;
        color: #8a6200;
    }

    .truncate {
        max-width: 520px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .table td,
    .table th {
        vertical-align: middle;
    }
    </style>
</head>

<body>
    <div id="wrapper" class="wrapper bg-ash">
        <?php $nav=__DIR__.'/layout/navbar.php'; if(file_exists($nav)) require $nav; ?>
        <div class="dashboard-page-one">
            <?php $side=__DIR__.'/layout/sidebar.php'; if(file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="text-uppercase">Enseignants</h3>
                    </div>
                    <div class="d-block gap-2">
                        <a class="btn btn-lg btn-primary" href="affectation.php">+ Nouvelle affectation</a>
                        <a class="btn btn-lg btn-outline-secondary" href="add-teacher.php">+ Créer un enseignant</a>
                    </div>
                </div>

                <div class="card-recherche mb-3">
                    <div class="card-body">
                        <form class="form-inline">
                            <input type="text" name="q" id="searchTable" class="form-control mr-2"
                                placeholder="Rechercher (nom, email, téléphone)">
                            <a class="btn btn-danger ml-2" href="all-teacher.php">Réinitialiser</a>
                        </form>
                    </div>
                </div>

                <div class="card-div">
                    <div class="card-body">
                        <?php if (!$rows): ?>
                        <div class="muted">Aucun enseignant trouvé.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Professeur</th>
                                        <th>Contact</th>
                                        <th>Classes</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>
                                                    <?= e(trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? '')) ?>
                                                </strong>
                                            </div>
                                            <?php if (!empty($r['username'])): ?>
                                            <div class="muted small">Identifiant: <?= e($r['username']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= e($r['email'] ?: '—') ?></div>
                                            <div class="muted"><?= e($r['phone'] ?: '—') ?></div>
                                        </td>
                                        <td>
                                            <div><?php if ($r['affecte']): ?>
                                                <span class="chip chip-ok" style="font-size:12px">Affecté
                                                    (<?= (int)$r['nb_classes'] ?>)</span>
                                                <?php else: ?>
                                                <span class="chip chip-warn">Non affecté</span>
                                                <?php endif; ?>
                                            </div>
                                            <div><?php if ($r['affecte']): ?>
                                                <?= e($r['classes_str']) ?>
                                                <?php else: ?>
                                                <span class="muted">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="truncate">
                                            <?php if ($r['compte_actif']): ?>
                                            <span class="chip chip-ok" style="font-size:14px">Actif</span>
                                            <?php else: ?>
                                            <span class="chip chip-warn" style="font-size:14px">Désactivé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex flex-wrap gap-1">
                                            <?php if ($r['user_id']): ?>
                                            <a class="d-none btn btn-sm btn-outline-primary mr-1 mb-1"
                                                href="detail_prof.php?user_id=<?= (int)$r['user_id'] ?>">Détails</a>
                                            <a class="btn btn-lg btn-primary mr-1 mb-1"
                                                href="affectation.php?teacher_user_id=<?= (int)$r['user_id'] ?>">Voir</a>
                                            <?php else: ?>
                                            <span class="muted mr-2 mb-1"
                                                title="Aucun compte utilisateur associé">—</span>
                                            <a class="btn btn-lg btn-outline-secondary mr-1 mb-1"
                                                href="affectation.php">Affecter</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php $foot=__DIR__.'/layout/footer.php'; if(file_exists($foot)) require $foot; ?>
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
    document.addEventListener("DOMContentLoaded", function() {

        const input = document.getElementById("searchTable");

        input.addEventListener("keyup", function() {

            let valeur = this.value.toLowerCase();

            document.querySelectorAll("table tbody tr").forEach(function(ligne) {

                let texte = ligne.textContent.toLowerCase();

                if (texte.indexOf(valeur) > -1) {
                    ligne.style.display = "";
                } else {
                    ligne.style.display = "none";
                }

            });

        });

    });
    </script>
</body>

</html>