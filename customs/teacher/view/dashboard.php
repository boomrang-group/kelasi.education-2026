<?php
// customs/teacher/view/dashboard.php
// Tableau de bord enseignant — KPIs + Charts + Bascule classe (session)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }

// ---- Sécurité minimale ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

// ---- Contexte session ----
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;   // users.id (si dispo)
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---- Helpers ----
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
function safe_date(?string $s, string $fmt='d/m/Y'){ return $s ? date($fmt, strtotime($s)) : ''; }

/** WHERE de rattachement prof */
function buildTeacherWhere(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail): array {
    $where = []; $params = [];
    $candidateIds = [];
    if ($usersId) $candidateIds[] = (int)$usersId;

    if ($sessionEmail) {
        try{
            $stT = $pdo->prepare("SELECT id FROM teacher WHERE email=:em LIMIT 1");
            $stT->execute([':em'=>$sessionEmail]);
            if ($tid=$stT->fetchColumn()) $candidateIds[] = (int)$tid;
        }catch(Throwable $e){}
    }
    if ($candidateIds) {
        $in = [];
        foreach ($candidateIds as $i=>$val) { $ph=':tid'.$i; $in[]=$ph; $params[$ph]=$val; }
        $where[] = 'cst.teacher_user_id IN ('.implode(',', $in).')';
    }
    if (!empty($sessionUsername)) { $where[]='cst.username=:uname'; $params[':uname']=$sessionUsername; }
    return [$where,$params];
}

/** Classes affectées */
function listAssignedClasses(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): array {
    [$where,$params] = buildTeacherWhere($pdo,$usersId,$sessionUsername,$sessionEmail);
    if (!$where) return [];
    $sql = "
        SELECT c.id, c.classe, c.description,
               n.description AS niveau, s.description AS section, o.description AS options,
               cst.created_at
          FROM class_subject_teacher cst
          JOIN classes c ON c.id=cst.class_id
          LEFT JOIN niveau  n ON c.niveau=n.id
          LEFT JOIN section s ON c.section=s.id
          LEFT JOIN options o ON c.options=o.id
         WHERE (".implode(' OR ', $where).")
    ";
    if (!empty($codeEcole)) { $sql.=" AND cst.code_ecole=:ce"; $params[':ce']=$codeEcole; }
    $sql.=" ORDER BY n.description, s.description, o.description, c.classe, c.description";
    $st=$pdo->prepare($sql); $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Classe par défaut */
function findDefaultClassId(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): ?int {
    [$where,$params] = buildTeacherWhere($pdo,$usersId,$sessionUsername,$sessionEmail);
    if (!$where) return null;
    if (!empty($codeEcole)) {
        $sql="SELECT class_id FROM class_subject_teacher cst WHERE (".implode(' OR ',$where).") AND cst.code_ecole=:ce ORDER BY cst.id DESC LIMIT 1";
        $p=$params; $p[':ce']=$codeEcole; $st=$pdo->prepare($sql); $st->execute($p);
        if ($cid=$st->fetchColumn()) return (int)$cid;
    }
    $sql2="SELECT class_id FROM class_subject_teacher cst WHERE (".implode(' OR ',$where).") ORDER BY cst.id DESC LIMIT 1";
    $st2=$pdo->prepare($sql2); $st2->execute($params);
    $cid=$st2->fetchColumn(); return $cid ? (int)$cid : null;
}

/** Vérifie appartenance classe → enseignant */
function classBelongsToTeacher(PDO $pdo, int $classId, ?int $usersId, ?string $username, ?string $email, ?string $codeEcole): bool {
    [$where,$params] = buildTeacherWhere($pdo,$usersId,$username,$email);
    if (!$where) return false;
    $sql="SELECT 1 FROM class_subject_teacher cst WHERE cst.class_id=:cid AND (".implode(' OR ',$where).")";
    $params[':cid']=$classId;
    if (!empty($codeEcole)) { $sql.=" AND cst.code_ecole=:ce"; $params[':ce']=$codeEcole; }
    $st=$pdo->prepare($sql); $st->execute($params);
    return (bool)$st->fetchColumn();
}

// ===================== POST: bascule de classe =====================
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='switch_class') {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $msg = '<div class="alert alert-danger">CSRF invalide.</div>';
    } else {
        $newClassId = (isset($_POST['class_id']) && ctype_digit((string)$_POST['class_id'])) ? (int)$_POST['class_id'] : 0;
        if ($newClassId<=0) {
            $msg = '<div class="alert alert-danger">Classe invalide.</div>';
        } else {
            try {
                if (classBelongsToTeacher($pdo,$newClassId,$userId,$username,$email,$code_ecole)) {
                    $_SESSION['active_class_id']=$newClassId;
                    $msg = '<div class="alert alert-success">Classe active mise à jour.</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Cette classe ne vous est pas affectée.</div>';
                }
            } catch(Throwable $e) {
                $msg = '<div class="alert alert-danger">Erreur bascule : '.e($e->getMessage()).'</div>';
            }
        }
    }
}

// ===================== Résolution contexte & données =====================
$assigned   = [];
$classeData = null;
$classId    = null;
$students   = [];
$nbEleves   = 0;
$nbPDF      = 0;
$nbVideo    = 0;
$nbAudio    = 0;
$alert      = '';

try { $assigned = listAssignedClasses($pdo,$userId,$username,$email,$code_ecole); } catch(Throwable $e){
    $assigned=[]; $alert = '<div class="alert alert-danger">Erreur affectations : '.e($e->getMessage()).'</div>';
}
if (!empty($_SESSION['active_class_id'])) {
    $candidate=(int)$_SESSION['active_class_id'];
    if ($candidate>0 && classBelongsToTeacher($pdo,$candidate,$userId,$username,$email,$code_ecole)) { $classId=$candidate; }
    else { unset($_SESSION['active_class_id']); }
}
if (!$classId) {
    $classId = findDefaultClassId($pdo,$userId,$username,$email,$code_ecole);
    if ($classId) $_SESSION['active_class_id']=$classId;
}

if ($classId) {
    try {
        // Fiche classe
        $stc=$pdo->prepare("
            SELECT c.id, c.classe, c.description,
                   n.description AS niveau, s.description AS section, o.description AS options
              FROM classes c
              LEFT JOIN niveau  n ON c.niveau=n.id
              LEFT JOIN section s ON c.section=s.id
              LEFT JOIN options o ON c.options=o.id
             WHERE c.id=:cid
        ");
        $stc->execute([':cid'=>$classId]);
        $classeData=$stc->fetch(PDO::FETCH_ASSOC) ?: null;

        // Elèves (liste pour tableau)
        $sqlStu="SELECT id AS students_id, first_name, last_name, gender, phone, email, date_of_birth
                   FROM students WHERE class_id=:cid";
        $pStu=[':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlStu.=" AND code_ecole=:ce"; $pStu[':ce']=$code_ecole; }
        $sqlStu.=" ORDER BY last_name, first_name";
        $sts=$pdo->prepare($sqlStu); $sts->execute($pStu);
        $students=$sts->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Compteurs basiques (KPIs)
        $sqlCnt="SELECT COUNT(*) FROM students WHERE class_id=:cid";
        $pCnt=[':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlCnt.=" AND code_ecole=:ce"; $pCnt[':ce']=$code_ecole; }
        $stCnt=$pdo->prepare($sqlCnt); $stCnt->execute($pCnt); $nbEleves=(int)$stCnt->fetchColumn();

        foreach ([['pdfs','uploaded_at','nbPDF'], ['videos','uploaded_at','nbVideo'], ['audios','uploaded_at','nbAudio']] as $def) {
            [$table,$dateCol,$var] = $def;
            try {
                $sql="SELECT COUNT(*) FROM {$table} WHERE class=:cid";
                $p=[':cid'=>$classId];
                if (!empty($code_ecole)) { $sql.=" AND code_ecole=:ce"; $p[':ce']=$code_ecole; }
                $st=$pdo->prepare($sql); $st->execute($p);
                ${$var}=(int)$st->fetchColumn();
            } catch(Throwable $e){ ${$var}=0; }
        }
    } catch(Throwable $e){
        $alert = '<div class="alert alert-danger">Erreur de chargement : '.e($e->getMessage()).'</div>';
    }
} else {
    if (!$alert) $alert = '<div class="alert alert-warning">Aucune classe n’est associée à votre compte enseignant.</div>';
}

// ===================== Données pour CHARTS =====================
// 1) Répartition contenus (PDF/VIDEO/AUDIO) — déjà dans KPIs, on réutilise
$chartContent = [
    'labels' => ['PDF','Vidéo','Audio'],
    'data'   => [ (int)$nbPDF, (int)$nbVideo, (int)$nbAudio ],
];

// 2) Sex-ratio élèves
$genderData = ['M'=>0,'F'=>0,'Autre'=>0];
if ($classId) {
    try {
        $sqlG="SELECT UPPER(TRIM(COALESCE(gender,''))) g, COUNT(*) n FROM students WHERE class_id=:cid";
        $pG=[':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlG.=" AND code_ecole=:ce"; $pG[':ce']=$code_ecole; }
        $sqlG.=" GROUP BY UPPER(TRIM(COALESCE(gender,'')))";
        $stG=$pdo->prepare($sqlG); $stG->execute($pG);
        while($r=$stG->fetch(PDO::FETCH_ASSOC)){
            $g=$r['g'] ?: 'AUTRE';
            if (!in_array($g,['M','F'])) $g='AUTRE';
            $genderData[$g]=(int)$r['n'];
        }
    } catch(Throwable $e){}
}
$chartGender = [
    'labels' => ['Garçons','Filles','Autre/Non renseigné'],
    'data'   => [ $genderData['M'], $genderData['F'], $genderData['Autre'] ],
];

// 3) Activité Quiz sur 6 derniers mois (créés vs soumis)
function lastMonths(int $count=6): array {
    $out=[]; for($i=$count-1;$i>=0;$i--){ $out[] = date('Y-m', strtotime("-{$i} months")); } return $out;
}
$months = lastMonths(6);
$quizCreated = array_fill(0,count($months),0);
$quizSubmitted = array_fill(0,count($months),0);

if ($classId) {
    // Créés (quizzes.created_at)
    try{
        $sqlQ="SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM quizzes WHERE class_id=:cid";
        $pQ=[':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlQ.=" AND code_ecole=:ce"; $pQ[':ce']=$code_ecole; }
        $sqlQ.=" GROUP BY DATE_FORMAT(created_at,'%Y-%m')";
        $stQ=$pdo->prepare($sqlQ); $stQ->execute($pQ);
        $map=[]; while($r=$stQ->fetch(PDO::FETCH_ASSOC)) $map[$r['m']]=(int)$r['n'];
        foreach ($months as $i=>$m) { $quizCreated[$i] = $map[$m] ?? 0; }
    } catch(Throwable $e){}

    // Soumissions (quiz_submissions.submitted_at)
    try{
        $sqlS="SELECT DATE_FORMAT(submitted_at,'%Y-%m') m, COUNT(*) n FROM quiz_submissions WHERE class_id=:cid";
        $pS=[':cid'=>$classId];
        if (!empty($code_ecole)) { $sqlS.=" AND code_ecole=:ce"; $pS[':ce']=$code_ecole; }
        $sqlS.=" GROUP BY DATE_FORMAT(submitted_at,'%Y-%m')";
        $stS=$pdo->prepare($sqlS); $stS->execute($pS);
        $map2=[]; while($r=$stS->fetch(PDO::FETCH_ASSOC)) $map2[$r['m']]=(int)$r['n'];
        foreach ($months as $i=>$m) { $quizSubmitted[$i] = $map2[$m] ?? 0; }
    } catch(Throwable $e){}
}
$chartQuiz = [
    'labels' => array_map(fn($ym)=>date('M Y', strtotime($ym.'-01')), $months),
    'created'=> $quizCreated,
    'submitted'=>$quizSubmitted,
];

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title>MyKelasi | Tableau de bord enseignant</title>
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
        .chip{display:inline-block;border:1px solid #e5e7eb;border-radius:999px;padding:.1rem .6rem;font-size:.8rem;background:#fafafa}
        .kpi-card{border:0;border-radius:1rem;box-shadow:0 12px 24px rgba(0,0,0,.06)}
        .kpi-icon{font-size:1.3rem; opacity:.7}
        .avatar-sm{width:32px;height:32px;border-radius:50%;object-fit:cover}
        .muted{color:#6c757d}
    </style>
</head>
<body>
<div id="preloader" class="d-none"></div>

<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area d-flex justify-content-between align-items-center">
                <div>
                    <h3 style="text-transform: uppercase;">Tableau de bord enseignant</h3>
                    <?php if ($classeData): ?>
                        <ul>
                            <li><a href="#">Classe active</a></li>
                            <li><?= e(trim(($classeData['classe']??'').' '.($classeData['description']??'').' '.($classeData['niveau']??'').' '.($classeData['section']??'').' '.($classeData['options']??''))) ?></li>
                        </ul>
                    <?php endif; ?>
                </div>
                <div>
                    <button class="btn btn-lg btn-primary" data-toggle="modal" data-target="#switchModal">Basculer</button>
                </div>
            </div>

            <?= $msg ?: '' ?>
            <?php if ($alert) echo $alert; ?>

            <!-- KPIs -->
            <div class="row">
                <div class="col-3-xxl col-lg-3 col-sm-6 col-12">
                    <div class="cards kpi-card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-uppercase small muted">Élèves</div>
                                <div class="h4 mb-0"><?= (int)$nbEleves ?></div>
                            </div>
                            <i class="flaticon-classmates kpi-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-3-xxl col-lg-3 col-sm-6 col-12">
                    <div class="cards kpi-card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-uppercase small muted">PDF</div>
                                <div class="h4 mb-0"><?= (int)$nbPDF ?></div>
                            </div>
                            <i class="flaticon-file kpi-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-3-xxl col-lg-3 col-sm-6 col-12">
                    <div class="cards kpi-card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-uppercase small muted">Vidéos</div>
                                <div class="h4 mb-0"><?= (int)$nbVideo ?></div>
                            </div>
                            <i class="flaticon-play-button kpi-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-3-xxl col-lg-3 col-sm-6 col-12">
                    <div class="cards kpi-card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-uppercase small muted">Audios</div>
                                <div class="h4 mb-0"><?= (int)$nbAudio ?></div>
                            </div>
                            <i class="flaticon-microphone kpi-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row mt-3">
                <div class="col-lg-6 mb-3">
                    <div class="cards h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2"><h5 class="mb-0">Répartition des contenus</h5><span class="muted small"><?= (int)($nbPDF+$nbVideo+$nbAudio) ?> items</span></div>
                            <canvas id="chartContent"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="cards h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2"><h5 class="mb-0">Sexe des élèves</h5><span class="muted small"><?= (int)$nbEleves ?> élèves</span></div>
                            <canvas id="chartGender"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <div class="cards h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2"><h5 class="mb-0">Activité Quiz (6 derniers mois)</h5></div>
                            <canvas id="chartQuiz"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des élèves -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="cards dashboard-card-eleven">
                        <div class="card-body">
                            <div class="heading-layout1">
                                <div class="item-title"><h3>Mes élèves</h3></div>
                                <?php if ($classId): ?>
                                    <div><span class="chip">Classe #<?= (int)$classId ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="table-box-wrap">
                                <div class="table-responsive student-table-box">
                                    <table class="table display data-table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Photo</th>
                                                <th>Nom</th>
                                                <th>Sexe</th>
                                                <th>Date de naissance</th>
                                                <th>Téléphone</th>
                                                <th>E-mail</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($students)): ?>
                                            <?php foreach ($students as $s): ?>
                                            <tr>
                                                <td><?= e((string)($s['students_id'] ?? 'N/A')) ?></td>
                                                <td class="text-center">
                                                    <img src="../../../img/figure/student.png" alt="student" class="avatar-sm">
                                                </td>
                                                <td><?= e(trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''))) ?></td>
                                                <td><?= e($s['gender'] ?? 'N/A') ?></td>
                                                <td><?= e(!empty($s['date_of_birth']) ? date('d/m/Y', strtotime($s['date_of_birth'])) : 'N/A') ?></td>
                                                <td><?= e($s['phone'] ?? 'N/A') ?></td>
                                                <td><?= e($s['email'] ?? '') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="7" class="text-center">Aucun élève trouvé.</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<!-- MODAL: Basculer de classe -->
<div class="modal fade" id="switchModal" tabindex="-1" role="dialog" aria-labelledby="switchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <form method="post" class="modal-content" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="switch_class">
      <div class="modal-header">
        <h5 class="modal-title" id="switchModalLabel">Basculer de classe</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <?php if (!$assigned): ?>
            <div class="alert alert-warning mb-0">Aucune classe ne vous est affectée pour le moment.</div>
        <?php else: ?>
            <div class="form-group">
                <label>Choisissez votre classe active</label>
                <select name="class_id" class="form-control" required>
                    <?php foreach ($assigned as $c):
                        $lbl = trim(($c['classe']??'').' '.($c['description']??'').' '.($c['niveau']??'').' '.($c['section']??'').' '.($c['options']??'')); if ($lbl==='') $lbl='Classe #'.(int)$c['id'];
                        $sel = (($classId && (int)$c['id']===$classId) ? 'selected' : '');
                    ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-1">Persisté pour toute votre session.</small>
            </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($assigned): ?><button type="submit" class="btn btn-primary">Valider</button><?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Fermer</button>
      </div>
    </form>
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
<script>
(function(){
    // Ouvrir automatiquement la modale si aucune classe active mais des affectations existent
    var hasActive = <?= $classId ? 'true':'false' ?>;
    var assignedCount = <?= (int)count($assigned) ?>;
    if (!hasActive && assignedCount >= 1) { $('#switchModal').modal('show'); }

    // DataTables
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            pageLength: 10,
            lengthChange: false,
            order: [[2,'asc']],
            language: {
                search: "Rechercher :", info: "Affichage _START_ à _END_ sur _TOTAL_",
                infoEmpty: "Aucun résultat", infoFiltered: "(filtré de _MAX_ au total)",
                zeroRecords: "Aucun résultat trouvé",
                paginate: { previous: "Préc.", next: "Suiv." }
            }
        });
    }

    // ===== Charts (Chart.js)
    // 1) Contenus
    try{
        var ctx1 = document.getElementById('chartContent').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartContent['labels']) ?>,
                datasets: [{
                    label: 'Nombre',
                    data: <?= json_encode($chartContent['data']) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision:0 } }] }
            }
        });
    }catch(e){}

    // 2) Sexe élèves
    try{
        var ctx2 = document.getElementById('chartGender').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chartGender['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chartGender['data']) ?>
                }]
            },
            options: {
                responsive: true,
                legend: { position: 'bottom' }
            }
        });
    }catch(e){}

    // 3) Quiz sur 6 mois
    try{
        var ctx3 = document.getElementById('chartQuiz').getContext('2d');
        new Chart(ctx3, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartQuiz['labels']) ?>,
                datasets: [
                    { label: 'Quiz créés', data: <?= json_encode($chartQuiz['created']) ?>, fill:false, borderWidth:2 },
                    { label: 'Soumissions', data: <?= json_encode($chartQuiz['submitted']) ?>, fill:false, borderWidth:2 }
                ]
            },
            options: {
                responsive: true,
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision:0 } }] },
                tooltips: { mode: 'index', intersect: false }
            }
        });
    }catch(e){}
})();
</script>
</body>
</html>
