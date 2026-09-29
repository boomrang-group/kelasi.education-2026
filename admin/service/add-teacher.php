<?php
// service/add-teacher.php — insertion + mails (sans session_check)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

 // DEBUG handled in db_connect.php


// DB connect
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php', __DIR__.'/../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/../../service/security_helpers.php';

csrf_protect();

// Auth + code_ecole
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isAdmin = in_array($role, ['admin','administrateur'], true);

$code_ecole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId && !$code_ecole) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $code_ecole = (string)($st->fetchColumn() ?: '');
    if ($code_ecole) $_SESSION['code_ecole'] = $code_ecole;
}
if (!$isAdmin || !$code_ecole) { http_response_code(403); exit('Accès refusé (admin requis + code école manquant).'); }

// Méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_teacher'])) {
    header('Location: ../add-teacher.php?msg=invalid'); exit;
}

// Données du formulaire
$first_name     = trim((string)($_POST['first_name'] ?? ''));
$last_name      = trim((string)($_POST['last_name'] ?? ''));
$email          = trim((string)($_POST['email'] ?? ''));
$gender         = trim((string)($_POST['gender'] ?? ''));
$dob            = trim((string)($_POST['date_of_birth'] ?? ''));     // yyyy-mm-dd (optionnel)
$phone          = trim((string)($_POST['phone'] ?? ''));
$address        = trim((string)($_POST['address'] ?? ''));
$qualification  = trim((string)($_POST['qualification'] ?? ''));
$specialization = trim((string)($_POST['specialization'] ?? ''));
$classes        = $_POST['classes'] ?? [];                           // <-- multi-classes
$joining_date   = trim((string)($_POST['date_of_joining'] ?? ''));   // yyyy-mm-dd (optionnel)

$username       = trim((string)($_POST['username'] ?? ''));
$password_plain = (string)($_POST['password'] ?? '');

// Validation basique
$missing = [];
if ($first_name==='')      $missing[]='first_name';
if ($last_name==='')       $missing[]='last_name';
if ($email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $missing[]='email';
if ($gender==='')          $missing[]='gender';
if ($qualification==='')   $missing[]='qualification';
if (empty($classes) || !is_array($classes)) $missing[]='classes';
if ($username==='')        $missing[]='username';
if ($password_plain==='')  $missing[]='password';

if ($missing) {
    if (DEBUG) { echo "Champs manquants: ".implode(', ', $missing); exit; }
    header('Location: ../add-teacher.php?msg=invalid'); exit;
}

// Normaliser liste de classes (ints uniques)
$classes = array_values(array_unique(array_filter(array_map(static function($v){
    return ctype_digit((string)$v) ? (int)$v : 0;
}, (array)$classes), static fn($x)=>$x>0)));

if (!$classes) {
    header('Location: ../add-teacher.php?msg=invalid'); exit;
}

// Vérifier que toutes les classes appartiennent à l’école
$in = implode(',', array_fill(0, count($classes), '?'));
$st = $pdo->prepare("SELECT id FROM classes WHERE code_ecole = ? AND id IN ($in)");
$st->execute(array_merge([$code_ecole], $classes));
$found = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

if (count($found) !== count($classes)) {
    // Au moins une classe ne correspond pas à cette école
    header('Location: ../add-teacher.php?msg=badclass'); exit;
}

// Vérifier unicité username / email dans users
$st = $pdo->prepare("SELECT 1 FROM users WHERE username = :u OR email = :e LIMIT 1");
$st->execute([':u'=>$username, ':e'=>$email]);
if ($st->fetchColumn()) {
    header('Location: ../add-teacher.php?msg=exists'); exit;
}

try {
    $pdo->beginTransaction();

    // 1) Créer le user (role 'prof')
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
    $insU = $pdo->prepare("
        INSERT INTO users (username, `PASSWORD`, email, role, first_name, last_name, phone, numero_bancaire, code_ecole, created_at, updated_at)
        VALUES (:u, :p, :e, 'prof', :fn, :ln, :ph, '', :code, NOW(), NOW())
    ");
    $insU->execute([
        ':u'=>$username, ':p'=>$password_hash, ':e'=>$email,
        ':fn'=>$first_name, ':ln'=>$last_name, ':ph'=>$phone, ':code'=>$code_ecole
    ]);
    $user_id = (int)$pdo->lastInsertId();

    // 2) Créer la fiche teacher
    $insT = $pdo->prepare("
        INSERT INTO teacher (first_name, last_name, email, gender, date_of_birth, phone, adress, qualification, specialization, classe, date_of_joining, code_ecole)
        VALUES (:fn,:ln,:em,:ge,:dob,:ph,:ad,:qu,:sp,:cl,:jo,:code)
    ");
    // NOTE: le champ `classe` dans la table teacher existe ; on y met la première classe pour info (ou chaîne join)
    $firstClass = $classes[0] ?? null;
    $insT->execute([
        ':fn'=>$first_name, ':ln'=>$last_name, ':em'=>$email, ':ge'=>$gender,
        ':dob'=>$dob ?: null, ':ph'=>$phone, ':ad'=>$address,
        ':qu'=>$qualification, ':sp'=>$specialization, ':cl'=>$firstClass,
        ':jo'=>$joining_date ?: null, ':code'=>$code_ecole
    ]);
    $teacher_id = (int)$pdo->lastInsertId();

    // 3) Lier dans class_subject_teacher pour CHAQUE classe
    $insCST = $pdo->prepare("
        INSERT INTO class_subject_teacher (class_id, teacher_user_id, username, code_ecole)
        VALUES (:cid, :tid, :u, :code)
    ");

    $skipped = [];
    foreach ($classes as $cid) {
        try {
            // IMPORTANT : teacher_user_id = users.id (pas teacher.id)
            $insCST->execute([
                ':cid'=>$cid, ':tid'=>$user_id, ':u'=>$username, ':code'=>$code_ecole
            ]);
        } catch (Throwable $e) {
            // Contrainte unique sur class_id => classe déjà liée à un autre enseignant
            // SQLSTATE 23000 = Integrity constraint violation
            if ($e instanceof PDOException && $e->getCode()==='23000') {
                $skipped[] = (string)$cid;
                continue;
            }
            throw $e;
        }
    }

    $pdo->commit();

    // Infos école + admin email pour mail
    $stE = $pdo->prepare("SELECT nom_ecole FROM ecoles WHERE code_ecole = :c LIMIT 1");
    $stE->execute([':c'=>$code_ecole]);
    $ecole_name = (string)($stE->fetchColumn() ?: '');

    $admin_email = null;
    if ($userId) {
        $stA = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
        $stA->execute([':id'=>$userId]);
        $admin_email = (string)($stA->fetchColumn() ?: '');
    }

    // Mails (prof + admin)
    $fullName = trim($first_name.' '.$last_name);
    email_teacher_credentials($email, $fullName, $username, $password_plain, $ecole_name ?: 'Votre école', $code_ecole, $admin_email);

    $redir = '../add-teacher.php?msg=created';
    if ($skipped) { $redir .= '&skipped='.rawurlencode(implode(', ', $skipped)); }
    header('Location: '.$redir); exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (DEBUG) { echo 'Erreur: '.$e->getMessage(); exit; }
    header('Location: ../add-teacher.php?msg=error'); exit;
}