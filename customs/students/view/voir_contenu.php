<?php
// customs/eleve/view/voir_contenu.php
// Affiche un contenu PDF / VIDEO / AUDIO d'une leçon, avec contrôle d'accès (classe)

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';
if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); } catch(Throwable $e){} }

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

$type = strtolower(trim($_GET['type'] ?? ''));
$id   = (isset($_GET['id']) && ctype_digit($_GET['id'])) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['pdf','video','audio'], true) || $id <= 0) {
    echo "Paramètres invalides."; exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function findStudent(PDO $pdo, ?string $email, ?string $username, ?string $codeEcole): ?array {
    try {
        if ($email) {
            $st=$pdo->prepare("SELECT * FROM students WHERE email=:em ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':em'=>$email]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return $r;
        }
        if ($username) {
            $st=$pdo->prepare("SELECT * FROM students WHERE username=:un ".($codeEcole?'AND code_ecole=:ce ':'')."LIMIT 1");
            $p=[':un'=>$username]; if ($codeEcole) $p[':ce']=$codeEcole;
            $st->execute($p); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return $r;
        }
    }catch(Throwable $e){}
    return null;
}

$student = findStudent($pdo, $email, $username, $code_ecole);
if (!$student) { echo "Élève introuvable."; exit; }
$classId = (int)$student['class_id'];

$row = null;
$title = '';
$path  = '';

try {
    if ($type==='pdf') {
        $st=$pdo->prepare("SELECT id, title, filename, class, code_ecole FROM pdfs WHERE id=:id");
        $st->execute([':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
        $title = $row ? ($row['title'] ?: ('PDF #'.$id)) : '';
        $file  = $row['filename'] ?? '';
    } elseif ($type==='video') {
        $st=$pdo->prepare("SELECT id, title, filename, class, code_ecole FROM videos WHERE id=:id");
        $st->execute([':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
        $title = $row ? ($row['title'] ?: ('Vidéo #'.$id)) : '';
        $file  = $row['filename'] ?? '';
    } else { // audio
        $st=$pdo->prepare("SELECT id, titre AS title, fichier AS filename, class, code_ecole FROM audios WHERE id=:id");
        $st->execute([':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
        $title = $row ? ($row['title'] ?: ('Audio #'.$id)) : '';
        $file  = $row['filename'] ?? '';
    }
} catch(Throwable $e) {}

if (!$row || (int)$row['class'] !== $classId) { echo "Accès refusé."; exit; }

// Construire l'URL du fichier
function file_url(string $type, string $filename): string {
    $f = trim($filename);
    if ($f==='' ) return '';
    if (preg_match('~^(https?://|/)~i', $f)) return $f; // déjà absolu
    $base = [
        'pdf'   => '../../../uploads/pdfs/',
        'video' => '../../../uploads/videos/',
        'audio' => '../../../uploads/audios/',
    ][$type] ?? '../../../uploads/';
    return $base.$f;
}
$path = file_url($type, $file);

?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title><?= h(strtoupper($type).' — '.$title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <style>
    body {
        background: #f7f7fb
    }

    .viewer {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px
    }
    </style>
</head>

<body>
    <div id="wrapper" class="wrapper bg-ash">
        <?php include '../layout/navbar.php'; ?>
        <div class="dashboard-page-one">
            <?php include '../layout/sidebar.php'; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3><?= h($title) ?></h3>
                    <p class="text-muted">Type : <?= strtoupper($type) ?></p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if ($type==='pdf'): ?>
                        <?php if ($path): ?>
                        <div class="viewer">
                            <iframe src="<?= h($path) ?>" width="100%" height="700" style="border:0"></iframe>
                        </div>
                        <a class="btn btn-outline-secondary mt-3" href="<?= h($path) ?>" target="_blank">Ouvrir dans un
                            nouvel onglet</a>
                        <?php else: ?>
                        <div class="alert alert-warning">Fichier PDF introuvable.</div>
                        <?php endif; ?>
                        <?php elseif ($type==='video'): ?>
                        <?php if ($path): ?>
                        <div class="viewer text-center">
                            <video controls width="100%" style="max-height:70vh">
                                <source src="<?= h($path) ?>">
                                Votre navigateur ne supporte pas la vidéo HTML5.
                            </video>
                        </div>
                        <a class="btn btn-outline-secondary mt-3" href="<?= h($path) ?>" target="_blank">Télécharger la
                            vidéo</a>
                        <?php else: ?>
                        <div class="alert alert-warning">Fichier vidéo introuvable.</div>
                        <?php endif; ?>
                        <?php else: ?>
                        <?php if ($path): ?>
                        <div class="viewer text-center">
                            <audio controls>
                                <source src="<?= h($path) ?>">
                                Votre navigateur ne supporte pas l'audio HTML5.
                            </audio>
                        </div>
                        <a class="btn btn-outline-secondary mt-3" href="<?= h($path) ?>" target="_blank">Télécharger
                            l'audio</a>
                        <?php else: ?>
                        <div class="alert alert-warning">Fichier audio introuvable.</div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php include '../layout/footer.php'; ?>
            </div>
        </div>
    </div>

    <script src="../../../js/bootstrap.min.js"></script>
</body>

</html>