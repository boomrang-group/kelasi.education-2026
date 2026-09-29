<?php
// customs/teacher/view/mes_cours.php
// Liste Cours → Leçons → Contenus avec filtre, publication, réordonnage et suppression sécurisés.
// Version avec "bascule" : classe active lue depuis la session, affichée sans sélecteur.
// Bouton Quiz au niveau du cours (plus dans chaque leçon).

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

// ---- Sécurité minimale : rôle prof requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---------- Helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(string $t): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Récupère TOUTES les classes pour ce prof (robuste) */
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
        // ❌ BUG ICI précédemment: parenthèse fermée au mauvais endroit
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

function loadClasseInfos(PDO $pdo, array $classIds): array {
    if (!$classIds) return [];
    $in = implode(',', array_fill(0, count($classIds), '?'));
    $st = $pdo->prepare("
        SELECT c.id, c.classe, c.description,
               n.description AS niveau, s.description AS section, o.description AS options
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau   = n.id
        LEFT JOIN section s ON c.section  = s.id
        LEFT JOIN options o ON c.options  = o.id
        WHERE c.id IN ($in)
        ORDER BY c.classe, c.id
    ");
    $st->execute($classIds);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function buildClassLabel(array $ci): string {
    $label = trim(($ci['classe']??'').' '.($ci['description']??'').' '.($ci['niveau']??'').' '.($ci['section']??'').' '.($ci['options']??''));
    return $label !== '' ? $label : ('Classe #'.(int)($ci['id'] ?? 0));
}

function getCoursByClass(PDO $pdo, int $classId, ?int $userId, ?string $codeEcole): array {
    if ($codeEcole) {
        $st = $pdo->prepare("SELECT id, nom FROM cours WHERE class=? AND teacher_user_id=? AND code_ecole=? ORDER BY nom");
        $st->execute([$classId, $userId, $codeEcole]);
    } else {
        $st = $pdo->prepare("SELECT id, nom FROM cours WHERE class=? AND teacher_user_id=? ORDER BY nom");
        $st->execute([$classId, $userId]);
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function fetchLecons(PDO $pdo, int $coursId): array {
    $st = $pdo->prepare("SELECT id, titre, ordre, is_published FROM lecons WHERE cours_id=? ORDER BY ordre");
    $st->execute([$coursId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Contenus d’une leçon (optionnellement filtrés par type) — inclut images */
function fetchContenusDetailed(PDO $pdo, int $leconId, ?string $typeFilter = null): array {
    $sql = "
        SELECT lc.id AS lc_id, lc.type_contenu, lc.ordre,
               CASE lc.type_contenu
                    WHEN 'pdf'   THEN p.title
                    WHEN 'video' THEN v.title
                    WHEN 'audio' THEN a.titre
                    WHEN 'image' THEN im.title
               END AS titre,
               CASE lc.type_contenu
                    WHEN 'pdf'   THEN p.filename
                    WHEN 'video' THEN v.filename
                    WHEN 'audio' THEN a.fichier
                    WHEN 'image' THEN im.filename
               END AS filename,
               CASE lc.type_contenu
                    WHEN 'pdf'   THEN p.id
                    WHEN 'video' THEN v.id
                    WHEN 'audio' THEN a.id
                    WHEN 'image' THEN im.id
               END AS cid
        FROM lecon_contenus lc
        LEFT JOIN pdfs   p ON (lc.type_contenu='pdf'   AND p.id=lc.contenu_id)
        LEFT JOIN videos v ON (lc.type_contenu='video' AND v.id=lc.contenu_id)
        LEFT JOIN audios a ON (lc.type_contenu='audio' AND a.id=lc.contenu_id)
        LEFT JOIN images im ON (lc.type_contenu='image' AND im.id=lc.contenu_id)
        WHERE lc.lecon_id=?
    ";
    $params = [$leconId];
    if ($typeFilter && in_array($typeFilter, ['pdf','video','audio','image'], true)) {
        $sql .= " AND lc.type_contenu=?";
        $params[] = $typeFilter;
    }
    $sql .= " ORDER BY lc.ordre";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// URL helper (uploads relatifs depuis customs/teacher/view/)
function filePublicUrl(string $filename, string $type): string {
    // $type: pdf|video|audio|image → dossier pdfs|videos|audios|images
    return '../../../uploads/'.rawurlencode($type.'s').'/'.rawurlencode($filename);
}

/**
 * Résout la classe active depuis la "bascule" (session)
 */
function resolveActiveClassContext(PDO $pdo, array $classIds): array {
    $candidates = [
        ['id' => 'bascule_class_id',      'label' => 'bascule_class_label'],
        ['id' => 'active_class_id',       'label' => 'active_class_label'],
        ['id' => 'classe_active_id',      'label' => 'classe_active_libelle'],
    ];

    $activeId = null; $activeLabel = null;

    foreach ($candidates as $pair) {
        if (!empty($_SESSION[$pair['id']])) {
            $tryId = (int)$_SESSION[$pair['id']];
            if (in_array($tryId, $classIds, true)) {
                $activeId = $tryId;
                $activeLabel = isset($_SESSION[$pair['label']]) && $_SESSION[$pair['label']] !== ''
                    ? (string)$_SESSION[$pair['label']]
                    : null;
                break;
            }
        }
    }

    if ($activeId === null && $classIds) {
        $activeId = (int)$classIds[0];
    }

    if ($activeId !== null && (!$activeLabel || trim($activeLabel) === '')) {
        $st = $pdo->prepare("
            SELECT c.id, c.classe, c.description,
                   n.description AS niveau, s.description AS section, o.description AS options
            FROM classes c
            LEFT JOIN niveau  n ON c.niveau   = n.id
            LEFT JOIN section s ON c.section  = s.id
            LEFT JOIN options o ON c.options  = o.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $st->execute([$activeId]);
        if ($ci = $st->fetch(PDO::FETCH_ASSOC)) {
            $activeLabel = buildClassLabel($ci);
        } else {
            $activeLabel = 'Classe #'.$activeId;
        }
    }

    return [$activeId, $activeLabel];
}

// =============================================
// Résolution classes + classe active + filtre type
// =============================================
$classIds = findClassIdsForTeacher($pdo, $userId, $username, $email, $code_ecole);
[$selectedClassId, $activeLabel] = resolveActiveClassContext($pdo, $classIds);

$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all','pdf','video','audio','image'], true)) $tab = 'all';
$typeFilter = $tab === 'all' ? null : $tab;

$msg = '';
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $msg = '<div class="alert alert-success">Cours / Leçon / Contenu créé avec succès.</div>';
    if ($m === 'updated') $msg = '<div class="alert alert-success">Mise à jour effectuée.</div>';
    if ($m === 'deleted') $msg = '<div class="alert alert-success">Suppression effectuée.</div>';
    if ($m === 'error')   $msg = '<div class="alert alert-danger">Une erreur est survenue.</div>';
}

// =============================================
// Actions POST (toggle publish / reorder / delete)
// =============================================
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check((string)$_POST['_csrf'] ?? '')) {
            throw new RuntimeException('CSRF invalide.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'toggle_lecon') {
            $lid = (int)($_POST['lecon_id'] ?? 0);
            $st = $pdo->prepare("UPDATE lecons SET is_published = 1 - is_published WHERE id=?");
            $st->execute([$lid]);
            header('Location: mes_cours.php?tab='.$tab.'&msg=updated'); exit;
        }
        if ($action === 'move_lecon') {
            $lid = (int)($_POST['lecon_id'] ?? 0);
            $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
            $st = $pdo->prepare("SELECT cours_id, ordre FROM lecons WHERE id=?");
            $st->execute([$lid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $cid = (int)$row['cours_id']; $ord = (int)$row['ordre'];
                if ($dir === 'up') {
                    $st2 = $pdo->prepare("SELECT id, ordre FROM lecons WHERE cours_id=? AND ordre < ? ORDER BY ordre DESC LIMIT 1");
                    $st2->execute([$cid, $ord]);
                } else {
                    $st2 = $pdo->prepare("SELECT id, ordre FROM lecons WHERE cours_id=? AND ordre > ? ORDER BY ordre ASC LIMIT 1");
                    $st2->execute([$cid, $ord]);
                }
                if ($other = $st2->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->beginTransaction();
                    $u1 = $pdo->prepare("UPDATE lecons SET ordre=? WHERE id=?");
                    $u2 = $pdo->prepare("UPDATE lecons SET ordre=? WHERE id=?");
                    $u1->execute([(int)$other['ordre'], $lid]);
                    $u2->execute([$ord, (int)$other['id']]);
                    $pdo->commit();
                }
            }
            header('Location: mes_cours.php?tab='.$tab.'&msg=updated'); exit;
        }
        if ($action === 'move_contenu') {
            $lcid = (int)($_POST['lc_id'] ?? 0);
            $dir  = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
            $st = $pdo->prepare("SELECT lecon_id, ordre FROM lecon_contenus WHERE id=?");
            $st->execute([$lcid]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $lid = (int)$row['lecon_id']; $ord = (int)$row['ordre'];
                if ($dir === 'up') {
                    $st2 = $pdo->prepare("SELECT id, ordre FROM lecon_contenus WHERE lecon_id=? AND ordre < ? ORDER BY ordre DESC LIMIT 1");
                    $st2->execute([$lid, $ord]);
                } else {
                    $st2 = $pdo->prepare("SELECT id, ordre FROM lecon_contenus WHERE lecon_id=? AND ordre > ? ORDER BY ordre ASC LIMIT 1");
                    $st2->execute([$lid, $ord]);
                }
                if ($other = $st2->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->beginTransaction();
                    $u1 = $pdo->prepare("UPDATE lecon_contenus SET ordre=? WHERE id=?");
                    $u2 = $pdo->prepare("UPDATE lecon_contenus SET ordre=? WHERE id=?");
                    $u1->execute([(int)$other['ordre'], $lcid]);
                    $u2->execute([$ord, (int)$other['id']]);
                    $pdo->commit();
                }
            }
            header('Location: mes_cours.php?tab='.$tab.'&msg=updated'); exit;
        }
        if ($action === 'delete_lecon') {
            $lid = (int)($_POST['lecon_id'] ?? 0);
            $del = $pdo->prepare("DELETE FROM lecons WHERE id=?");
            $del->execute([$lid]);
            header('Location: mes_cours.php?tab='.$tab.'&msg=deleted'); exit;
        }
        if ($action === 'delete_contenu') {
            $lcid = (int)($_POST['lc_id'] ?? 0);
            $st = $pdo->prepare("
                SELECT lc.type_contenu, lc.contenu_id,
                       CASE lc.type_contenu
                            WHEN 'pdf'   THEN p.filename
                            WHEN 'video' THEN v.filename
                            WHEN 'audio' THEN a.fichier
                            WHEN 'image' THEN im.filename
                       END AS filename
                FROM lecon_contenus lc
                LEFT JOIN pdfs   p ON (lc.type_contenu='pdf'   AND p.id=lc.contenu_id)
                LEFT JOIN videos v ON (lc.type_contenu='video' AND v.id=lc.contenu_id)
                LEFT JOIN audios a ON (lc.type_contenu='audio' AND a.id=lc.contenu_id)
                LEFT JOIN images im ON (lc.type_contenu='image' AND im.id=lc.contenu_id)
                WHERE lc.id=?
            ");
            $st->execute([$lcid]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $type = $row['type_contenu']; $cid = (int)$row['contenu_id']; $fn = $row['filename'] ?? '';
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM lecon_contenus WHERE id=?")->execute([$lcid]);
                if ($type === 'pdf')    $pdo->prepare("DELETE FROM pdfs WHERE id=?")->execute([$cid]);
                if ($type === 'video')  $pdo->prepare("DELETE FROM videos WHERE id=?")->execute([$cid]);
                if ($type === 'audio')  $pdo->prepare("DELETE FROM audios WHERE id=?")->execute([$cid]);
                if ($type === 'image')  $pdo->prepare("DELETE FROM images WHERE id=?")->execute([$cid]);
                $pdo->commit();

                if ($fn !== '') {
                    $path = realpath(__DIR__.'/../../../uploads/'.$type.'s').'/'.$fn;
                    if (is_file($path)) @unlink($path);
                }
            }
            header('Location: mes_cours.php?tab='.$tab.'&msg=deleted'); exit;
        }
        if ($action === 'delete_cours') {

            $coursId = (int)($_POST['cours_id'] ?? 0);

            if (!$coursId) {
                throw new RuntimeException("Cours invalide.");
            }

            // sécurité classe
            $st = $pdo->prepare("SELECT class FROM cours WHERE id=?");
            $st->execute([$coursId]);
            $classIdDb = (int)$st->fetchColumn();

            if (!$classIdDb || !in_array($classIdDb, $classIds, true)) {
                throw new RuntimeException("Accès refusé.");
            }

            $pdo->beginTransaction();

            // 1. récupérer leçons
            $st = $pdo->prepare("SELECT id FROM lecons WHERE cours_id=?");
            $st->execute([$coursId]);
            $lecons = $st->fetchAll(PDO::FETCH_COLUMN);

            foreach ($lecons as $lid) {

                // 2. supprimer contenus + fichiers physiques
                $st2 = $pdo->prepare("
                    SELECT lc.id, lc.type_contenu,
                        CASE lc.type_contenu
                                WHEN 'pdf' THEN p.id
                                WHEN 'video' THEN v.id
                                WHEN 'audio' THEN a.id
                                WHEN 'image' THEN im.id
                        END AS cid,
                        CASE lc.type_contenu
                                WHEN 'pdf' THEN p.filename
                                WHEN 'video' THEN v.filename
                                WHEN 'audio' THEN a.fichier
                                WHEN 'image' THEN im.filename
                        END AS file
                    FROM lecon_contenus lc
                    LEFT JOIN pdfs p ON (lc.type_contenu='pdf' AND p.id=lc.contenu_id)
                    LEFT JOIN videos v ON (lc.type_contenu='video' AND v.id=lc.contenu_id)
                    LEFT JOIN audios a ON (lc.type_contenu='audio' AND a.id=lc.contenu_id)
                    LEFT JOIN images im ON (lc.type_contenu='image' AND im.id=lc.contenu_id)
                    WHERE lc.lecon_id=?
                ");
                $st2->execute([$lid]);
                $contents = $st2->fetchAll(PDO::FETCH_ASSOC);

                foreach ($contents as $ct) {

                    if (!empty($ct['file'])) {
                        $path = realpath(__DIR__ . '/../../../uploads/' . $ct['type_contenu'].'s') . '/' . $ct['file'];
                        if (is_file($path)) @unlink($path);
                    }

                    if ($ct['type_contenu'] === 'pdf')   $pdo->prepare("DELETE FROM pdfs WHERE id=?")->execute([$ct['cid']]);
                    if ($ct['type_contenu'] === 'video') $pdo->prepare("DELETE FROM videos WHERE id=?")->execute([$ct['cid']]);
                    if ($ct['type_contenu'] === 'audio') $pdo->prepare("DELETE FROM audios WHERE id=?")->execute([$ct['cid']]);
                    if ($ct['type_contenu'] === 'image') $pdo->prepare("DELETE FROM images WHERE id=?")->execute([$ct['cid']]);
                }

                $pdo->prepare("DELETE FROM lecon_contenus WHERE lecon_id=?")->execute([$lid]);
            }

            // 3. supprimer leçons
            $pdo->prepare("DELETE FROM lecons WHERE cours_id=?")->execute([$coursId]);

            // 4. supprimer quiz liés à la classe (optionnel à améliorer avec cours_id plus tard)
            $st = $pdo->prepare("SELECT id FROM quizzes WHERE class_id=?");
            $st->execute([$classIdDb]);
            $quizzes = $st->fetchAll(PDO::FETCH_COLUMN);

            foreach ($quizzes as $qid) {
                $pdo->prepare("DELETE FROM quiz_answers 
                    WHERE submission_id IN (SELECT id FROM quiz_submissions WHERE quiz_id=?)")->execute([$qid]);

                $pdo->prepare("DELETE FROM quiz_submissions WHERE quiz_id=?")->execute([$qid]);
                $pdo->prepare("DELETE FROM quiz_options WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id=?)")->execute([$qid]);
                $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id=?")->execute([$qid]);
                $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$qid]);
            }

            // 5. supprimer cours
            $pdo->prepare("DELETE FROM cours WHERE id=?")->execute([$coursId]);

            $pdo->commit();

            $_SESSION['flash_js_delete_cours'] = [
                'old' => 'Cours supprimé',
                'new' => ''
            ];

            header("Location: mes_cours.php");
            exit;
        }
    }
} catch (Throwable $e) {
    $msg = '<div class="alert alert-danger">Erreur action: '.e($e->getMessage()).'</div>';
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mes cours & leçons</title>
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
    body {
        font-family: system-ui
    }

    .topbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 16px
    }

    .muted {
        color: #666
    }

    .cours-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 14px
    }

    .cours-header {
        background: #f8fafc;
        padding: 10px 14px;
        border-radius: 10px 10px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center
    }

    .cours-body {
        padding: 12px 14px
    }

    .lecon {
        padding: 8px 0;
        border-bottom: 1px dashed #e5e7eb
    }

    .lecon:last-child {
        border-bottom: 0
    }

    .contenus li {
        margin: 2px 0
    }

    .btn-xs {
        padding: .15rem .35rem;
        font-size: .72rem;
        line-height: 1
    }

    .pill {
        display: inline-block;
        padding: .15rem .5rem;
        border-radius: 999px;
        font-size: .75rem
    }

    .pill-pub {
        background: #e8fff1;
        border: 1px solid #22c55e;
        color: #14532d
    }

    .pill-unpub {
        background: #fff7ed;
        border: 1px solid #fb923c;
        color: #7c2d12
    }

    .actions-cours .btn {
        margin-left: 6px
    }

    .badge-soft {
        background: #eef2ff;
        color: #3730a3;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        padding: .25rem .6rem
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php include '../layout/navbar.php'; ?>
        <div class="dashboard-page-one">
            <?php include '../layout/sidebar.php'; ?>
            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h1>📚 Mes cours / leçons / contenus</h1>
                    <?= $msg ?>

                    <?php if (!$classIds): ?>
                    <div class="alert alert-warning">
                        ⚠️ Aucune classe n’est associée à votre compte enseignant.
                        <div class="muted mt-1">Vérifiez <code>class_subject_teacher</code> (teacher_user_id / username
                            / code_ecole).</div>
                    </div>
                    <?php else: ?>
                    <div class="topbar">
                        <div class="d-flex align-items-center" style="gap:8px">
                            <span class="text-muted">Classe active :</span>
                            <span class="badge-soft"><?= e($activeLabel ?: '—') ?></span>
                        </div>

                        <ul class="nav nav-pills">
                            <?php
                                    $tabs = ['all'=>'Tous','pdf'=>'PDF','video'=>'Vidéo','audio'=>'Audio','image'=>'Image'];
                                    foreach($tabs as $k=>$lbl):
                                        $active = $tab===$k ? 'active' : '';
                                        $link = '?tab='.$k;
                                ?>
                            <li class="nav-item"><a class="nav-link <?= $active ?>"
                                    href="<?= e($link) ?>"><?= e($lbl) ?></a></li>
                            <?php endforeach; ?>
                        </ul>

                        <a href="creer_cours.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">
                            <i class="fas fa-plus"></i> Créer un cours
                        </a>
                    </div>

                    <?php
                            $coursList = $selectedClassId ? getCoursByClass($pdo, $selectedClassId, $userId, $code_ecole) : [];
                            if (!$coursList) echo "<p class='text-muted'>Aucun cours pour cette classe.</p>";
                        ?>

                    <div id="accordion">
                        <?php foreach($coursList as $c): ?>
                        <?php $cid = (int)$c['id']; $collapseId = 'cours-'.$cid; ?>
                        <div class="cours-card">
                            <div class="cours-header" id="heading-<?= $cid ?>">
                                <strong><?= e($c['nom']) ?></strong>
                                <div class="actions-cours">
                                    <!-- Nouveau bouton QUIZ au niveau du COURS -->
                                    <a class="btn btn-lg btn-success"
                                        href="composition_mes_quiz.php?cours_id=<?= $cid ?>"
                                        title="Composer un quiz pour ce cours">
                                        <i class="fas fa-question-circle"></i> Quiz
                                    </a>

                                    <a class="btn btn-lg btn-primary" href="creer_cours.php?cours_id=<?= $cid ?>&edit=1"
                                        title="Modifier ce cours">
                                        <i class="fas fa-edit"></i> Modifier le cours/Ajouter une leçon
                                    </a>
                                    <form method="post" class="d-inline"
                                        onsubmit="return confirm('⚠️ Supprimer ce cours, ses leçons et quiz ?');">

                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_cours">
                                        <input type="hidden" name="cours_id" value="<?= $cid ?>">

                                        <button type="submit" class="btn btn-lg btn-danger">
                                            <i class="fas fa-trash"></i> Supprimer ce cours
                                        </button>
                                    </form>
                                    <button class="btn btn-lg btn-secondary" data-toggle="collapse"
                                        data-target="#<?= $collapseId ?>" aria-expanded="true"
                                        aria-controls="<?= $collapseId ?>">
                                        <i class="fas fa-arrow-up"></i>
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="<?= $collapseId ?>" class="collapse show" aria-labelledby="heading-<?= $cid ?>"
                                data-parent="#accordion">
                                <div class="cours-body">
                                    <?php $lecons = fetchLecons($pdo, $cid); ?>
                                    <?php if(!$lecons): ?>
                                    <p class="text-muted mb-0">Aucune leçon pour ce cours.</p>
                                    <?php endif; ?>

                                    <?php foreach($lecons as $l): ?>
                                    <div class="lecon">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <strong>Leçon <?= (int)$l['ordre'] ?> :</strong> <?= e($l['titre']) ?>
                                                <?php if ((int)$l['is_published'] === 1): ?>
                                                <span class="pill pill-pub ml-2">publié</span>
                                                <?php else: ?>
                                                <span class="pill pill-unpub ml-2">non publié</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-2 d-flex align-items-center">
                                                <!-- Boutons actions leçon -->
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="move_lecon">
                                                    <input type="hidden" name="lecon_id" value="<?= (int)$l['id'] ?>">
                                                    <button class="btn btn-md btn-secondary" name="dir" value="up"
                                                        title="Monter">↑</button>
                                                    <button class="btn btn-md btn-secondary" name="dir" value="down"
                                                        title="Descendre">↓</button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="toggle_lecon">
                                                    <input type="hidden" name="lecon_id" value="<?= (int)$l['id'] ?>">
                                                    <button class="btn btn-md btn-primary" title="(Dé)publier"
                                                        style="margin: 0rem 0.50rem 0rem">Publier/Depublier</button>
                                                </form>
                                                <form method="post" class="d-inline"
                                                    onsubmit="return confirm('Supprimer cette leçon ? Les contenus liés seront détachés/supprimés.');">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_lecon">
                                                    <input type="hidden" name="lecon_id" value="<?= (int)$l['id'] ?>">
                                                    <button class="btn btn-md btn-danger" title="Supprimer"><i
                                                            class="fas fa-trash"></i> <span>Supprimer cette leçon</span>
                                                    </button>
                                                </form>
                                                <a href="gerer_cours.php?lecon_id=<?= (int)$l['id'] ?>"
                                                    class="btn btn-md btn-success"
                                                    title="Ajouter un fichier à cette leçon"
                                                    style="margin: 0.40rem 0rem 0rem 0.50rem">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Ajouter un fichier</span>
                                                </a>
                                            </div>
                                        </div>

                                        <?php
                                                    $contenus = fetchContenusDetailed($pdo, (int)$l['id'], $typeFilter);
                                                    if($contenus):
                                                ?>
                                        <ul class="contenus list-unstyled mt-2 ml-3">
                                            <?php foreach($contenus as $ct): ?>
                                            <?php
                                                            $num   = (int)$ct['ordre'];
                                                            $type  = (string)$ct['type_contenu']; // pdf|video|audio|image
                                                            $titre = trim((string)($ct['titre'] ?? ''));
                                                            if ($titre==='') $titre = 'Sans titre';
                                                            $fn    = (string)($ct['filename'] ?? '');
                                                            $url   = $fn ? filePublicUrl($fn, $type) : '#';
                                                        ?>
                                            <li class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <?= $num ?>. [<?= strtoupper($type) ?>]
                                                    <?php if ($fn): ?>
                                                    <a href="<?= e($url) ?>" target="_blank"
                                                        rel="noopener noreferrer"><?= e($titre) ?></a>
                                                    <?php else: ?>
                                                    <?= e($titre) ?> <span class="text-danger">(fichier manquant)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="_csrf"
                                                            value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="move_contenu">
                                                        <input type="hidden" name="lc_id"
                                                            value="<?= (int)$ct['lc_id'] ?>">
                                                        <button class="btn btn-md btn-secondary" name="dir" value="up"
                                                            title="Monter">↑</button>
                                                        <button class="btn btn-md btn-secondary" name="dir" value="down"
                                                            title="Descendre">↓</button>
                                                    </form>
                                                    <form method="post" class="d-inline"
                                                        onsubmit="return confirm('Supprimer ce contenu ?');">
                                                        <input type="hidden" name="_csrf"
                                                            value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="delete_contenu">
                                                        <input type="hidden" name="lc_id"
                                                            value="<?= (int)$ct['lc_id'] ?>">
                                                        <button class="btn btn-md btn-danger"
                                                            title="Supprimer ce fichier"><i
                                                                class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php else: ?>
                                        <div class="muted ml-3">Aucun contenu<?= $typeFilter ? " ($typeFilter)" : "" ?>
                                            dans cette leçon.</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <script src="../../../js/jquery-3.3.1.min.js"></script>
            <script src="../../../js/plugins.js"></script>
            <script src="../../../js/popper.min.js"></script>
            <script src="../../../js/bootstrap.min.js"></script>
            <script src="../../../js/main.js"></script>
            <?php if (!empty($_SESSION['flash_js'])): ?>
            <script>
            const oldName = <?= json_encode($_SESSION['flash_js']['old']) ?>;
            const newName = <?= json_encode($_SESSION['flash_js']['new']) ?>;

            alert("Votre cours a été modifié de '" + oldName + "' vers '" + newName + "'");

            window.location.href = "mes_cours.php";
            </script>
            <?php unset($_SESSION['flash_js']); endif; ?>

            <?php if (!empty($_SESSION['flash_js_delete_cours'])): ?>
            <script>
            // const oldName = <?= json_encode($_SESSION['flash_js_delete_cours']['old']) ?>;
            // const newName = <?= json_encode($_SESSION['flash_js_delete_cours']['new']) ?>;

            // alert("Votre cours '" + oldName + "' a été supprimé");
            alert("Votre cours a été supprimé");

            window.location.href = "mes_cours.php";
            </script>
            <?php unset($_SESSION['flash_js_delete_cours']); endif; ?>
        </div>
    </div>
</body>

</html>