<?php
// mykelasi/admin/affectation.php
// Affecter un professeur EXISTANT à une ou plusieurs classes (sans créer un compte)
// + Voir détails du prof + Retirer des classes

declare(strict_types=1);

 // DEBUG handled in db_connect.php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ---- Connexion DB ----
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

// ---- Auth : admin + code_ecole ----
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

// --- CSRF minimal ---
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];

// ---- Paramètre GET pour pré-sélection du prof ----
$prefTeacherId = (isset($_GET['teacher_user_id']) && ctype_digit((string)$_GET['teacher_user_id']))
    ? (int)$_GET['teacher_user_id'] : 0;

// ---- Charger profs (users.role='prof') de l'école ----
$profs = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at, u.password
        FROM users u
        WHERE u.role='prof' AND u.code_ecole=:ce
        ORDER BY u.first_name, u.last_name, u.username
    ");
    $st->execute([':ce'=>$codeEcole]);
    $profs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if (DEBUG) { echo $e->getMessage(); exit; }
}

// Index rapide des profs par id
$profIndex = [];
foreach ($profs as $p) { $profIndex[(int)$p['id']] = $p; }
$prefTeacherValid = $prefTeacherId > 0 && isset($profIndex[$prefTeacherId]);

// ---- Charger classes de l'école ----
$classes = [];
try {
    $st = $pdo->prepare("
        SELECT c.id, c.classe, c.description AS desc_classe,
               n.description AS niveau, s.description AS section, o.description AS opt
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options  o ON c.options = o.id
        WHERE c.code_ecole = :ce
        ORDER BY n.description, s.description, o.description, c.classe, c.description
    ");
    $st->execute([':ce'=>$codeEcole]);
    $classes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if (DEBUG) { echo $e->getMessage(); exit; }
}

// ---- Récupérer les affectations existantes pour cette école ----
// -> class_id => teacher_user_id
$affects = [];

try {
    $st = $pdo->prepare("
        SELECT class_id, teacher_user_id, titulaire
        FROM class_subject_teacher
        WHERE code_ecole = :ce
    ");
    $st->execute([':ce'=>$codeEcole]);

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {

    $cid = (int)$row['class_id'];
    $tid = (int)$row['teacher_user_id'];
    $titulaire = (int)$row['titulaire'];

    if (!isset($affects[$cid])) {
        $affects[$cid] = [];
    }

    $affects[$cid][$tid] = $titulaire;
}

} catch (Throwable $e) {
    if (DEBUG) { echo $e->getMessage(); exit; }
}

// ---- Messages retour ----
$msg = '';
if (!empty($_GET['msg'])) {
    $map = [
        'created'  => '✅ Affectation enregistrée.',
        'removed'  => '🗑️ Classe retirée de ce professeur.',
        'invalid'  => '⚠️ Données invalides.',
        'badclass' => '⚠️ Au moins une classe n’appartient pas à cette école.',
        'error'    => '❌ Erreur lors de l’opération.',
    ];
    $msg = $map[$_GET['msg']] ?? '';
    if (!empty($_GET['skipped'])) {
        $msg .= '<br><small>Classes ignorées (déjà affectées à un autre enseignant) : '.e($_GET['skipped']).'</small>';
    }
}

// ---- POST (ajout d’affectations OU retrait d’une classe) ----
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? 'add';
    $token  = $_POST['csrf_token'] ?? '';
    if (!hash_equals($CSRF, $token)) { header('Location: affectation.php?msg=invalid'); exit; }

    // Ajout d’affectations
    if ($action === 'add') {
        $teacher_user_id = isset($_POST['teacher_user_id']) && ctype_digit((string)$_POST['teacher_user_id']) ? (int)$_POST['teacher_user_id'] : 0;
        $selClasses = $_POST['classes'] ?? [];
        $titulaires = $_POST['titulaire'] ?? [];

        if ($teacher_user_id<=0 || !isset($profIndex[$teacher_user_id]) || !is_array($selClasses) || count($selClasses)===0) {
            header('Location: affectation.php?msg=invalid'); exit;
        }

        $selClasses = array_values(array_unique(array_filter(array_map(function($v){
            return ctype_digit((string)$v) ? (int)$v : 0;
        }, $selClasses), fn($x)=>$x>0)));

        // Vérifier prof
        $st = $pdo->prepare("SELECT username, `PASSWORD` FROM users WHERE id=:id AND role='prof' AND code_ecole=:ce LIMIT 1");
        $st->execute([':id'=>$teacher_user_id, ':ce'=>$codeEcole]);
        $uRow = $st->fetch(PDO::FETCH_ASSOC);
        if (!$uRow) { header('Location: affectation.php?msg=invalid'); exit; }

        // Vérifier classes
        $in = implode(',', array_fill(0, count($selClasses), '?'));
        $chk = $pdo->prepare("SELECT id FROM classes WHERE code_ecole=? AND id IN ($in)");
        $chk->execute(array_merge([$codeEcole], $selClasses));
        $found = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
        if (count($found) !== count($selClasses)) {
            header('Location: affectation.php?msg=badclass'); exit;
        }

        // Insérer
        $skipped = [];
        $ins = $pdo->prepare("
            INSERT INTO class_subject_teacher
            (
                class_id,
                teacher_user_id,
                titulaire,
                code_ecole
            )
            VALUES
            (
                :cid,
                :tid,
                :titulaire,
                :ce
            )
        ");
        try {
            foreach ($selClasses as $cid) {

                $isTitulaire = isset($titulaires[$cid]) ? 1 : 0;

                /*
                * Vérifier si l'affectation existe déjà
                */
                $check = $pdo->prepare("
                    SELECT id
                    FROM class_subject_teacher
                    WHERE class_id = :cid
                    AND teacher_user_id = :tid
                    LIMIT 1
                ");

                $check->execute([
                    ':cid' => $cid,
                    ':tid' => $teacher_user_id
                ]);

                $affectationId = $check->fetchColumn();

                if ($affectationId) {

                    if ($isTitulaire) {

                        /*
                        * Retirer le titulaire précédent
                        */
                        $pdo->prepare("
                            UPDATE class_subject_teacher
                            SET titulaire = 0
                            WHERE class_id = :cid
                            AND code_ecole = :ce
                        ")->execute([
                            ':cid' => $cid,
                            ':ce'  => $codeEcole
                        ]);
                    }

                    /*
                    * Mise à jour de l'affectation existante
                    */
                    $pdo->prepare("
                        UPDATE class_subject_teacher
                        SET titulaire = :titulaire
                        WHERE id = :id
                    ")->execute([
                        ':titulaire' => $isTitulaire,
                        ':id'        => $affectationId
                    ]);

                } else {

                    if ($isTitulaire) {

                        $pdo->prepare("
                            UPDATE class_subject_teacher
                            SET titulaire = 0
                            WHERE class_id = :cid
                            AND code_ecole = :ce
                        ")->execute([
                            ':cid' => $cid,
                            ':ce'  => $codeEcole
                        ]);
                    }

                    /*
                    * Nouvelle affectation
                    */
                    $ins->execute([
                        ':cid'       => $cid,
                        ':tid'       => $teacher_user_id,
                        ':titulaire' => $isTitulaire,
                        ':ce'        => $codeEcole
                    ]);
                }
            }
            $redir = 'affectation.php?msg=created&teacher_user_id='.$teacher_user_id;
            if ($skipped) $redir .= '&skipped='.rawurlencode(implode(', ', $skipped));
            header('Location: '.$redir); exit;

        } catch (Throwable $e) {
            if (DEBUG) { echo 'Erreur: '.$e->getMessage(); exit; }
            header('Location: affectation.php?msg=error'); exit;
        }
    }

    // Retirer une classe du prof
    if ($action === 'remove') {
        $teacher_user_id = isset($_POST['teacher_user_id']) && ctype_digit((string)$_POST['teacher_user_id']) ? (int)$_POST['teacher_user_id'] : 0;
        $class_id        = isset($_POST['class_id']) && ctype_digit((string)$_POST['class_id']) ? (int)$_POST['class_id'] : 0;

        if ($teacher_user_id<=0 || $class_id<=0 || !isset($profIndex[$teacher_user_id])) {
            header('Location: affectation.php?msg=invalid'); exit;
        }

        // Vérifier que l’affectation existe bien pour cette école
        $st = $pdo->prepare("
            SELECT 1
            FROM class_subject_teacher
            WHERE teacher_user_id=:tid AND class_id=:cid AND code_ecole=:ce
            LIMIT 1
        ");
        $st->execute([':tid'=>$teacher_user_id, ':cid'=>$class_id, ':ce'=>$codeEcole]);
        $exists = (bool)$st->fetchColumn();

        if (!$exists) {
            header('Location: affectation.php?msg=invalid&teacher_user_id='.$teacher_user_id); exit;
        }

        // Supprimer
        $del = $pdo->prepare("
            DELETE FROM class_subject_teacher
            WHERE teacher_user_id=:tid AND class_id=:cid AND code_ecole=:ce
            LIMIT 1
        ");
        try {
            $del->execute([':tid'=>$teacher_user_id, ':cid'=>$class_id, ':ce'=>$codeEcole]);
            header('Location: affectation.php?msg=removed&teacher_user_id='.$teacher_user_id); exit;
        } catch (Throwable $e) {
            if (DEBUG) { echo 'Erreur: '.$e->getMessage(); exit; }
            header('Location: affectation.php?msg=error&teacher_user_id='.$teacher_user_id); exit;
        }
    }

    // ---- ACTIVER / DESACTIVER COMPTE ----
if ($action === 'toggle_account') {
    $teacher_user_id = isset($_POST['teacher_user_id']) && ctype_digit((string)$_POST['teacher_user_id']) ? (int)$_POST['teacher_user_id'] : 0;

    if ($teacher_user_id <= 0) {
        header('Location: affectation.php?msg=invalid'); exit;
    }

    try {
        // Vérifier user
        $st = $pdo->prepare("SELECT id, password FROM users WHERE id=:id AND code_ecole=:ce LIMIT 1");
        $st->execute([':id'=>$teacher_user_id, ':ce'=>$codeEcole]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: affectation.php?msg=invalid'); exit;
        }

        // Si password existe → désactiver (vider)
        if (!empty($user['password'])) {
            $up = $pdo->prepare("UPDATE users SET password='' WHERE id=:id");
            $up->execute([':id'=>$teacher_user_id]);

            header('Location: affectation.php?teacher_user_id='.$teacher_user_id.'&msg=desactivated');
            exit;
        } 
        // Sinon → activer (mettre nouveau mot de passe)
        else {
            $newPass = $_POST['new_password'] ?? '';

            if (strlen($newPass) < 4) {
                header('Location: affectation.php?teacher_user_id='.$teacher_user_id.'&msg=invalid');
                exit;
            }

            $hash = password_hash($newPass, PASSWORD_DEFAULT);

            $up = $pdo->prepare("UPDATE users SET password=:p WHERE id=:id");
            $up->execute([
                ':p'=>$hash,
                ':id'=>$teacher_user_id
            ]);

            header('Location: affectation.php?teacher_user_id='.$teacher_user_id.'&msg=activated');
            exit;
        }

    } catch (Throwable $e) {
        if (DEBUG) { echo $e->getMessage(); exit; }
        header('Location: affectation.php?msg=error'); exit;
    }
}

}

// ---- Si un prof est sélectionné/valide, charger ses classes affectées (liste détaillée) ----
$teacherDetails = null;
$teacherClasses = []; // classes affectées à ce prof
if ($prefTeacherValid) {
    $teacherDetails = $profIndex[$prefTeacherId];

    try {
        $st = $pdo->prepare("
            SELECT c.id, cst.titulaire, c.classe, c.description AS desc_classe,
                   n.description AS niveau, s.description AS section, o.description AS opt
            FROM class_subject_teacher cst
            JOIN classes c ON c.id = cst.class_id
            LEFT JOIN niveau  n ON c.niveau = n.id
            LEFT JOIN section s ON c.section = s.id
            LEFT JOIN options  o ON c.options = o.id
            WHERE cst.teacher_user_id = :tid AND cst.code_ecole = :ce
            ORDER BY n.description, s.description, o.description, c.classe, c.description
        ");
        $st->execute([':tid'=>$prefTeacherId, ':ce'=>$codeEcole]);
        $teacherClasses = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        if (DEBUG) { echo $e->getMessage(); }
    }
}

?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Affectation prof → classes</title>
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
        background: #fafafa;
    }

    .chip-ok {
        background: #e8f8ef;
        border-color: #cdeee0;
        color: #1f7a4a;
    }

    .chip-busy {
        background: #ffe5e7;
        border-color: #ffd1d6;
        color: #9f1f2b;
    }

    .class-item {
        border: 1px solid #eee;
        border-radius: .5rem;
        padding: .5rem .75rem;
        margin-bottom: .35rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .class-left {
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    .search {
        max-width: 380px;
    }

    .kbd {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 2px 6px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
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
                        <h3>Affecter un professeur à des classes</h3>
                        <p class="muted mb-0">Ajouter ou retirer des classes d’un professeur existant.</p>
                        <?php if ($msg): ?><div class="alert alert-info mt-2"><?= $msg ?></div><?php endif; ?>
                    </div>
                    <div>
                        <a class="btn btn-outline-secondary btn-sm" href="all-teacher.php">← Retour à la liste</a>
                    </div>
                </div>

                <!-- FICHE PROF + CLASSES AFFECTÉES -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form id="teacherSelectForm" method="get" class="row">
                            <div class="col-lg-6 form-group">
                                <label>Professeur * </label>
                                <?php if ($prefTeacherValid):
                                $p = $teacherDetails;
                                $display = trim(($p['first_name']??'').' '.($p['last_name']??''));
                                if ($display === '') $display = $p['username'];
                                $display2 = $display.' — '.$p['email'];
                            ?>
                                <input type="hidden" name="teacher_user_id" value="<?= (int)$prefTeacherId ?>">
                                <div class="form-control-plaintext font-weight-bold"><?= e($display2) ?></div>
                                <div class="muted small">ID utilisateur : <span
                                        class="kbd"><?= (int)$prefTeacherId ?></span> • Créé le
                                    <?= e($p['created_at'] ?? '—') ?></div>
                                <?php if (!empty($p['phone'])): ?>
                                <div class="muted small">Téléphone : <?= e($p['phone']) ?></div>
                                <?php endif; ?>
                                <?php else: ?>
                                <select name="teacher_user_id" id="teacher_user_id" class="form-control" required>
                                    <option value="">— Choisir un professeur —</option>
                                    <?php foreach ($profs as $pp):
                                        $label = trim(($pp['first_name']??'').' '.($pp['last_name']??''));
                                        if ($label==='') $label = $pp['username'];
                                        $label .= ' — '.$pp['email'];
                                        $sel = ($prefTeacherId>0 && (int)$pp['id']===$prefTeacherId) ? 'selected' : '';
                                    ?>
                                    <option value="<?= (int)$pp['id'] ?>" <?= $sel ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="muted">Le détail du professeur s’affichera automatiquement.</small>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label>Filtrer les classes</label>
                                <input type="text" id="classSearch" class="form-control search"
                                    placeholder="Rechercher une classe, niveau, section, option...">
                                <small class="muted">Tapez pour filtrer la liste ci-dessous.</small>
                            </div>
                        </form>

                        <?php if ($prefTeacherValid): ?>
                        <hr>
                        <h6 class="mb-2">Classes actuellement affectées à ce professeur</h6>
                        <?php if (!$teacherClasses): ?>
                        <div class="muted">Aucune classe affectée pour l’instant.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Classe</th>
                                        <th>Status</th>
                                        <!-- <th>ID</th> -->
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherClasses as $c):
                                        $cid = (int)$c['id'];
                                        $lbl = trim(($c['classe']??'').' '.($c['desc_classe']??'').' '.($c['niveau']??'').' '.($c['section']??'').' '.($c['opt']??''));
                                        if ($lbl==='') $lbl = 'Classe #'.$cid;
                                    ?>
                                    <tr>
                                        <td><?= e($lbl) ?></td>
                                        <td>
                                            <?php if ((int)$c['titulaire'] === 1): ?>
                                            <span class="badge badge-success">
                                                Titulaire
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-secondary">
                                                Enseignant
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- <td><span class="kbd"><?= $cid ?></span></td> -->
                                        <td>
                                            <form method="post" class="d-inline"
                                                onsubmit="return confirm('Retirer cette classe du professeur ?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="teacher_user_id"
                                                    value="<?= (int)$prefTeacherId ?>">
                                                <input type="hidden" name="class_id" value="<?= $cid ?>">
                                                <button class="btn btn-sm btn-outline-danger">Retirer</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AJOUTER DES CLASSES (CHECKBOX) -->
                <div class="card">
                    <div class="card-body">
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-12">
                                    <?php if ($prefTeacherValid): ?>
                                    <input type="hidden" name="teacher_user_id" value="<?= (int)$prefTeacherId ?>">
                                    <?php else: ?>
                                    <label>Professeur *</label>
                                    <select name="teacher_user_id" class="form-control mb-2" required>
                                        <option value="">— Choisir un professeur —</option>
                                        <?php foreach ($profs as $pp):
                                            $label = trim(($pp['first_name']??'').' '.($pp['last_name']??''));
                                            if ($label==='') $label = $pp['username'];
                                            $label .= ' — '.$pp['email'];
                                            $sel = ($prefTeacherId>0 && (int)$pp['id']===$prefTeacherId) ? 'selected' : '';
                                        ?>
                                        <option value="<?= (int)$pp['id'] ?>" <?= $sel ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <label>Classes (cochez une ou plusieurs) *</label>
                                    <div id="classesList">
                                        <?php
                                        foreach ($classes as $c):
                                        $cid = (int)$c['id'];
                                        $lbl = trim(($c['classe']??'').' '.($c['desc_classe']??'').' '.($c['niveau']??'').' '.($c['section']??'').' '.($c['opt']??''));
                                        if ($lbl==='') $lbl = 'Classe #'.$cid;

                                        $teachersInClass = $affects[$cid] ?? [];

                                        $isMine = (
                                            $prefTeacherValid &&
                                            isset($affects[$cid][$prefTeacherId])
                                        );

                                        $checked = isset($affects[$cid][$prefTeacherId]) ? 'checked' : '';
                                        $isBusy = false; // ❌ PLUS DE BLOCAGE

                                        $titulaireChecked = (
                                            isset($affects[$cid][$prefTeacherId]) &&
                                            (int)$affects[$cid][$prefTeacherId] === 1
                                        ) ? 'checked' : '';
                                    ?>
                                        <div class="class-item" data-text="<?= e(mb_strtolower($lbl)) ?>">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="class-left">
                                                        <input type="checkbox" name="classes[]" value="<?= $cid ?>"
                                                            <?= $checked ?> <?= $disabled ?>>
                                                        <div>
                                                            <div><strong><?= e($lbl) ?></strong></div>
                                                            <!-- <div class="muted small">ID #<?= $cid ?></div> -->
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check mt-1">
                                                        <?php
                                                            $titulaireChecked = (
                                                                isset($affects[$cid][$prefTeacherId]) &&
                                                                (int)$affects[$cid][$prefTeacherId] === 1
                                                            ) ? 'checked' : '';
                                                        ?>

                                                        <input class="form-check-input" type="checkbox"
                                                            name="titulaire[<?= $cid ?>]" value="1"
                                                            id="titulaire<?= $cid ?>" <?= $titulaireChecked ?>>
                                                        <label class="form-check-label" for="titulaire<?= $cid ?>">
                                                            Titulaire
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div>
                                                        <?php if ($isMine): ?>
                                                        <span class="chip chip-ok">Déjà affectée à ce prof</span>
                                                        <?php elseif (!empty($teachersInClass)): ?>
                                                        <span class="chip chip-busy">
                                                            Déjà affectée à <?= count($teachersInClass) ?> prof(s)
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="chip">Disponible</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>





                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="muted d-block mt-1">
                                        Une classe peut avoir plusieurs professeurs.
                                    </small>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-primary">Enregistrer l’affectation</button>
                                <a href="all-teacher.php" class="btn btn-outline-secondary">Retour</a>
                            </div>
                        </form>
                    </div>

                    <div class="card mt-5">
                        <div class="card-body">
                            <h6 class="mb-2">Gestion du compte</h6>

                            <?php
                                $hasPassword = !empty($teacherDetails['password'] ?? '');
                                ?>

                            <?php if ($hasPassword): ?>
                            <!-- Désactiver -->
                            <form method="post" class="d-inline" onsubmit="return confirm('Désactiver ce compte ?');">
                                <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                                <input type="hidden" name="action" value="toggle_account">
                                <input type="hidden" name="teacher_user_id" value="<?= (int)$prefTeacherId ?>">
                                <button class="btn btn-danger btn-lg">Désactiver le compte</button>
                            </form>
                            <?php else: ?>
                            <!-- Activer -->
                            <button class="btn btn-danger btn-lg" data-toggle="modal" data-target="#modalActivate">
                                Activer ce compte enseignant/professeur
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <?php $foot=__DIR__.'/layout/footer.php'; if(file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalActivate">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Activer le compte</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                        <input type="hidden" name="action" value="toggle_account">
                        <input type="hidden" name="teacher_user_id" value="<?= (int)$prefTeacherId ?>">

                        <div class="col-xl-12 col-lg-12 col-12 form-group">
                            <label>Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control"
                                placeholder="Entrer à nouveau le mot de passe" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-success btn-lg">Activer</button>
                        <button type="button" class="btn btn-secondary btn-lg" data-dismiss="modal">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script>
    // Redirection automatique quand on choisit un prof (pour afficher sa fiche et ses classes)
    $(function() {
        $('#teacher_user_id').on('change', function() {
            const v = $(this).val();
            if (v) {
                window.location.href = 'affectation.php?teacher_user_id=' + encodeURIComponent(v);
            }
        });

        // Filtre client pour les classes
        $('#classSearch').on('input', function() {
            const q = $(this).val().toString().trim().toLowerCase();
            if (!q) {
                $('#classesList .class-item').show();
                return;
            }
            $('#classesList .class-item').each(function() {
                const txt = $(this).attr('data-text') || '';
                $(this).toggle(txt.indexOf(q) !== -1);
            });
        });
    });
    </script>
</body>

</html>