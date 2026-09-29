<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

// ================= AUTH =================
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ================= HELPERS =================
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s){ return trim((string)$s); }
function ensure_dir($dir){ if(!is_dir($dir)) @mkdir($dir,0775,true); }
function safe_filename($n){
    $n = preg_replace('~[^a-zA-Z0-9._-]+~','_',$n);
    return trim($n,'._-') ?: ('file_'.time());
}
function csrf(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check($t){
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t);
}

// ================= LEÇON ID =================
$lecon_id = isset($_GET['lecon_id']) ? (int)$_GET['lecon_id'] : 0;

if (!$lecon_id) {
    exit("Leçon introuvable");
}

// ================= LOAD LEÇON =================
$st = $pdo->prepare("
    SELECT 
        l.*,
        c.nom AS cours_nom,
        c.class AS class_id
    FROM lecons l
    JOIN cours c ON c.id = l.cours_id
    WHERE l.id = ?
");
$st->execute([$lecon_id]);
$lecon = $st->fetch(PDO::FETCH_ASSOC);

if (!$lecon) {
    exit("Leçon inexistante");
}

// ================= TRAITEMENT UPLOAD =================
$alert = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $alert = "<div class='alert alert-danger'>CSRF invalide</div>";
    } else {

        $allowed = [
            'pdf'   => ['pdf'],
            'video' => ['mp4','mov','mkv','avi','webm'],
            'audio' => ['mp3','wav','aac','m4a','ogg'],
            'image' => ['jpg','jpeg','png','gif','webp']
        ];

        $max = [
            'pdf'=>50*1024*1024,
            'video'=>500*1024*1024,
            'audio'=>100*1024*1024,
            'image'=>15*1024*1024
        ];

        $files = $_FILES['files'] ?? null;

        if ($files && !empty($files['name'][0])) {

            $uploadRoot = realpath(__DIR__.'/../../../uploads') ?: __DIR__.'/../../../uploads';

            foreach(['pdfs','videos','audios','images'] as $f){
                ensure_dir($uploadRoot.'/'.$f);
            }

            $count = count($files['name']);

            try {
                $pdo->beginTransaction();

                for ($i=0;$i<$count;$i++) {

                    if (empty($files['name'][$i])) continue;

                    $name = $files['name'][$i];
                    $tmp  = $files['tmp_name'][$i];
                    $size = (int)$files['size'][$i];

                    if (!is_uploaded_file($tmp)) continue;

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    $type = null;
                    if (in_array($ext,$allowed['pdf'])) $type='pdf';
                    elseif (in_array($ext,$allowed['video'])) $type='video';
                    elseif (in_array($ext,$allowed['audio'])) $type='audio';
                    elseif (in_array($ext,$allowed['image'])) $type='image';
                    else continue;

                    if ($size > $max[$type]) {
                        throw new Exception("Fichier trop volumineux");
                    }

                    $folder = $type.'s';
                    $dir = $uploadRoot.'/'.$folder;

                    $filename = safe_filename($name);
                    $target = $dir.'/'.$filename;

                    $j=1;
                    while(file_exists($target)){
                        $p = pathinfo($filename);
                        $target = $dir.'/'.$p['filename'].'_'.$j++.'.'.$p['extension'];
                    }

                    move_uploaded_file($tmp,$target);
                    $saved = basename($target);

                    // ================= INSERT =================
                    if ($type==='pdf') {
                        $pdo->prepare("INSERT INTO pdfs(title,filename,class,code_ecole,uploaded_at)
                        VALUES (?,?,?,?,NOW())")
                        ->execute([$name,$saved,$lecon['class_id'],$code_ecole]);

                        $cid = $pdo->lastInsertId();

                    } elseif ($type==='video') {
                        $pdo->prepare("INSERT INTO videos(title,filename,class,code_ecole,uploaded_at)
                        VALUES (?,?,?,?,NOW())")
                        ->execute([$name,$saved,$lecon['class_id'],$code_ecole]);

                        $cid = $pdo->lastInsertId();

                    } elseif ($type==='audio') {
                        $pdo->prepare("INSERT INTO audios(titre,fichier,class,code_ecole,uploaded_at)
                        VALUES (?,?,?,?,NOW())")
                        ->execute([$name,$saved,$lecon['class_id'],$code_ecole]);

                        $cid = $pdo->lastInsertId();

                    } else {
                        $pdo->prepare("INSERT INTO images(title,filename,class,code_ecole,uploaded_at)
                        VALUES (?,?,?,?,NOW())")
                        ->execute([$name,$saved,$lecon['class_id'],$code_ecole]);

                        $cid = $pdo->lastInsertId();
                    }

                    // ================= LINK =================
                    $stOrd = $pdo->prepare("
                        SELECT COALESCE(MAX(ordre),0)+1 
                        FROM lecon_contenus 
                        WHERE lecon_id=?
                    ");
                    $stOrd->execute([$lecon_id]);
                    $ordre = (int)$stOrd->fetchColumn();

                    $pdo->prepare("
                        INSERT INTO lecon_contenus(lecon_id,type_contenu,contenu_id,ordre)
                        VALUES (?,?,?,?)
                    ")->execute([$lecon_id,$type,$cid,$ordre]);
                }

                $pdo->commit();

                $alert = "<div class='alert alert-success'>Fichiers ajoutés avec succès</div>";

            } catch(Exception $e){
                $pdo->rollBack();
                $alert = "<div class='alert alert-danger'>Erreur : ".e($e->getMessage())."</div>";
            }
        }
    }
}
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi - leçons</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <style>
    .card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .05)
    }

    .preview {
        padding: 10px;
        border: 1px solid #eee;
        border-radius: 8px;
        margin-top: 10px
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
                    <h3>Gérer la leçon</h3>
                    <ul>
                        <li><?= e($lecon['cours_nom']) ?></li>
                        <li><?= e($lecon['titre']) ?></li>
                    </ul>
                </div>

                <?= $alert ?>

                <div class="card">
                    <div class="card-body">

                        <h5>Ajouter des fichiers à cette leçon</h5>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">

                            <div class="form-group">
                                <label>Fichiers</label>
                                <input type="file" name="files[]" class="form-control" multiple>
                                <small>PDF, vidéo, audio, image autorisés</small>
                            </div>

                            <button class="btn btn-primary btn-lg">
                                Ajouter
                            </button>
                            <a href="mes_cours.php" class="btn btn-dark btn-lg">
                                Annuler
                            </a>

                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
</body>

</html>