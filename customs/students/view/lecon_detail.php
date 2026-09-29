<?php
// customs/eleve/view/lecon_detail.php
// Affiche les contenus (PDF/VIDEO/AUDIO) d'une leçon pour l'élève

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';
if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); } catch(Throwable $e){} }

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$leconId = (isset($_GET['id']) && ctype_digit($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($leconId <= 0) { echo "Leçon invalide."; exit; }

// Trouver l'élève
function findStudent(PDO $pdo, ?string $email, ?string $username, ?string $codeEcole): ?array {
    try {
        if ($email) {
            $st=$pdo->prepare("SELECT * FROM students WHERE email=:em ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':em'=>$email]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($row=$st->fetch(PDO::FETCH_ASSOC)) return $row;
        }
        if ($username) {
            $st=$pdo->prepare("SELECT * FROM students WHERE username=:un ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':un'=>$username]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($row=$st->fetch(PDO::FETCH_ASSOC)) return $row;
        }
    } catch(Throwable $e) {}
    return null;
}

$student = findStudent($pdo, $email, $username, $code_ecole);
if (!$student) { echo "Élève introuvable."; exit; }
$classId   = (int)$student['class_id'];
$studentId = (int)$student['id'];

// Vérifier que la leçon appartient à un cours de la classe de l'élève
$lecon = null;
try{
    $sql = "SELECT l.id, l.titre, l.cours_id, c.nom AS cours_nom, c.class
            FROM lecons l
            INNER JOIN cours c ON c.id=l.cours_id
            WHERE l.id=:id";
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$leconId]);
    $lecon = $st->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

if (!$lecon || (int)$lecon['class'] !== $classId) {
    echo "Accès refusé à cette leçon.";
    exit;
}

// Récupérer contenus
$contents = [];
try {
    $sql = "SELECT lc.type_contenu, lc.contenu_id,
                   COALESCE(p.title, v.title, a.titre) AS contenu_titre
            FROM lecon_contenus lc
            LEFT JOIN pdfs   p ON (lc.type_contenu='pdf'   AND p.id=lc.contenu_id)
            LEFT JOIN videos v ON (lc.type_contenu='video' AND v.id=lc.contenu_id)
            LEFT JOIN audios a ON (lc.type_contenu='audio' AND a.id=lc.contenu_id)
            WHERE lc.lecon_id=:lid
            ORDER BY lc.ordre, lc.contenu_id";
    $st = $pdo->prepare($sql);
    $st->execute([':lid'=>$leconId]);
    $contents = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){ $contents=[]; }

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= h($lecon['cours_nom'].' — '.$lecon['titre']) ?> | Leçon</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <style>
        .content-row{display:flex;justify-content:space-between;align-items:center;gap:.75rem;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem 1rem;margin-bottom:.5rem;background:#fff}
        .badge-type{font-size:.75rem}
        .muted{color:#6b7280}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3><?= h($lecon['cours_nom']) ?></h3>
                <p class="muted">Leçon : <strong><?= h($lecon['titre']) ?></strong></p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (!$contents): ?>
                        <div class="alert alert-info">Aucun contenu dans cette leçon.</div>
                    <?php else: foreach ($contents as $c):
                        $type = strtolower($c['type_contenu']);
                        $title = trim((string)$c['contenu_titre']) ?: strtoupper($type).' #'.$c['contenu_id'];
                        $href = 'voir_contenu.php?type='.$type.'&id='.(int)$c['contenu_id'];
                        $badge = '<span class="badge badge-info badge-type">'.strtoupper($type).'</span>';
                    ?>
                        <div class="content-row">
                            <div>
                                <?= $badge ?> &nbsp; <?= h($title) ?>
                            </div>
                            <div>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $href = 'service/track_view.php?type='.$type.'&id='.(int)$c['contenu_id'].'&lecon_id='.(int)$leconId; ?>">
                                    Ouvrir
                                </a>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/plugins.js"></script>
<script src="../../../js/popper.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script src="../../../js/main.js"></script>
</body>
</html>
