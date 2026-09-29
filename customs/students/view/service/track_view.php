<?php
// customs/students/view/service/track_view.php
// Journalise une vue de contenu d'une leçon puis redirige vers le viewer.

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// -------- DB connect robuste (selon ton arborescence) --------
$pdo = null;
foreach ([__DIR__.'/../../../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }

// -------- Contrôle d'accès --------
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($role !== 'eleve') {
    header('Location: ../../../../login/index.php?msg=forbidden'); exit;
}
$code_ecole = $_SESSION['code_ecole'] ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

// -------- Helpers --------
function redirectTo(string $url): void { header('Location: '.$url); exit; }
function bad(string $m): void { http_response_code(400); exit($m); }
function findStudent(PDO $pdo, ?string $email, ?string $username, ?string $codeEcole): ?array {
    try {
        if ($email) {
            $sql="SELECT * FROM students WHERE email=:em";
            $p=[":em"=>$email];
            if ($codeEcole) { $sql.=" AND code_ecole=:ce"; $p[":ce"]=$codeEcole; }
            $sql.=" LIMIT 1";
            $st=$pdo->prepare($sql); $st->execute($p);
            if ($r=$st->fetch(PDO::FETCH_ASSOC)) return $r;
        }
        if ($username) {
            $sql="SELECT * FROM students WHERE username=:un";
            $p=[":un"=>$username];
            if ($codeEcole) { $sql.=" AND code_ecole=:ce"; $p[":ce"]=$codeEcole; }
            $sql.=" LIMIT 1";
            $st=$pdo->prepare($sql); $st->execute($p);
            if ($r=$st->fetch(PDO::FETCH_ASSOC)) return $r;
        }
    } catch(Throwable $e) {}
    return null;
}
function getClientIpBin(): ?string {
    if (empty($_SERVER['REMOTE_ADDR'])) return null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $bin = @inet_pton($ip);
    return $bin !== false ? $bin : null;
}

// -------- Inputs --------
$type     = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$contentId= isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$leconId  = isset($_GET['lecon_id']) && ctype_digit($_GET['lecon_id']) ? (int)$_GET['lecon_id'] : 0;

if (!in_array($type, ['pdf','video','audio'], true)) bad('type invalide');
if ($contentId <= 0 || $leconId <= 0) bad('Paramètres manquants');

// -------- Élève courant --------
$student = findStudent($pdo, $email, $username, $code_ecole);
if (!$student) { bad('Élève introuvable.'); }
$student_id = (int)$student['id'];
$class_id   = (int)($student['class_id'] ?? 0);
if ($class_id <= 0) { bad('Classe non définie.'); }

// -------- Vérifier la leçon et l’accès --------
try {
    $sql = "SELECT l.id, l.cours_id, c.class, c.code_ecole
            FROM lecons l
            JOIN cours c ON c.id = l.cours_id
            WHERE l.id=:lid LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':lid'=>$leconId]);
    $lecon = $st->fetch(PDO::FETCH_ASSOC);
    if (!$lecon) { bad('Leçon inconnue.'); }

    // même classe
    if ((int)$lecon['class'] !== $class_id) { bad('Accès refusé (classe).'); }
    // même école (si policy)
    if ($code_ecole && !empty($lecon['code_ecole']) && $lecon['code_ecole'] !== $code_ecole) {
        bad('Accès refusé (école).');
    }
} catch(Throwable $e){ bad('Erreur leçon.'); }

// -------- Valider l’existence du contenu et son rattachement à la classe --------
try {
    $table = $type === 'pdf' ? 'pdfs' : ($type === 'video' ? 'videos' : 'audios');
    // Les tables ont un champ `class` et (souvent) `code_ecole`
    $sql = "SELECT id, `class`, ".($code_ecole?'code_ecole,':'')." uploaded_at
            FROM {$table}
            WHERE id=:cid";
    $p = [':cid'=>$contentId];

    if ($code_ecole) {
        $sql .= " AND code_ecole=:ce";
        $p[':ce'] = $code_ecole;
    }
    $sql .= " LIMIT 1";

    $st=$pdo->prepare($sql);
    $st->execute($p);
    $content = $st->fetch(PDO::FETCH_ASSOC);
    if (!$content) { bad('Contenu introuvable.'); }
    if ((int)$content['class'] !== $class_id) { bad('Accès refusé (contenu/classe).'); }
} catch(Throwable $e){ bad('Erreur contenu.'); }

// -------- Enregistrer la vue --------
try {
    $ip   = getClientIpBin();
    $ua   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ins  = $pdo->prepare("
        INSERT INTO content_views
            (code_ecole, student_id, class_id, lecon_id, content_type, content_id, viewed_at, ip, user_agent)
        VALUES
            (:ce, :sid, :cid, :lid, :typ, :ctid, NOW(), :ip, :ua)
    ");
    $ins->execute([
        ':ce'   => $code_ecole,
        ':sid'  => $student_id,
        ':cid'  => $class_id,
        ':lid'  => $leconId,
        ':typ'  => $type,
        ':ctid' => $contentId,
        ':ip'   => $ip,
        ':ua'   => $ua,
    ]);
} catch(Throwable $e){
    // On ne bloque pas la lecture si le log échoue ; on continue.
}

// -------- Redirection vers le viewer --------
$target = '../voir_contenu.php?type='.$type.'&id='.$contentId;
redirectTo($target);
