<?php
// customs/students/view/profil.php
// Profil élève : mise à jour informations personnelles + changement de mot de passe

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';

// ---- Sécurité : élève requis ----
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden'); exit;
}

$userId     = (int)($_SESSION['user_id'] ?? 0);           // users.id si connecté via table users
$studentId  = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0; // students.id si mappé
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

// ---------- Helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s){ return trim((string)$s); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

/** Tente de retrouver la fiche students prioritairement par session student_id,
 *  sinon par email/username (avec code_ecole si dispo) */
function findStudent(PDO $pdo, int $studentId, ?string $email, ?string $username, ?string $codeEcole): ?array {
    if ($studentId > 0) {
        $st = $pdo->prepare("SELECT * FROM students WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$studentId]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    if ($email) {
        if ($codeEcole) {
            $st = $pdo->prepare("SELECT * FROM students WHERE email=:em AND code_ecole=:ce LIMIT 1");
            $st->execute([':em'=>$email, ':ce'=>$codeEcole]);
        } else {
            $st = $pdo->prepare("SELECT * FROM students WHERE email=:em LIMIT 1");
            $st->execute([':em'=>$email]);
        }
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    if ($username) {
        if ($codeEcole) {
            $st = $pdo->prepare("SELECT * FROM students WHERE username=:un AND code_ecole=:ce LIMIT 1");
            $st->execute([':un'=>$username, ':ce'=>$codeEcole]);
        } else {
            $st = $pdo->prepare("SELECT * FROM students WHERE username=:un LIMIT 1");
            $st->execute([':un'=>$username]);
        }
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    return null;
}

/** Charge entry users si c'est un compte users role=eleve */
function loadUserIfEleve(PDO $pdo, int $userId): ?array {
    if ($userId <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM users WHERE id=:id AND role='eleve' LIMIT 1");
    $st->execute([':id'=>$userId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Vérifie mot de passe saisi vs hash (et rétro-compat clair < 60 chars) */
function check_password(string $input, string $stored): bool {
    return password_verify($input, $stored) || (strlen($stored) < 60 && hash_equals($stored, $input));
}

/** Met à jour password hashé dans students et/ou users selon présence */
function update_passwords(PDO $pdo, ?int $studentId, ?int $userId, string $newHash): void {
    if ($studentId && $studentId > 0) {
        $st = $pdo->prepare("UPDATE students SET PASSWORD=:p WHERE id=:id");
        $st->execute([':p'=>$newHash, ':id'=>$studentId]);
    }
    if ($userId && $userId > 0) {
        $st = $pdo->prepare("UPDATE users SET PASSWORD=:p WHERE id=:id AND role='eleve'");
        $st->execute([':p'=>$newHash, ':id'=>$userId]);
    }
}

/** Synchronise quelques champs vers users si compte users eleve existe */
function sync_users_from_student(PDO $pdo, int $userId, array $stu): void {
    if ($userId <= 0) return;
    $fields = [
        'first_name' => $stu['first_name'] ?? null,
        'last_name'  => $stu['last_name']  ?? null,
        'email'      => $stu['email']      ?? null,
        'username'   => $stu['username']   ?? null,
        'phone'      => $stu['phone']      ?? null,
        'code_ecole' => $stu['code_ecole'] ?? null,
    ];
    $sql = "UPDATE users SET first_name=:fn, last_name=:ln, email=:em, username=:un, phone=:ph, code_ecole=:ce WHERE id=:id AND role='eleve'";
    $pdo->prepare($sql)->execute([
        ':fn'=>$fields['first_name'], ':ln'=>$fields['last_name'], ':em'=>$fields['email'],
        ':un'=>$fields['username'], ':ph'=>$fields['phone'], ':ce'=>$fields['code_ecole'], ':id'=>$userId
    ]);
}

// =================== Chargement initial ===================
$errors = [];
$success = '';

$userRow = loadUserIfEleve($pdo, $userId);
$stuRow  = findStudent($pdo, $studentId, $email, $username, $code_ecole);
if (!$stuRow) {
    $errors[] = "Impossible de charger votre profil élève (table students). Contactez l'administration.";
}

// =================== POST: mise à jour infos ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form']==='info' && $stuRow) {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $errors[] = "CSRF invalide.";
    } else {
        // Champs autorisés (students)
        $first_name        = clean($_POST['first_name'] ?? '');
        $last_name         = clean($_POST['last_name'] ?? '');
        $username_new      = clean($_POST['username'] ?? '');
        $email_new         = clean($_POST['email'] ?? '');
        $phone             = clean($_POST['phone'] ?? '');
        $gender            = clean($_POST['gender'] ?? '');
        $date_of_birth     = clean($_POST['date_of_birth'] ?? '');
        $father            = clean($_POST['father'] ?? '');
        $mother            = clean($_POST['mother'] ?? '');
        $phone_resp        = clean($_POST['phone_responsable'] ?? '');
        $email_resp        = clean($_POST['email_responsable'] ?? '');
        $ecole_provenance  = clean($_POST['ecole_provenance'] ?? '');

        // Validations simples
        if ($first_name==='') $errors[]="Prénom requis.";
        if ($last_name==='')  $errors[]="Nom requis.";
        if ($username_new==='') $errors[]="Nom d'utilisateur requis.";
        if ($email_new==='' || !filter_var($email_new, FILTER_VALIDATE_EMAIL)) $errors[]="E-mail invalide.";

        // Unicité username dans students (sauf moi)
        if ($username_new !== ($stuRow['username'] ?? '')) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM students WHERE username=:un AND id<>:id");
            $st->execute([':un'=>$username_new, ':id'=>(int)$stuRow['id']]);
            if ((int)$st->fetchColumn() > 0) $errors[] = "Ce nom d'utilisateur est déjà utilisé.";
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $sql = "UPDATE students SET
                            first_name=:fn, last_name=:ln, username=:un, email=:em, phone=:ph,
                            gender=:gd, date_of_birth=:dob, father=:fa, mother=:mo,
                            phone_responsable=:pr, email_responsable=:er, ecole_provenance=:ep
                        WHERE id=:id";
                $pdo->prepare($sql)->execute([
                    ':fn'=>$first_name, ':ln'=>$last_name, ':un'=>$username_new, ':em'=>$email_new, ':ph'=>$phone,
                    ':gd'=>($gender!==''?$gender:null),
                    ':dob'=>($date_of_birth!==''?$date_of_birth:null),
                    ':fa'=>$father!==''?$father:null,
                    ':mo'=>$mother!==''?$mother:null,
                    ':pr'=>$phone_resp!==''?$phone_resp:null,
                    ':er'=>$email_resp!==''?$email_resp:null,
                    ':ep'=>$ecole_provenance!==''?$ecole_provenance:null,
                    ':id'=>(int)$stuRow['id']
                ]);

                // Rafraîchir stuRow
                $st = $pdo->prepare("SELECT * FROM students WHERE id=:id");
                $st->execute([':id'=>(int)$stuRow['id']]);
                $stuRow = $st->fetch(PDO::FETCH_ASSOC) ?: $stuRow;

                // Synchroniser vers users si compte users élève existe
                if ($userRow) {
                    sync_users_from_student($pdo, (int)$userRow['id'], $stuRow);
                    // Mettre aussi à jour la session (username/email)
                    $_SESSION['username'] = $stuRow['username'] ?? $_SESSION['username'];
                    $_SESSION['email']    = $stuRow['email']    ?? $_SESSION['email'];
                }

                $pdo->commit();
                $success = "Informations mises à jour.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Erreur lors de la mise à jour des informations.";
                if ($DEBUG) $errors[] = e($e->getMessage());
            }
        }
    }
}

// =================== POST: changement de mot de passe ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form']==='pwd' && $stuRow) {
    if (!csrf_check((string)($_POST['_csrf'] ?? ''))) {
        $errors[] = "CSRF invalide.";
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new1    = (string)($_POST['new_password'] ?? '');
        $new2    = (string)($_POST['new_password2'] ?? '');

        if ($current==='') $errors[]="Mot de passe actuel requis.";
        if ($new1==='' || strlen($new1)<6) $errors[]="Nouveau mot de passe : 6 caractères minimum.";
        if ($new1 !== $new2) $errors[]="Les deux nouveaux mots de passe ne correspondent pas.";

        // Déterminer où vérifier le mot de passe actuel : priorité users (si connecté via users), sinon students
        $storedHash = null;
        if ($userRow) {
            $storedHash = (string)$userRow['PASSWORD'];
        } else {
            $storedHash = (string)($stuRow['PASSWORD'] ?? '');
        }

        if (!$errors) {
            if (!check_password($current, $storedHash)) {
                $errors[] = "Mot de passe actuel incorrect.";
            } else {
                try {
                    $hash = password_hash($new1, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    update_passwords($pdo, (int)$stuRow['id'], $userRow ? (int)$userRow['id'] : null, $hash);
                    $pdo->commit();
                    $success = "Mot de passe mis à jour.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[]="Erreur lors de la mise à jour du mot de passe.";
                    if ($DEBUG) $errors[]=e($e->getMessage());
                }
            }
        }
    }
}

// Valeurs pour le formulaire
$val = $stuRow ?: [];
$fullName = trim(($val['first_name']??'').' '.($val['last_name']??''));
if ($fullName==='') $fullName = $val['username'] ?? 'Mon profil';

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mon profil élève</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <style>
        .card{border:0;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,.05)}
        .muted{color:#6b7280}
        .form-section{margin-bottom:1.25rem}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>
        <div class="dashboard-content-one">

            <div class="breadcrumbs-area">
                <h3>👤 Mon profil</h3>
                <p class="muted mb-0">Mettez à jour vos informations.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">Informations personnelles</h5>
                            <form method="post" action="" autocomplete="off">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="form" value="info">

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Prénom</label>
                                        <input type="text" name="first_name" class="form-control" value="<?= e($val['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Nom</label>
                                        <input type="text" name="last_name" class="form-control" value="<?= e($val['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Nom d'utilisateur</label>
                                        <input type="text" name="username" class="form-control" value="<?= e($val['username'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>E-mail</label>
                                        <input type="email" name="email" class="form-control" value="<?= e($val['email'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Téléphone</label>
                                        <input type="text" name="phone" class="form-control" value="<?= e($val['phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Sexe</label>
                                        <select name="gender" class="form-control">
                                            <?php $g = strtolower((string)($val['gender'] ?? '')); ?>
                                            <option value="">—</option>
                                            <option value="male"   <?= $g==='male'?'selected':'' ?>>Masculin</option>
                                            <option value="female" <?= $g==='female'?'selected':'' ?>>Féminin</option>
                                            <option value="other"  <?= $g==='other'?'selected':'' ?>>Autre</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Date de naissance</label>
                                        <input type="date" name="date_of_birth" class="form-control" value="<?= e($val['date_of_birth'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6 class="mb-2">Responsables</h6>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Père</label>
                                            <input type="text" name="father" class="form-control" value="<?= e($val['father'] ?? '') ?>">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Mère</label>
                                            <input type="text" name="mother" class="form-control" value="<?= e($val['mother'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Téléphone du responsable</label>
                                            <input type="text" name="phone_responsable" class="form-control" value="<?= e($val['phone_responsable'] ?? '') ?>">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>E-mail du responsable</label>
                                            <input type="email" name="email_responsable" class="form-control" value="<?= e($val['email_responsable'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>École de provenance</label>
                                    <input type="text" name="ecole_provenance" class="form-control" value="<?= e($val['ecole_provenance'] ?? '') ?>">
                                </div>

                                <div class="text-right">
                                    <button class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div><!-- /col infos -->

                <div class="d-none col-lg-5">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">Changer mon mot de passe</h5>
                            <form method="post" action="" autocomplete="off">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="form" value="pwd">

                                <div class="form-group">
                                    <label>Mot de passe actuel</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Nouveau mot de passe</label>
                                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="new_password2" class="form-control" minlength="6" required>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="showPwd">
                                    <label class="form-check-label" for="showPwd">Afficher les mots de passe</label>
                                </div>

                                <div class="text-right">
                                    <button class="btn btn-outline-primary">Mettre à jour le mot de passe</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="muted">
                        Besoin d’aide ? Contactez l’administrateur de votre école.
                    </div>
                </div><!-- /col pwd -->
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

<script src="../../../js/jquery-3.3.1.min.js"></script>
<script src="../../../js/bootstrap.min.js"></script>
<script>
// Afficher / Masquer les mots de passe
(function(){
    var chk = document.getElementById('showPwd');
    if (!chk) return;
    chk.addEventListener('change', function(){
        document.querySelectorAll('input[name="current_password"], input[name="new_password"], input[name="new_password2"]').forEach(function(el){
            var t = el.getAttribute('type');
            el.setAttribute('type', t === 'password' ? 'text' : 'password');
        });
    });
})();
</script>
</body>
</html>
