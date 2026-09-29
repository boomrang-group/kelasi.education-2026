<?php
// customs/teacher/view/all-students.php
// Liste des élèves de la classe active de l'enseignant (UI améliorée + toolbar + stats + filtres)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

// ---- Sécurité minimale : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

// ---- Contexte session ----
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; // users.id (si présent)
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---- Helpers ----
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function safe_date(?string $s, string $fmt='d/m/Y'){ return $s ? date($fmt, strtotime($s)) : ''; }
function compute_age(?string $dob): ?int {
    if (!$dob) return null;
    $ts = strtotime($dob); if (!$ts) return null;
    $from = new DateTime(date('Y-m-d', $ts));
    $now  = new DateTime('today');
    return (int)$from->diff($now)->y;
}
function file_exists_rel(string $rel): bool {
    $abs = realpath(__DIR__ . '/' . $rel);
    if ($abs === false) $abs = __DIR__ . '/' . $rel;
    return is_file($abs);
}

/** WHERE d’appartenance de l’enseignant */
function buildTeacherWhere(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail): array {
    $where = []; $params = [];

    if ($usersId) { $where[] = 'cst.teacher_user_id = :uid'; $params[':uid'] = $usersId; }
    if (!empty($sessionUsername)) { $where[] = 'cst.username = :uname'; $params[':uname'] = $sessionUsername; }

    // Optionnel : mapping via teacher.email -> teacher.id stocké parfois dans teacher_user_id
    if (!empty($sessionEmail)) {
        try{
            $st = $pdo->prepare("SELECT id FROM teacher WHERE email=:em LIMIT 1");
            $st->execute([':em'=>$sessionEmail]);
            if ($tid = $st->fetchColumn()) { $where[] = 'cst.teacher_user_id = :tid'; $params[':tid']=(int)$tid; }
        }catch(Throwable $e){}
    }

    return [$where,$params];
}

/** Vérifie qu’une classe appartient à l’enseignant */
function classBelongsToTeacher(PDO $pdo, int $classId, ?int $usersId, ?string $username, ?string $email, ?string $codeEcole): bool {
    [$where,$params] = buildTeacherWhere($pdo,$usersId,$username,$email);
    if (!$where) return false;
    $sql = "SELECT 1 FROM class_subject_teacher cst WHERE cst.class_id=:cid AND (".implode(' OR ',$where).")";
    $params[':cid'] = $classId;
    if (!empty($codeEcole)) { $sql .= " AND cst.code_ecole=:ce"; $params[':ce']=$codeEcole; }
    $st = $pdo->prepare($sql); $st->execute($params);
    return (bool)$st->fetchColumn();
}

/** Trouve une classe par défaut si pas de session active */
function findClassIdForTeacher(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): ?int {
    [$where,$params] = buildTeacherWhere($pdo,$usersId,$sessionUsername,$sessionEmail);
    if (!$where) return null;

    // priorité avec code_ecole
    if (!empty($codeEcole)) {
        $sql = "SELECT class_id FROM class_subject_teacher cst WHERE (".implode(' OR ',$where).") AND cst.code_ecole=:ce ORDER BY cst.id DESC LIMIT 1";
        $p = $params; $p[':ce']=$codeEcole;
        $st = $pdo->prepare($sql); $st->execute($p);
        if ($cid = $st->fetchColumn()) return (int)$cid;
    }

    // sinon sans code_ecole
    $sql2 = "SELECT class_id FROM class_subject_teacher cst WHERE (".implode(' OR ',$where).") ORDER BY cst.id DESC LIMIT 1";
    $st2 = $pdo->prepare($sql2); $st2->execute($params);
    $cid2 = $st2->fetchColumn();
    return $cid2 ? (int)$cid2 : null;
}

// ---------- Résolution de la classe ----------
$alert      = '';
$classId    = null;
$classeData = null;
$students   = [];

try {
    // 1) Classe active en session (si valide)
    if (!empty($_SESSION['active_class_id']) && ctype_digit((string)$_SESSION['active_class_id'])) {
        $candidate = (int)$_SESSION['active_class_id'];
        if ($candidate>0 && classBelongsToTeacher($pdo,$candidate,$userId,$username,$email,$code_ecole)) {
            $classId = $candidate;
        } else {
            unset($_SESSION['active_class_id']);
        }
    }

    // 2) Sinon, détection auto
    if (!$classId) {
        $classId = findClassIdForTeacher($pdo,$userId,$username,$email,$code_ecole);
        if (!$classId) {
            $alert = '<div class="alert alert-warning">Aucune classe n’est encore associée à votre compte enseignant.</div>';
        } else {
            $_SESSION['active_class_id'] = $classId;
        }
    }
} catch (Throwable $e) {
    $alert = '<div class="alert alert-danger">Erreur init : '.e($e->getMessage()).'</div>';
}

// ---------- Charger infos classe + élèves ----------
if ($classId) {
    try {
        // Fiche classe
        $stc = $pdo->prepare("
            SELECT c.id, c.classe, c.description,
                   n.description AS niveau, s.description AS section, o.description AS options
              FROM classes c
              LEFT JOIN niveau  n ON c.niveau  = n.id
              LEFT JOIN section s ON c.section = s.id
              LEFT JOIN options o ON c.options = o.id
             WHERE c.id=:cid
        ");
        $stc->execute([':cid'=>$classId]);
        $classeData = $stc->fetch(PDO::FETCH_ASSOC) ?: null;

        // Élèves
        $sqlStu = "SELECT id AS students_id, first_name, last_name, gender, phone, email, date_of_birth
                     FROM students
                    WHERE class_id = :cid";
        $paramsStu = [':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlStu .= " AND code_ecole = :ce"; $paramsStu[':ce']=$code_ecole; }
        $sqlStu .= " ORDER BY last_name, first_name";
        $sts = $pdo->prepare($sqlStu); $sts->execute($paramsStu);
        $students = $sts->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $alert = '<div class="alert alert-danger">Erreur lors du chargement : '.e($e->getMessage()).'</div>';
    }
}

// ---------- Stats sexe ----------
$total = count($students);
$cntM = $cntF = $cntO = 0;
foreach ($students as $s) {
    $g = strtoupper(trim((string)($s['gender'] ?? '')));
    if ($g === 'M') $cntM++;
    elseif ($g === 'F') $cntF++;
    else $cntO++;
}

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>MyKelasi | Mes élèves</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon & CSS -->
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
        .muted{color:#6c757d}
        .chip{display:inline-block;border:1px solid #e5e7eb;border-radius:999px;padding:.1rem .6rem;font-size:.8rem;background:#fafafa}
        .avatar-sm{width:32px;height:32px;border-radius:50%;object-fit:cover}
        .toolbar .form-control{height:36px}
        .toolbar .btn{height:36px}
        .badge-soft{background:#f1f3f5;color:#495057;border-radius:.5rem;padding:.15rem .5rem;font-size:.75rem}
    </style>
</head>

<body>
<div id="preloader" class="d-none"></div>

<div id="wrapper" class="wrapper bg-ash">
    <?php require_once('../layout/navbar.php'); ?>

    <div class="dashboard-page-one">
        <?php require_once('../layout/sidebar.php'); ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3>Liste des élèves</h3>
                <?php if ($classeData): ?>
                    <ul>
                        <li><a href="#">Classe active</a></li>
                        <li>
                            <?= e(trim(
                                ($classeData['classe'] ?? '') . ' ' .
                                ($classeData['description'] ?? '') . ' ' .
                                ($classeData['niveau'] ?? '') . ' ' .
                                ($classeData['section'] ?? '') . ' ' .
                                ($classeData['options'] ?? '')
                            )) ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($alert) echo $alert; ?>

            <!-- Mini KPIs -->
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><span class="badge-soft">Total : <?= (int)$total ?></span></div>
                <div class="col-md-3 mb-2"><span class="badge-soft">Filles : <?= (int)$cntF ?></span></div>
                <div class="col-md-3 mb-2"><span class="badge-soft">Garçons : <?= (int)$cntM ?></span></div>
                <div class="col-md-3 mb-2"><span class="badge-soft">Autre/NR : <?= (int)$cntO ?></span></div>
            </div>

            <div class="card height-auto">
                <div class="card-body">

                    <!-- Toolbar -->
                    <div class="toolbar d-flex flex-wrap align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group mr-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input id="globalSearch" type="text" class="form-control" placeholder="Rechercher...">
                            </div>

                            <select id="genderFilter" class="form-control mr-2" style="min-width:160px;">
                                <option value="">— Tous les sexes —</option>
                                <option value="F">Filles</option>
                                <option value="M">Garçons</option>
                                <option value="Autre">Autre/NR</option>
                            </select>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button id="btnExportCsv" class="btn btn-outline-primary mr-2"><i class="fas fa-file-export mr-1"></i> Export CSV</button>
                            <button id="btnPrint" class="btn btn-outline-secondary"><i class="fas fa-print mr-1"></i> Imprimer</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="studentsTable" class="table display text-nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Photo</th>
                                    <th>Nom</th>
                                    <th>Sexe</th>
                                    <th>Date de naissance</th>
                                    <th>Téléphone</th>
                                    <th>E-mail</th>
                                    <!-- <th></th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($students)): ?>
                                    <?php foreach ($students as $s):
                                        $sid   = (int)($s['students_id'] ?? 0);
                                        $name  = trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''));
                                        $gender= strtoupper(trim((string)($s['gender'] ?? '')));
                                        $genderOut = ($gender==='M' ? 'M' : ($gender==='F' ? 'F' : 'Autre'));
                                        $dob   = $s['date_of_birth'] ?? null;
                                        $age   = compute_age($dob);
                                        $phone = $s['phone'] ?? 'N/A';
                                        $mail  = $s['email'] ?? '';

                                        $relImg = "../../../uploads/students/{$sid}.jpg";
                                        $imgSrc = file_exists_rel($relImg) ? $relImg : "../../../img/figure/student.png";
                                    ?>
                                    <tr>
                                        <td><?= e((string)$sid) ?></td>
                                        <td class="text-center"><img class="avatar-sm" src="<?= e($imgSrc) ?>" alt=""></td>
                                        <td><?= e($name ?: 'N/A') ?></td>
                                        <td data-gender="<?= e($genderOut) ?>"><?= e($genderOut) ?></td>
                                        <td><?= e(safe_date($dob)) ?></td>
                                        <td><?= e($phone) ?></td>
                                        <td><?= e($mail) ?></td>
                                        <!-- <td>
                                            <a class="btn btn-sm btn-outline-primary mb-1" href="../view/student_show.php?id=<?= (int)$sid ?>">
                                                <i class="fas fa-user mr-1"></i> Voir
                                            </a>
                                            <a class="btn btn-sm btn-outline-secondary mb-1" href="../view/chat.php?to=student&id=<?= (int)$sid ?>">
                                                <i class="fas fa-paper-plane mr-1"></i> Message
                                            </a>
                                        </td> -->
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center">Aucun élève trouvé.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <?php require_once('../layout/footer.php'); ?>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/jquery.scrollUp.min.js"></script>
<script src="../../../js/jquery.dataTables.min.js"></script>
<script src="../../../js/main.js"></script>
<script>
(function(){
    // DataTable FR
    var dt = $('#studentsTable').DataTable({
        pageLength: 10,
        lengthChange: false,
        order: [[2,'asc']],
        responsive: true,
        language: {
            search: "Rechercher :", info: "Affichage _START_ à _END_ sur _TOTAL_",
            infoEmpty: "Aucun résultat", infoFiltered: "(filtré de _MAX_ au total)",
            zeroRecords: "Aucun résultat trouvé",
            paginate: { previous: "Préc.", next: "Suiv." }
        },
        columnDefs: [
            { orderable: false, targets: [1,8] }
        ]
    });

    // Recherche globale
    $('#globalSearch').on('keyup change', function(){ dt.search(this.value).draw(); });

    // Filtre par sexe (colonne 3)
    $('#genderFilter').on('change', function(){
        var v = this.value;
        if (!v) {
            dt.column(3).search('').draw();
        } else if (v === 'Autre') {
            // On filtre "Autre/NR"
            dt.column(3).search('Autre', true, false).draw();
        } else {
            dt.column(3).search('^' + v + '$', true, false).draw();
        }
    });

    // Export CSV (colonnes visibles)
    function tableToCSV() {
        var rows = dt.rows({search:'applied'}).nodes();
        var headers = [];
        $('#studentsTable thead th').each(function(){
            headers.push($(this).text().trim());
        });
        // Exclure la dernière colonne (Actions)
        headers.pop();

        var csv = [];
        csv.push(headers.join(','));

        $(rows).each(function(){
            var cells = $(this).find('td');
            var row = [];
            cells.each(function(i){
                if (i === cells.length - 1) return; // skip actions
                var text = $(this).text().trim().replace(/\s+/g,' ');
                // Échapper les virgules/doubles-quotes
                if (text.indexOf(',') !== -1 || text.indexOf('"') !== -1) {
                    text = '"' + text.replace(/"/g,'""') + '"';
                }
                row.push(text);
            });
            csv.push(row.join(','));
        });

        var blob = new Blob([csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'eleves_classe_<?=
            isset($classeData['classe']) ? preg_replace("/[^a-z0-9_-]+/i","_",$classeData['classe']) : "liste";
        ?>.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    $('#btnExportCsv').on('click', tableToCSV);

    // Impression
    $('#btnPrint').on('click', function(){ window.print(); });
})();
</script>
</body>
</html>
