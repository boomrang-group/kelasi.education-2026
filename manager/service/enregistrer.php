<?php
// my_school/service/enregistrer.php
declare(strict_types=1);

// ==== DEV (désactiver en prod) ====
 // display_errors handled in db_connect.php
 // display_startup_errors handled in db_connect.php
 // error_reporting handled in db_connect.php

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Accès : promoteur requis
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($role !== 'promoteur') { header('Location: ../../login/?msg=forbidden'); exit; }

// DB
$pdoPathCandidates = [
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../../../database/db_connect.php',
];
foreach ($pdoPathCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur BDD.'); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$PROMOTEUR_ID = (int)($_SESSION['user_id'] ?? 0);
if ($PROMOTEUR_ID <= 0) { header('Location: ../../login/?msg=login_required'); exit; }

/* ======================
   Utils
   ====================== */
function clean_str(?string $s): string { return trim((string)$s); }
function only_alnum(?string $s): string { return preg_replace('/[^A-Za-z0-9]/', '', (string)$s); }
function slug_base(string $s): string {
    $x = iconv('UTF-8','ASCII//TRANSLIT',$s);
    $x = preg_replace('/[^A-Za-z0-9\-]+/','-',$x ?? '');
    $x = preg_replace('/-+/','-',$x);
    $x = trim($x,'-');
    return strtolower($x ?: 'ecole');
}
/** URL <= 20 chars, unique dans landingpage.url_ecole */
function slug20_unique(PDO $pdo, string $base): string {
    $base = substr(slug_base($base), 0, 20);
    if ($base === '') $base = 'ecole';
    $exists = function(string $url) use ($pdo): bool {
        $q = $pdo->prepare("SELECT 1 FROM landingpage WHERE url_ecole = ? LIMIT 1");
        $q->execute([$url]);
        return (bool)$q->fetchColumn();
    };
    if (!$exists($base)) return $base;
    for ($i=1; $i<=9999; $i++) {
        $suf = '-'.$i;
        $room = 20 - strlen($suf);
        $cand = substr($base, 0, max(1,$room)).$suf;
        if (!$exists($cand)) return $cand;
    }
    return substr($base, 0, 19).'-x';
}
/** CODE <= 11 chars, unique dans ecoles.code_ecole */
function code_ecole_unique(PDO $pdo, string $proposed): string {
    $base = strtoupper(only_alnum($proposed));
    if ($base === '') $base = 'ECOLE';
    $base = substr($base, 0, 11);
    $exists = function(string $code) use ($pdo): bool {
        $q = $pdo->prepare("SELECT 1 FROM ecoles WHERE code_ecole = ? LIMIT 1");
        $q->execute([$code]);
        return (bool)$q->fetchColumn();
    };
    if (!$exists($base)) return $base;
    for ($i=1; $i<=9999; $i++) {
        $s = (string)$i;
        $room = 11 - strlen($s);
        $cand = substr($base, 0, max(0,$room)).$s;
        if (!$exists($cand)) return $cand;
    }
    return substr($base, 0, 10).'X';
}
/** Initiales à partir du nom + suffixe type (CS, GS, EP, L., COL) → pour proposition code */
function initials_from_name(string $name): string {
    $tokens = preg_split('/[\s\-_]+/u', trim($name)) ?: [];
    $inis = '';
    foreach ($tokens as $t) { if ($t !== '') $inis .= mb_strtoupper(mb_substr($t,0,1,'UTF-8'),'UTF-8'); }
    return $inis ?: 'E';
}
function compute_code_from_name_type(string $nom, string $type): string {
    $t = strtoupper(str_replace('.','', $type));
    return substr(initials_from_name($nom).$t, 0, 11);
}
function compute_url_from_name_type(string $nom, string $type): string {
    return slug_base($nom.' '.str_replace('.','',$type));
}
/** Upload basique avec sécurité */
function save_upload(string $key, string $prefix, string $uploadBase): ?string {
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) return null;
    $f = $_FILES[$key];
    if (!isset($f['error']) || (int)$f['error'] !== UPLOAD_ERR_OK) return null;

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    $fileExt = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExtensions)) {
        return null;
    }

    // Validation MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        return null;
    }

    $safe = preg_replace('/[^a-z0-9\-_.]/i', '_', basename($f['name']));
    $name = $prefix . '_' . date('Ymd_His') . '_' . $safe;
    $dest = rtrim($uploadBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

    // Prevent double extensions like file.php.jpg
    if (strpos($safe, '.') !== strrpos($safe, '.')) {
         $name = $prefix . '_' . date('Ymd_His') . '_' . str_replace('.', '_', substr($safe, 0, strrpos($safe, '.'))) . '.' . $fileExt;
         $dest = rtrim($uploadBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    if (!@move_uploaded_file($f['tmp_name'], $dest)) return null;
    return $name;
}
function send_mail_simple(string $to, string $subject, string $body): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: MyKelasi <no-reply@mykelasi.local>\r\n";
    return @mail($to, $subject, $body, $headers);
}

/* Promoteur (pour courriel contact) */
$promoteur_email = null; $promoteur_nom = null;
try {
    $stPromo = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id=:id LIMIT 1");
    $stPromo->execute([':id'=>$PROMOTEUR_ID]);
    if ($pr = $stPromo->fetch(PDO::FETCH_ASSOC)) {
        $promoteur_email = $pr['email'] ?? null;
        $promoteur_nom   = trim(($pr['first_name'] ?? '').' '.($pr['last_name'] ?? ''));
    }
} catch(Throwable $e){}

/* Dossier upload */
$uploadBase = __DIR__ . '/../../uploads/ecoles';
if (!is_dir($uploadBase)) { @mkdir($uploadBase, 0775, true); }

$ok = []; $fail = [];
$items = $_POST['ecoles'] ?? [];
if (!is_array($items) || !$items) {
    $_SESSION['flash_error'] = "Aucune donnée reçue.";
    header('Location: ../mykelasi-school.php'); exit;
}

foreach ($items as $idx => $in) {
    try {
        $pdo->beginTransaction();

        // Champs de base
        $nom_ecole   = clean_str($in['nom_ecole']  ?? '');
        $type_ecole  = clean_str($in['type_ecole'] ?? ''); // non stocké, utilisé pour proposer code/url
        $code_input  = clean_str($in['code_ecole'] ?? '');
        $url_input   = clean_str($in['url_ecole']  ?? '');

        if ($nom_ecole === '') {
            throw new RuntimeException("Bloc #".($idx+1).": nom_ecole requis.");
        }

        // Si l’utilisateur a laissé vide, on propose à partir (nom + type)
        if ($code_input === '') { $code_input = compute_code_from_name_type($nom_ecole, $type_ecole); }
        if ($url_input  === '') { $url_input  = compute_url_from_name_type($nom_ecole, $type_ecole); }

        // Génération FINALE UNIQUE côté serveur (source de vérité)
        $code_ecole = code_ecole_unique($pdo, $code_input);      // <= 11
        $url_ecole  = slug20_unique($pdo, $url_input);           // <= 20 (landingpage.url_ecole)

        // Autres infos (optionnelles)
        $ville          = clean_str($in['ville'] ?? '');
        $pays           = clean_str($in['pays'] ?? '');
        $province_etat  = clean_str($in['province_etat'] ?? '');
        $adress         = clean_str($in['adress'] ?? '');
        $tel1           = clean_str($in['telephone1'] ?? '');
        $tel2           = clean_str($in['telephone2'] ?? '');

        // Compte admin de l'école
        $admin_email          = clean_str($in['admin_email'] ?? '');
        $admin_username_input = clean_str($in['admin_username'] ?? '');
        $admin_password_plain = (string)($in['admin_password'] ?? '');
        $admin_confirm        = (string)($in['admin_password_confirm'] ?? '');

        if ($admin_email === '' || $admin_password_plain === '') {
            throw new RuntimeException("Bloc #".($idx+1).": email & mot de passe admin requis.");
        }
        if ($admin_confirm !== $admin_password_plain) {
            throw new RuntimeException("Bloc #".($idx+1).": confirmation du mot de passe admin invalide.");
        }
        $hash = password_hash($admin_password_plain, PASSWORD_BCRYPT);

        // Fichiers
        $logo_file = save_upload("logo_{$idx}", "logo_{$code_ecole}", $uploadBase);
        $docs_file = save_upload("docs_{$idx}", "docs_{$code_ecole}", $uploadBase);
        $pj_file   = save_upload("piece_jointe_{$idx}", "pj_{$code_ecole}",   $uploadBase);

        // Email de contact = promoteur
        $email_contact = $promoteur_email ?: null;

        // Insert ECOLE
        $sql = "
            INSERT INTO ecoles
                (nom_ecole, code_ecole, url_ecole, ville, pays, province_etat,
                 logo, docs, adress, nom_responsable, postnom_responsable,
                 type_piece, piece_jointe, telephone1, telephone2,
                 email, username, PASSWORD, statut, id_promoteur)
            VALUES
                (:nom_ecole, :code_ecole, :url_ecole, :ville, :pays, :province_etat,
                 :logo, :docs, :adress, :nom_responsable, :postnom_responsable,
                 :type_piece, :piece_jointe, :telephone1, :telephone2,
                 :email, :username, :password, :statut, :id_promoteur)
        ";
        $ecole_username = ($admin_username_input !== '' ? $admin_username_input : $admin_email);
        $st = $pdo->prepare($sql);
        $st->execute([
            ':nom_ecole'          => $nom_ecole,
            ':code_ecole'         => $code_ecole,
            ':url_ecole'          => $url_ecole,
            ':ville'              => $ville ?: null,
            ':pays'               => $pays ?: null,
            ':province_etat'      => $province_etat ?: null,
            ':logo'               => $logo_file ?: null,
            ':docs'               => $docs_file ?: null,
            ':adress'             => $adress ?: null,
            ':nom_responsable'    => clean_str($in['nom_responsable'] ?? '') ?: null,
            ':postnom_responsable'=> clean_str($in['postnom_responsable'] ?? '') ?: null,
            ':type_piece'         => clean_str($in['type_piece'] ?? '') ?: null,
            ':piece_jointe'       => $pj_file ?: null,
            ':telephone1'         => $tel1 ?: null,
            ':telephone2'         => $tel2 ?: null,
            ':email'              => $email_contact,
            ':username'           => $ecole_username,
            ':password'           => $hash,
            ':statut'             => 'en attente',
            ':id_promoteur'       => $PROMOTEUR_ID,
        ]);

        // Landing page (upsert)
        $bioDefault = "Bienvenue sur {$nom_ecole} !";
        $sqlLP = "
            INSERT INTO landingpage (url_ecole, bio, nom_ecole, code_ecole)
            VALUES (:url_ecole, :bio, :nom_ecole, :code_ecole)
            ON DUPLICATE KEY UPDATE
                bio = VALUES(bio),
                nom_ecole = VALUES(nom_ecole),
                code_ecole = VALUES(code_ecole)
        ";
        $stLP = $pdo->prepare($sqlLP);
        $stLP->execute([
            ':url_ecole'  => $url_ecole,
            ':bio'        => $bioDefault,
            ':nom_ecole'  => $nom_ecole,
            ':code_ecole' => $code_ecole,
        ]);

        // Admin user (upsert par email)
        // username unique
        $admin_username = $ecole_username;
        $chkUser = $pdo->prepare("SELECT id FROM users WHERE username=:u LIMIT 1");
        $suffix = 1; $base = $admin_username;
        while (true) {
            $chkUser->execute([':u' => $admin_username]);
            if (!$chkUser->fetchColumn()) break;
            $admin_username = $base . $suffix;
            $suffix++;
            if ($suffix > 50) { throw new RuntimeException("Impossible de générer un username admin unique."); }
        }
        $sqlUser = "
            INSERT INTO users
                (username, PASSWORD, email, role, first_name, last_name, phone, numero_bancaire, code_ecole)
            VALUES
                (:username, :pwd, :email, 'admin', :fn, :ln, :phone, :nb, :code_ecole)
            ON DUPLICATE KEY UPDATE
                PASSWORD   = VALUES(PASSWORD),
                role       = 'admin',
                code_ecole = VALUES(code_ecole),
                updated_at = CURRENT_TIMESTAMP
        ";
        $stUser = $pdo->prepare($sqlUser);
        $stUser->execute([
            ':username'   => $admin_username,
            ':pwd'        => $hash,
            ':email'      => $admin_email,
            ':fn'         => clean_str($in['nom_responsable'] ?? '') ?: null,
            ':ln'         => clean_str($in['postnom_responsable'] ?? '') ?: null,
            ':phone'      => '',
            ':nb'         => '',
            ':code_ecole' => $code_ecole,
        ]);

        // Lier code_ecole sur le promoteur (non bloquant)
        try {
            $updPromo = $pdo->prepare("UPDATE users SET code_ecole = :ce, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND role = 'promoteur'");
            $updPromo->execute([':ce' => $code_ecole, ':id' => $PROMOTEUR_ID]);
        } catch(Throwable $e){}

        $pdo->commit();

        // Emails
        $loginUrl = rtrim(dirname(dirname(__DIR__)), '/').'/login/';

        $mailSubjectAdmin = "Accès administrateur - {$nom_ecole}";
        $mailBodyAdmin =
"Bonjour,

Votre école a été créée dans MyKelasi.

École : {$nom_ecole}
Code école : {$code_ecole}
URL école : {$url_ecole}

Identifiants administrateur :
- Nom d'utilisateur : {$admin_username}
- E-mail : {$admin_email}
- Mot de passe : {$admin_password_plain}

Page de connexion : {$loginUrl}

Nous vous recommandons de changer ce mot de passe après la première connexion.

-- 
MyKelasi";
        @send_mail_simple($admin_email, $mailSubjectAdmin, $mailBodyAdmin);

        if ($promoteur_email) {
            $salut = $promoteur_nom ? "Bonjour {$promoteur_nom}," : "Bonjour,";
            $mailSubjectPromo = "Nouvelle école créée - {$nom_ecole}";
            $mailBodyPromo =
"{$salut}

Vous venez de créer une école dans MyKelasi.

École : {$nom_ecole}
Code école : {$code_ecole}
URL école : {$url_ecole}

Informations de connexion administrateur (copie) :
- Nom d'utilisateur : {$admin_username}
- E-mail : {$admin_email}
- Mot de passe : {$admin_password_plain}

Page de connexion : {$loginUrl}

-- 
MyKelasi";
            @send_mail_simple($promoteur_email, $mailSubjectPromo, $mailBodyPromo);
        }

        $ok[] = $code_ecole;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $fail[] = "Bloc #".($idx+1)." — ".$e->getMessage();
        continue;
    }
}

// Flash + redirect
if ($ok) {
    $_SESSION['flash_success'] = count($ok) . " école(s) créée(s) avec succès : " . implode(', ', $ok);
}
if ($fail) {
    $_SESSION['flash_error'] = "Certaines écoles n'ont pas été enregistrées :\n- " . implode("\n- ", $fail);
}
header('Location: ../mykelasi-school.php');
