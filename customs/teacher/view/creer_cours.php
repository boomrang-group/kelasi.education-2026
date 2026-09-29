<?php
// customs/teacher/view/creer_cours.php
// Création d’un cours (cours + N leçons + pièces jointes par leçon) — SANS QUIZ
// Mode "cours existant" via ?cours_id=... : ajoute de nouvelles leçons à ce cours, sans modifier l’existant.

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';

// --- Auth : prof requis ---
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username   = $_SESSION['username'] ?? null;
$email      = $_SESSION['email'] ?? null;

// ---------- Helpers ----------
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean(?string $s): string { return trim((string)$s); }
function ensure_dir(string $dir): void { if (!is_dir($dir)) @mkdir($dir, 0775, true); }
function safe_filename(string $name): string {
    $name = preg_replace('~[^a-zA-Z0-9._-]+~', '_', $name);
    return trim($name, '._-') ?: ('file_'.time());
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

/** Liste TOUTES les classes du prof (robuste) */
function findClassIdsForTeacher(PDO $pdo, ?int $usersId, ?string $sessionUsername, ?string $sessionEmail, ?string $codeEcole): array {
    $ids = [];

    if ($usersId && $codeEcole) {
        $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:uid AND code_ecole=:ce");
        $st->execute([':uid'=>$usersId, ':ce'=>$codeEcole]);
        $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($usersId) {
        $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id=:uid");
        $st->execute([':uid'=>$usersId]);
        $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername && $codeEcole) {
        $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un AND code_ecole=:ce");
        $st->execute([':un'=>$sessionUsername, ':ce'=>$codeEcole]);
        $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionUsername) {
        $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE username=:un");
        $st->execute([':un'=>$sessionUsername]);
        $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    }
    if ($sessionEmail) {
        if ($codeEcole) {
            $stT = $pdo->prepare("SELECT id FROM teacher WHERE email=:em AND code_ecole=:ce");
            $stT->execute([':em'=>$sessionEmail, ':ce'=>$codeEcole]);
            $tids = array_map('intval', $stT->fetchAll(PDO::FETCH_COLUMN));
            if ($tids) {
                $in = implode(',', array_fill(0, count($tids), '?'));
                $params = $tids; $params[] = $codeEcole;
                $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in) AND code_ecole=?");
                $st->execute($params);
                $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
            }
        }
        $stT = $pdo->prepare("SELECT id FROM teacher WHERE email=:em");
        $stT->execute([':em'=>$sessionEmail]);
        $tids = array_map('intval', $stT->fetchAll(PDO::FETCH_COLUMN));
        if ($tids) {
            $in = implode(',', array_fill(0, count($tids), '?'));
            $st = $pdo->prepare("SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_user_id IN ($in)");
            $st->execute($tids);
            $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        }
    }
    $ids = array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));
    return $ids;
}

/** Libellé de classe */
function buildClassLabel(array $ci): string {
    $label = trim(($ci['classe']??'').' '.($ci['description']??'').' '.($ci['niveau']??'').' '.($ci['section']??'').' '.($ci['options']??''));
    return $label !== '' ? $label : ('Classe #'.(int)($ci['id'] ?? 0));
}

/** Charge infos classe */
function loadClasseData(PDO $pdo, int $classId): ?array {
    $st = $pdo->prepare("
        SELECT c.id, c.classe, c.description,
               n.description AS niveau, s.description AS section, o.description AS options
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE c.id = :cid
        LIMIT 1
    ");
    $st->execute([':cid'=>$classId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Résout la **classe active** depuis la bascule en session ; retombe sur 1re classe du prof si non défini */
function resolveActiveClassContext(PDO $pdo, array $classIds): array {
    $pairs = [
        ['id' => 'bascule_class_id', 'label' => 'bascule_class_label'],
        ['id' => 'active_class_id',  'label' => 'active_class_label'],
        ['id' => 'classe_active_id', 'label' => 'classe_active_libelle'],
    ];
    $activeId = null; $activeLabel = null;
    foreach ($pairs as $p) {
        if (!empty($_SESSION[$p['id']])) {
            $try = (int)$_SESSION[$p['id']];
            if (in_array($try, $classIds, true)) {
                $activeId = $try;
                $activeLabel = (string)($_SESSION[$p['label']] ?? '');
                break;
            }
        }
    }
    if ($activeId === null && $classIds) $activeId = (int)$classIds[0];

    if ($activeId !== null && (!$activeLabel || trim($activeLabel)==='')) {
        $ci = loadClasseData($pdo, $activeId);
        $activeLabel = $ci ? buildClassLabel($ci) : ('Classe #'.$activeId);
    }
    return [$activeId, $activeLabel];
}

/** Vérifie qu’un cours appartient à la classe (active) et à l’école (si code_ecole fourni) */
function courseBelongsToClass(PDO $pdo, int $coursId, int $classId, ?string $codeEcole): bool {
    $sql = "SELECT COUNT(*) FROM cours WHERE id=:id AND class=:cid";
    $params = [':id'=>$coursId, ':cid'=>$classId];
    if ($codeEcole) { $sql .= " AND code_ecole=:ce"; $params[':ce']=$codeEcole; }
    $st = $pdo->prepare($sql); $st->execute($params);
    return (int)$st->fetchColumn() > 0;
}

/** Charge les leçons d’un cours + leurs contenus (titres/filenames) */
function loadCourseDetails(PDO $pdo, int $coursId): array {
    $data = ['lecons'=>[]];

    $st = $pdo->prepare("SELECT id, titre, ordre, is_published, created_at FROM lecons WHERE cours_id=:cid ORDER BY ordre ASC, id ASC");
    $st->execute([':cid'=>$coursId]);
    $lecons = $st->fetchAll(PDO::FETCH_ASSOC);

    $getContents = $pdo->prepare("
        SELECT id, type_contenu, contenu_id, ordre
        FROM lecon_contenus
        WHERE lecon_id = :lid
        ORDER BY ordre ASC, id ASC
    ");

    $fetchPdf   = $pdo->prepare("SELECT id, title AS t, filename AS f FROM pdfs   WHERE id=:id");
    $fetchVid   = $pdo->prepare("SELECT id, title AS t, filename AS f FROM videos WHERE id=:id");
    $fetchAud   = $pdo->prepare("SELECT id, titre AS t, fichier  AS f FROM audios WHERE id=:id");
    $fetchImg   = $pdo->prepare("SELECT id, title AS t, filename AS f FROM images WHERE id=:id");

    foreach ($lecons as $lec) {
        $row = [
            'id' => (int)$lec['id'],
            'titre' => (string)$lec['titre'],
            'ordre' => (int)$lec['ordre'],
            'is_published' => (int)$lec['is_published'],
            'created_at' => (string)$lec['created_at'],
            'contenus' => []
        ];
        $getContents->execute([':lid'=>$lec['id']]);
        $cts = $getContents->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cts as $c) {
            $type = (string)$c['type_contenu'];
            $cid  = (int)$c['contenu_id'];
            $info = ['id'=>$cid,'type'=>$type,'ordre'=>(int)$c['ordre'],'title'=>null,'file'=>null];
            try {
                if     ($type==='pdf')   { $fetchPdf->execute([':id'=>$cid]); $r=$fetchPdf->fetch(PDO::FETCH_ASSOC); }
                elseif ($type==='video') { $fetchVid->execute([':id'=>$cid]); $r=$fetchVid->fetch(PDO::FETCH_ASSOC); }
                elseif ($type==='audio') { $fetchAud->execute([':id'=>$cid]); $r=$fetchAud->fetch(PDO::FETCH_ASSOC); }
                else /* image */         { $fetchImg->execute([':id'=>$cid]); $r=$fetchImg->fetch(PDO::FETCH_ASSOC); }
                if ($r) { $info['title']=$r['t'] ?? null; $info['file']=$r['f'] ?? null; }
            } catch(Throwable $e) { /* silencieux */ }
            $row['contenus'][] = $info;
        }
        $data['lecons'][] = $row;
    }
    return $data;
}

/** URL publique du fichier en fonction du type */
function build_content_url(string $type, ?string $filename): ?string {
    if (!$filename) return null;
    $type = strtolower($type);
    $sub  = ($type==='pdf'?'pdfs':($type==='video'?'videos':($type==='audio'?'audios':'images')));
    return '../../../uploads/'.$sub.'/'.rawurlencode($filename);
}

// === Résolution **classe active** ===
$alert = '';
try {
    $classIds = findClassIdsForTeacher($pdo, $userId, $username, $email, $code_ecole);
    if (!$classIds) {
        $classId = null; $classeData = null;
        $alert = '<div class="alert alert-warning">Aucune classe n’est associée à votre compte enseignant.</div>';
    } else {
        [$classId, $activeLabel] = resolveActiveClassContext($pdo, $classIds);
        $classeData = $classId ? loadClasseData($pdo, $classId) : null;
    }
} catch (Throwable $e) {
    $alert = '<div class="alert alert-danger">Erreur init : '.e($e->getMessage()).'</div>';
    $classId = null; $classeData = null;
}

// ===== Mode "ajout de leçons à un cours existant" ? =====
$editId   = (!empty($_GET['cours_id']) && ctype_digit((string)$_GET['cours_id'])) ? (int)$_GET['cours_id'] : null;
$isEdit   = ($editId !== null);
$editRow  = null;
$editData = ['lecons'=>[]];

if ($isEdit && $classId) {
    if (!courseBelongsToClass($pdo, $editId, $classId, $code_ecole)) {
        $alert .= '<div class="alert alert-danger mt-2">Ce cours n’appartient pas à votre classe active.</div>';
        $isEdit = false; $editId = null;
    } else {
        $st = $pdo->prepare("SELECT id, nom FROM cours WHERE id=?");
        $st->execute([$editId]);
        $editRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($editRow) {
            $editData = loadCourseDetails($pdo, (int)$editRow['id']);
        } else {
            $isEdit = false; $editId = null;
        }
    }
}

// ====== POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $alert = '<div class="alert alert-danger">CSRF token invalide.</div>';
    } else {

            if (isset($_POST['modifier_cours'])) {

            $coursId = (int)($_POST['cours_id'] ?? 0);
            $nom     = clean($_POST['nom'] ?? '');

            if ($coursId && $nom !== '') {

                // sécurité
                if (!courseBelongsToClass($pdo, $coursId, $classId, $code_ecole)) {
                    $_SESSION['flash_error'] = "Accès refusé à ce cours.";
                    header("Location: mes_cours.php");
                    exit;
                }

                // récupérer ancien nom
                $st = $pdo->prepare("SELECT nom FROM cours WHERE id = ?");
                $st->execute([$coursId]);
                $oldName = $st->fetchColumn();

                $sql = "UPDATE cours SET nom = :nom WHERE id = :id";
                if ($code_ecole) {
                    $sql .= " AND code_ecole = :ce";
                }

                $stmt = $pdo->prepare($sql);

                $params = [
                    ':nom' => $nom,
                    ':id'  => $coursId
                ];

                if ($code_ecole) {
                    $params[':ce'] = $code_ecole;
                }

                $stmt->execute($params);

                // message JS
                $_SESSION['flash_js'] = [
                    'old' => $oldName,
                    'new' => $nom
                ];

                header("Location: mes_cours.php");
                exit;
            }
        }

        // Nom saisi (utilisé SEULEMENT en création de cours)
        $nomCours = clean($_POST['nom'] ?? '');
        $lecons   = isset($_POST['lecons']) && is_array($_POST['lecons']) ? $_POST['lecons'] : [];

        // Règles validation fichiers
        $allowedExtMap = [
            'pdf'   => ['pdf'],
            'video' => ['mp4','mov','mkv','avi','webm'],
            'audio' => ['mp3','wav','aac','m4a','ogg'],
            'image' => ['jpg','jpeg','png','gif','webp']
        ];
        $maxBytesMap = [
            'pdf'   => 50 * 1024 * 1024,
            'video' => 512 * 1024 * 1024,
            'audio' => 100 * 1024 * 1024,
            'image' => 15 * 1024 * 1024
        ];

        if (empty($classId)) {
            $alert = '<div class="alert alert-danger">Impossible d’enregistrer : aucune classe active.</div>';
        } elseif (!$isEdit && $nomCours === '') {
            // Création : nom obligatoire
            $alert = '<div class="alert alert-danger">Veuillez renseigner le nom du cours.</div>';
        } elseif (empty($lecons)) {
            $alert = '<div class="alert alert-danger">Ajoutez au moins une leçon.</div>';
        } else {
            try {
                $pdo->beginTransaction();
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }

                // 1) Cours (dans la **classe active**)
                if ($isEdit && $editId) {
                    // On NE MODIFIE PAS le cours : on se contente de l’utiliser
                    if (!courseBelongsToClass($pdo, $editId, $classId, $code_ecole)) {
                        throw new RuntimeException('Ce cours n’appartient pas à votre classe active.');
                    }
                    $coursId = $editId;
                } else {
                    // Création ou réutilisation d’un cours portant ce nom dans la classe active
                    $sel = $pdo->prepare("SELECT id FROM cours WHERE nom=:n AND class=:cid ".($code_ecole?"AND code_ecole=:ce ":"")."LIMIT 1");
                    $p = [':n'=>$nomCours, ':cid'=>$classId]; if ($code_ecole) $p[':ce']=$code_ecole;
                    $sel->execute($p);
                    $coursId = (int)($sel->fetchColumn() ?: 0);
                    if (!$coursId) {
                        $ins = $pdo->prepare("INSERT INTO cours (nom, class, teacher_user_id, code_ecole, created_at) VALUES (:n,:cid,:teacher_user_id,:ce,NOW())");
                        $ins->execute([':n'=>$nomCours, ':cid'=>$classId, ':teacher_user_id'=>$userId, ':ce'=>$code_ecole]);
                        $coursId = (int)$pdo->lastInsertId();
                    }
                }

                // Dossier upload
                $uploadRoot = realpath(__DIR__.'/../../../uploads') ?: (__DIR__.'/../../../uploads');
                ensure_dir($uploadRoot);
                foreach (['pdfs','videos','audios','images'] as $sd) ensure_dir($uploadRoot.'/'.$sd);

                // Récup ordre leçon initial (propre au **cours**)
                $stMax = $pdo->prepare("SELECT COALESCE(MAX(ordre),0) FROM lecons WHERE cours_id=:cid");
                $stMax->execute([':cid'=>$coursId]);
                $ordreMax = (int)$stMax->fetchColumn();

                // 2) Pour chaque leçon soumise → on AJOUTE après les existantes
                foreach ($lecons as $idx => $L) {
                    $titreLecon  = clean($L['titre'] ?? '');
                    $publieLecon = isset($L['publie']) ? 1 : 0;

                    if ($titreLecon === '') continue; // ignorer leçons vides

                    $ordre = ++$ordreMax;
                    $insL = $pdo->prepare("INSERT INTO lecons (cours_id, titre, ordre, is_published, created_at)
                                           VALUES (:cid, :t, :o, :pub, NOW())");
                    $insL->execute([':cid'=>$coursId, ':t'=>$titreLecon, ':o'=>$ordre, ':pub'=>$publieLecon]);
                    $leconId = (int)$pdo->lastInsertId();

                    // 3) Pièces jointes pour CETTE leçon : champ files = attachments_{idx}[]
                    $fileKey = 'attachments_'.$idx;
                    if (!empty($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
                        $names = $_FILES[$fileKey]['name'];
                        $tmps  = $_FILES[$fileKey]['tmp_name'];
                        $sizes = $_FILES[$fileKey]['size'];

                        $count = count($names);
                        for ($i=0; $i<$count; $i++){
                            if (empty($names[$i])) continue;
                            if (!is_uploaded_file($tmps[$i])) continue;

                            $name = $names[$i];
                            $tmp  = $tmps[$i];
                            $size = (int)$sizes[$i];

                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $type = null;
                            if (in_array($ext, $allowedExtMap['pdf'], true))   $type = 'pdf';
                            elseif (in_array($ext, $allowedExtMap['video'], true)) $type = 'video';
                            elseif (in_array($ext, $allowedExtMap['audio'], true)) $type = 'audio';
                            elseif (in_array($ext, $allowedExtMap['image'], true)) $type = 'image';
                            if (!$type) throw new RuntimeException("Extension .$ext non autorisée.");

                            if ($size > $maxBytesMap[$type]) throw new RuntimeException("Fichier trop volumineux ($type).");

                            $subdir = $type.'s';
                            $dir = $uploadRoot . '/' . $subdir;
                            $base = safe_filename($name);
                            $target = $dir . '/' . $base;
                            $j=1; while (file_exists($target)) {
                                $pi = pathinfo($base);
                                $target = $dir . '/' . ($pi['filename'].'_'.$j++.(isset($pi['extension'])?'.'.$pi['extension']:'')); 
                            }
                            if (!move_uploaded_file($tmp, $target)) {
                                throw new RuntimeException('Échec de déplacement d’un fichier joint.');
                            }
                            $saved = basename($target);

                            // Insert selon type — **toujours** avec class = $classId (classe active)
                            if ($type==='pdf') {
                                $qi = $pdo->prepare("INSERT INTO pdfs (title, filename, class, code_ecole, uploaded_at)
                                                     VALUES (:title, :fn, :class, :ce, NOW())");
                                $qi->execute([':title'=>$name, ':fn'=>$saved, ':class'=>$classId, ':ce'=>$code_ecole]);
                                $cid = (int)$pdo->lastInsertId();
                            } elseif ($type==='video') {
                                $qi = $pdo->prepare("INSERT INTO videos (title, filename, class, code_ecole, uploaded_at)
                                                     VALUES (:title, :fn, :class, :ce, NOW())");
                                $qi->execute([':title'=>$name, ':fn'=>$saved, ':class'=>$classId, ':ce'=>$code_ecole]);
                                $cid = (int)$pdo->lastInsertId();
                            } elseif ($type==='audio') {
                                $qi = $pdo->prepare("INSERT INTO audios (titre, fichier, class, code_ecole, uploaded_at)
                                                     VALUES (:t, :f, :class, :ce, NOW())");
                                $qi->execute([':t'=>$name, ':f'=>$saved, ':class'=>$classId, ':ce'=>$code_ecole]);
                                $cid = (int)$pdo->lastInsertId();
                            } else { // image
                                $pdo->exec("CREATE TABLE IF NOT EXISTS images (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    title VARCHAR(255) NOT NULL,
                                    description TEXT DEFAULT NULL,
                                    filename VARCHAR(255) NOT NULL,
                                    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    class INT NOT NULL,
                                    code_ecole VARCHAR(11) NOT NULL,
                                    KEY (class), KEY (code_ecole)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                                $qi = $pdo->prepare("INSERT INTO images (title, description, filename, class, code_ecole, uploaded_at)
                                VALUES (:title, :desc, :fn, :class, :ce, NOW())");

                                $qi->execute([
                                    ':title' => $name,
                                    ':desc'  => null,
                                    ':fn'    => $saved,
                                    ':class' => $classId,
                                    ':ce'    => $code_ecole
                                ]);
                                $cid = (int)$pdo->lastInsertId();
                            }

                            // 1. Calculer ordre séparément
                            $stOrdre = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM lecon_contenus WHERE lecon_id=:lid");
                            $stOrdre->execute([':lid' => $leconId]);
                            $ordreContenu = (int)$stOrdre->fetchColumn();

                            // 2. Insert
                            $lk = $pdo->prepare("INSERT INTO lecon_contenus (lecon_id, type_contenu, contenu_id, ordre)
                                                VALUES (:lid, :typ, :cid, :ord)");

                            $lk->execute([
                                ':lid' => $leconId,
                                ':typ' => $type,
                                ':cid' => $cid,
                                ':ord' => $ordreContenu
                            ]);
                        }
                    }
                }

                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }

                // Redirection différente si on était en mode ajout de leçons
                if ($isEdit) {
                    header('Location: mes_cours.php?msg=lessons_added'); 
                } else {
                    header('Location: mes_cours.php?msg=created');
                }
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $alert = '<div class="alert alert-danger">Erreur enregistrement : '.e($e->getMessage()).'</div>';
            }
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Ajouter des leçons au cours' : 'Créer un cours' ?> | Espace enseignant</title>
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
    .muted {
        color: #6b7280
    }

    .card {
        border: 0;
        border-radius: 1rem;
        /* box-shadow: 0 10px 25px rgba(0, 0, 0, .05) */
    }

    .pill {
        display: inline-block;
        padding: .15rem .5rem;
        border-radius: 999px;
        font-size: .75rem
    }

    .pill-edit {
        background: #eef6ff;
        border: 1px solid #93c5fd;
        color: #1e3a8a
    }

    .badge-soft {
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #3730a3
    }

    .content-chip {
        display: flex;
        gap: .5rem;
        align-items: center;
        flex-wrap: wrap;
        padding: .35rem .5rem;
        border-radius: .5rem;
        border: 1px solid #e5e7eb;
        background: #fafafa;
        margin: .25rem 0
    }

    .divider {
        height: 1px;
        background: #e5e7eb;
        margin: .75rem 0
    }

    .preview-box {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: .5rem;
        padding: .5rem
    }

    .preview-box embed {
        width: 100%;
        height: 480px
    }

    .preview-box img {
        max-width: 100%;
        height: auto
    }

    .preview-box video,
    .preview-box audio {
        width: 100%
    }

    .btn-chip {
        padding: .15rem .5rem
    }

    .lesson-block {
        border: 1px dashed #cbd5e1;
        border-radius: .75rem;
        padding: 1rem;
        margin-bottom: .75rem;
        background: #fbfdff;
        position: relative
    }

    .rm-lesson {
        position: absolute;
        right: .5rem;
        top: .5rem
    }

    .legend-mini {
        font-weight: 600;
        margin-bottom: .5rem
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
                    <h3>
                        <?= $isEdit ? 'Ajouter des leçons' : 'Créer un cours' ?>
                        <?php if ($isEdit): ?>
                        <span class="pill pill-edit ml-2">cours existant</span>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($classeData)): ?>
                    <ul>
                        <li>Ma classe (active)</li>
                        <li><?= e(($classeData['classe'] ?? '').' '.($classeData['description'] ?? '').' '.($classeData['niveau'] ?? '').' '.($classeData['section'] ?? '').' '.($classeData['options'] ?? '')) ?>
                        </li>
                    </ul>
                    <?php else: ?>
                    <div class="alert alert-warning mt-2">Aucune classe active détectée.</div>
                    <?php endif; ?>
                </div>

                <?php
                if ($alert) echo $alert;
                if (!$classId) echo '<div class="alert alert-warning">Le formulaire est désactivé car aucune classe active n’est liée à votre compte.</div>';
            ?>

                <?php if ($isEdit && $editRow): ?>
                <!-- Bloc de données existantes du cours (avec prévisualisation) -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-2">Cours sélectionné</h5>
                        <div class="mb-2">
                            <!-- <span class="badge badge-soft">Cours #<?= (int)$editRow['id'] ?></span> -->
                            <strong class="ml-2">Cours :</strong> <?= e($editRow['nom']) ?>
                            <div class="muted mt-1">Vous allez <strong>ajouter de nouvelles leçons</strong> à ce cours.
                                Les leçons existantes ne seront pas modifiées.</div>
                        </div>

                        <?php if (empty($editData['lecons'])): ?>
                        <div class="muted">Aucune leçon enregistrée pour ce cours.</div>
                        <?php else: ?>
                        <?php foreach ($editData['lecons'] as $lec): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Leçon #<?= (int)$lec['ordre'] ?> :</strong> <?= e($lec['titre']) ?>
                                    <?= $lec['is_published'] ? '<span class="badge badge-success ml-2">publiée</span>' : '<span class="badge badge-secondary ml-2">brouillon</span>' ?>
                                </div>
                                <div class="muted">Créée le <?= e($lec['created_at']) ?></div>
                            </div>

                            <?php if (empty($lec['contenus'])): ?>
                            <div class="muted mt-1">Aucun contenu.</div>
                            <?php else: ?>
                            <div class="divider"></div>
                            <?php foreach ($lec['contenus'] as $ct): ?>
                            <?php
                                            $url = build_content_url((string)$ct['type'], $ct['file'] ?? null);
                                            $domId = 'pv_'.$lec['id'].'_'.$ct['type'].'_'.$ct['id'];
                                        ?>
                            <div class="content-chip">
                                <small><strong><?= e(strtoupper((string)$ct['type'])) ?></strong></small>
                                <?php if (!empty($ct['title'])): ?>
                                <span class="ml-1"><?= e($ct['title']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ct['file'])): ?>
                                <span class="ml-1 muted">(<?= e($ct['file']) ?>)</span>
                                <?php endif; ?>
                                <span class="ml-1 muted">• ordre <?= (int)$ct['ordre'] ?></span>

                                <?php if ($url): ?>
                                <a class="btn btn-sm btn-outline-primary btn-chip ml-2" data-toggle="collapse"
                                    href="#<?= e($domId) ?>">Voir</a>
                                <a class="btn btn-sm btn-outline-secondary btn-chip" href="<?= e($url) ?>"
                                    target="_blank" rel="noopener" download> Télécharger </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($url): ?>
                            <div class="collapse mt-2" id="<?= e($domId) ?>">
                                <div class="preview-box">
                                    <?php if ($ct['type']==='pdf'): ?>
                                    <embed src="<?= e($url) ?>" type="application/pdf">
                                    <?php elseif ($ct['type']==='video'): ?>
                                    <video controls src="<?= e($url) ?>"></video>
                                    <?php elseif ($ct['type']==='audio'): ?>
                                    <audio controls src="<?= e($url) ?>"></audio>
                                    <?php else: /* image */ ?>
                                    <img src="<?= e($url) ?>" alt="<?= e($ct['title'] ?? 'image') ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">

                        <?php if ($isEdit): ?>
                        <!-- Formulaire 1 : Uniquement pour modifier le nom du cours -->
                        <fieldset class="mb-4">
                            <legend class="h5">Nom du cours</legend>
                            <form method="post" action="">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="cours_id" value="<?= (int)$editId ?>">
                                <div class="form-group col-lg-6 p-0">
                                    <label>Modifier le nom du cours</label>
                                    <div class="d-flex">
                                        <input type="text" name="nom" class="form-control"
                                            value="<?= e($editRow['nom'] ?? '') ?>" required>
                                        <button name="modifier_cours" value="1"
                                            class="btn btn-primary ml-2">Valider</button>
                                    </div>
                                    <small class="muted">Cliquez sur valider pour mettre à jour uniquement le titre du
                                        cours.</small>
                                </div>
                            </form>
                        </fieldset>
                        <hr>
                        <?php endif; ?>

                        <!-- Formulaire 2 : Pour créer un cours OU ajouter des leçons -->
                        <form method="post" action="" enctype="multipart/form-data" autocomplete="off" id="createForm">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <?php if ($isEdit && $editId): ?>
                            <input type="hidden" name="cours_id" value="<?= (int)$editId ?>">
                            <?php else: ?>
                            <fieldset class="mb-3">
                                <legend class="h5">Nouveau cours</legend>
                                <div class="row">
                                    <div class="col-lg-6 form-group">
                                        <label>Nom du cours <span class="text-danger">*</span></label>
                                        <input type="text" name="nom" class="form-control"
                                            placeholder="Ex : Conjugaison" required
                                            <?= empty($classId)?'disabled':''; ?>>
                                        <small class="muted">Si un cours du même nom existe déjà pour votre classe
                                            active, il sera réutilisé.</small>
                                    </div>
                                </div>
                            </fieldset>
                            <?php endif; ?>

                            <!-- Leçons dynamiques -->
                            <fieldset class="mb-2">
                                <legend class="h5 d-flex justify-content-between align-items-center">
                                    <span>Leçons à ajouter</span>
                                    <button type="button" id="addLessonBtn" class="btn btn-sm btn-outline-primary"
                                        <?= empty($classId)?'disabled':''; ?>>+ Ajouter leçon</button>
                                </legend>

                                <div id="lessonsContainer">
                                    <!-- blocs leçon injectés ici -->
                                </div>

                                <template id="lessonTemplate">
                                    <div class="lesson-block">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger rm-lesson">Supprimer</button>
                                        <div class="legend-mini">Nouvelle leçon</div>
                                        <div class="row">
                                            <div class="col-md-8 form-group">
                                                <label>Titre de la leçon <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control lesson-title" required>
                                            </div>
                                            <div class="col-md-4 form-group d-flex align-items-end">
                                                <div class="form-check">
                                                    <input class="form-check-input lesson-pub" type="checkbox">
                                                    <label class="form-check-label">Publier immédiatement</label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 form-group">
                                                <label>Pièces jointes (PDF / VIDÉO / AUDIO / IMAGE)</label>
                                                <input type="file" class="form-control lesson-files"
                                                    accept=".pdf,.mp4,.mov,.mkv,.avi,.webm,.mp3,.wav,.aac,.m4a,.ogg,.jpg,.jpeg,.png,.gif,.webp"
                                                    multiple>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </fieldset>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary" <?= empty($classId)?'disabled':''; ?>>
                                    <?= $isEdit ? 'Ajouter ces leçons au cours' : 'Créer le cours et les leçons' ?>
                                </button>
                                <a href="mes_cours.php" class="btn btn-outline-secondary">Annuler</a>
                            </div>
                        </form>
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
    <script>
    (function() {
        // Gestion du builder de leçons
        var idx = 0;
        var container = document.getElementById('lessonsContainer');
        var tpl = document.getElementById('lessonTemplate');
        var addBtn = document.getElementById('addLessonBtn');

        function addLesson(prefill) {
            var node = tpl.content.cloneNode(true);
            var block = node.querySelector('.lesson-block');
            var title = node.querySelector('.lesson-title');
            var pub = node.querySelector('.lesson-pub');
            var files = node.querySelector('.lesson-files');
            var rm = node.querySelector('.rm-lesson');

            // Noms des champs : lecons[idx][titre], lecons[idx][publie], attachments_idx[]
            title.name = 'lecons[' + idx + '][titre]';
            pub.name = 'lecons[' + idx + '][publie]';
            files.name = 'attachments_' + idx + '[]';

            if (prefill && prefill.title) title.value = prefill.title;
            if (prefill && prefill.pub) pub.checked = true;

            rm.addEventListener('click', function() {
                block.remove();
            });

            container.appendChild(node);
            idx++;
        }

        // Ajouter au moins un bloc au chargement
        addLesson();

        if (addBtn) addBtn.addEventListener('click', function() {
            addLesson();
        });
    })();
    </script>
</body>

</html>