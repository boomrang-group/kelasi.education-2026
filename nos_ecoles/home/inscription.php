<?php
// inscription_eleve.php — Formulaire + traitement (students + users) + emails
declare(strict_types=1);

require __DIR__ . '/paserelle_url.php';             // init session + $url_session_ecole
require __DIR__ . '/../../database/db_connect.php'; // $pdo
require __DIR__ . '/email.php';                      // fonctions d'email

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }

// ───────────── Config uploads ─────────────
$UPLOAD_BASE = __DIR__ . '/uploads/students';
$MAX_PHOTO_MB = 3;   // 3 Mo
$MAX_DOC_MB   = 10;  // 10 Mo
$ALLOWED_IMG  = ['image/jpeg','image/png','image/webp'];
$ALLOWED_DOC  = ['application/pdf','image/jpeg','image/png','image/webp'];

// ───────────── CSRF ─────────────
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF_TOKEN = $_SESSION['csrf'];

// ───────────── Helpers ─────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function old(string $k, $d=''){ return $_POST[$k] ?? $d; }
function is_email(string $s): bool { return (bool)filter_var($s, FILTER_VALIDATE_EMAIL); }
function is_phone(?string $s): bool {
    if ($s===null || $s==='') return true;
    return (bool)preg_match('/^[0-9+\s().-]{6,20}$/', $s);
}
function mkdir_safe(string $dir): void { if (!is_dir($dir)) { @mkdir($dir, 0775, true); } }

// translittération simple (accents → ascii)
function ascii(string $s): string {
    $t = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    return $t !== false ? $t : $s;
}
function first_letter(string $s): string {
    $s = trim($s);
    if ($s==='') return '';
    $s = ascii($s);
    preg_match('/[A-Za-z]/', $s, $m);
    if ($m) return substr(strtoupper($m[0]),0,1);
    return '';
}
function slugify(string $s): string {
    $s = strtolower(ascii($s));
    $s = preg_replace('/[^a-z0-9]+/','-',$s);
    $s = trim($s,'-');
    return $s ?: 'eleve';
}

// username = initiale école + '-' + last_name (slug)
// unique en DB (users.username)
function unique_username_by_base(PDO $pdo, string $base): string {
    // Essaye base, puis base-2, base-3, ...
    $u = $base;
    $i = 2;
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=:u");
    while (true) {
        $st->execute([':u'=>$u]);
        if ((int)$st->fetchColumn() === 0) return $u;
        $u = $base.'-'.$i;
        $i++;
        if ($i>9999) return $base.'-'.time(); // dernier recours
    }
}

/**
 * Génère un mot de passe temporaire fort et aléatoire (V11).
 * On n'utilise plus la logique prévisible basée sur le nom et la date de naissance.
 */
function build_password(): string {
    return bin2hex(random_bytes(4)); // ex: 8f2a1c9e
}

// ───────────── Contexte école ─────────────
$code_ecole   = $_SESSION['code_ecole'] ?? null;
$handle_ecole = $url_session_ecole ?? ($_SESSION['url_ecole'] ?? null);
$nom_ecole    = $_SESSION['nom_ecole'] ?? ($_SESSION['landingpage']['nom_ecole'] ?? 'Votre établissement');

// ───────────── Traitement POST ─────────────
$alert = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        // CSRF
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException("Session expirée. Veuillez recharger la page.");
        }

        // Champs
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name  = trim((string)($_POST['last_name'] ?? ''));
        $gender = strtolower(trim((string)($_POST['gender'] ?? '')));
        $date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $ecole_provenance = trim((string)($_POST['ecole_provencance'] ?? $_POST['ecole_provenance'] ?? ''));
        $father     = trim((string)($_POST['father'] ?? ''));
        $mother     = trim((string)($_POST['mother'] ?? ''));
        $phone_resp = trim((string)($_POST['phone_responsable'] ?? ''));
        $email_resp = trim((string)($_POST['email_responsable'] ?? ''));
        $class_id   = isset($_POST['classe']) && $_POST['classe'] !== '' ? (int)$_POST['classe'] : null;
        $accepted   = isset($_POST['accept']) && $_POST['accept']=='1';

        // Validations
        if ($first_name==='' || $last_name==='') throw new RuntimeException("Prénoms/Noms requis.");
        if (!in_array($gender, ['homme', 'femme'], true)) {throw new RuntimeException("Genre invalide.");}
        if ($date_of_birth==='') throw new RuntimeException("Date de naissance requise.");
        if ($email!=='' && !is_email($email)) throw new RuntimeException("Email élève invalide.");
        if (!is_phone($phone)) throw new RuntimeException("Téléphone élève invalide.");
        if (!is_phone($phone_resp)) throw new RuntimeException("Téléphone responsable invalide.");
        if (!is_email($email_resp)) throw new RuntimeException("Email responsable invalide.");
        if (!$accepted) throw new RuntimeException("Veuillez accepter la charte/conditions.");
        if (!$code_ecole) throw new RuntimeException("Code école manquant en session.");

        // Duplication email élève
        if ($email!=='') {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email=:e AND code_ecole=:ec");
            $chk->execute([':e'=>$email, ':ec'=>$code_ecole]);
            if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Cet email élève est déjà utilisé (students).");

            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=:e");
            $chk2->execute([':e'=>$email]);
            if ((int)$chk2->fetchColumn() > 0) throw new RuntimeException("Cet email est déjà utilisé (users).");
        }

        // USERNAME = initiale école + '-' + last_name (slug)
        $ecole_initial = first_letter($nom_ecole);
        if ($ecole_initial==='') {
            $ecole_initial = first_letter($code_ecole ?? '');
            if ($ecole_initial==='') $ecole_initial = first_letter($handle_ecole ?? '');
            if ($ecole_initial==='') $ecole_initial = 'E';
        }
        $username_base = strtolower($ecole_initial).'-'.slugify($last_name);
        $username      = unique_username_by_base($pdo, $username_base);

        // PASSWORD aléatoire (V11)
        $plainPassword = build_password();
        $passwordHash  = password_hash($plainPassword, PASSWORD_BCRYPT);

        // Vérif fichiers (on enregistrera après inserts DB)
        if (!empty($_FILES['photo']['name'])) {
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Erreur upload photo.");
            if ((int)$_FILES['photo']['size'] > $MAX_PHOTO_MB*1024*1024) throw new RuntimeException("Photo trop volumineuse (max {$MAX_PHOTO_MB} Mo).");
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            if (!in_array($mime, $ALLOWED_IMG, true)) throw new RuntimeException("Format photo invalide.");
        }
        if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $i => $name) {
                if ($name==='') continue;
                if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) throw new RuntimeException("Erreur upload document ($name).");
                if ((int)$_FILES['documents']['size'][$i] > $MAX_DOC_MB*1024*1024) throw new RuntimeException("Document trop volumineux ($name).");
                $mime = mime_content_type($_FILES['documents']['tmp_name'][$i]);
                if (!in_array($mime, $ALLOWED_DOC, true)) throw new RuntimeException("Format document invalide ($name).");
            }
        }

        // ───────── Transaction : insert students + insert users ─────────
        $pdo->beginTransaction();

        // 1) STUDENTS
        $sqlS = "INSERT INTO students
                 (first_name,last_name,username,gender,date_of_birth,email,phone,class_id,PASSWORD,father,mother,phone_responsable,email_responsable,created_at,code_ecole,ecole_provenance,statut)
                 VALUES
                 (:fn,:ln,:un,:g,:dob,:em,:ph,:cid,:pwd,:fa,:mo,:ph_r,:em_r,NOW(),:ec,:prov,'invalide')";
        $stS = $pdo->prepare($sqlS);
        $stS->execute([
            ':fn'=>$first_name, ':ln'=>$last_name, ':un'=>$username, ':g'=>$gender,
            ':dob'=>$date_of_birth, ':em'=>$email!==''?$email:null, ':ph'=>$phone!==''?$phone:null,
            ':cid'=>$class_id!==null?$class_id:null, ':pwd'=>$passwordHash,
            ':fa'=>$father, ':mo'=>$mother, ':ph_r'=>$phone_resp, ':em_r'=>$email_resp,
            ':ec'=>$code_ecole, ':prov'=>$ecole_provenance ?: $handle_ecole
        ]);
        $studentId = (int)$pdo->lastInsertId();

        // Email pour USERS (placeholder si vide)
        $userEmail = $email !== '' ? $email : ('student'.$studentId.'@noemail.local');

        // 2) USERS (role élève)
        $sqlU = "INSERT INTO users
                 (username, PASSWORD, email, role, first_name, last_name, phone, numero_bancaire, code_ecole, created_at, updated_at)
                 VALUES
                 (:un, :pwd, :em, 'eleve', :fn, :ln, :ph, :nb, :ec, NOW(), NOW())";
        $stU = $pdo->prepare($sqlU);
        $stU->execute([
            ':un'=>$username,
            ':pwd'=>$passwordHash,
            ':em'=>$userEmail,
            ':fn'=>$first_name,
            ':ln'=>$last_name,
            ':ph'=>$phone!==''?$phone:'',
            ':nb'=>'',
            ':ec'=>$code_ecole
        ]);
        $userId = (int)$pdo->lastInsertId();

        // ───────── Uploads (après avoir un ID) ─────────
        $photoPath = null; $docsPaths = [];
        if ($studentId) {
            $studentDir = $UPLOAD_BASE . '/' . $studentId;
            mkdir_safe($studentDir);

            if (!empty($_FILES['photo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg');
                $target = $studentDir . '/photo.' . $ext;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    throw new RuntimeException("Impossible d'enregistrer la photo.");
                }
                $photoPath = 'uploads/students/'.$studentId.'/photo.'.$ext;
            }

            if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
                foreach ($_FILES['documents']['name'] as $i => $name) {
                    if ($name==='') continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'dat');
                    $tmp = $_FILES['documents']['tmp_name'][$i];
                    $safeName = 'doc_' . ($i+1) . '.' . $ext;
                    $target   = $studentDir . '/' . $safeName;
                    if (move_uploaded_file($tmp, $target)) {
                        $docsPaths[] = 'uploads/students/'.$studentId.'/'.$safeName;
                    }
                }
            }
            // (Option) si colonnes photo_url/docs_json existent dans students :
            // $up = $pdo->prepare("UPDATE students SET photo_url=:p, docs_json=:d WHERE id=:id");
            // $up->execute([':p'=>$photoPath, ':d'=>json_encode($docsPaths, JSON_UNESCAPED_SLASHES), ':id'=>$studentId]);
        }

        $pdo->commit();

        // ───────── Emails (après commit) ─────────
        $loginUrl = kelasi_login_url();

        if ($email !== '') {
            @send_student_credentials($email, [
                'first'=>$first_name, 'last'=>$last_name,
                'username'=>$username, 'password'=>$plainPassword,
                'code_ecole'=>$code_ecole, 'ecole_name'=>$nom_ecole,
                'login_url'=>$loginUrl
            ]);
        }

        @notify_admins_new_student($pdo, $code_ecole, [
            'first'=>$first_name, 'last'=>$last_name,
            'email'=>$email, 'phone'=>$phone,
            'father'=>$father, 'mother'=>$mother,
            'email_resp'=>$email_resp, 'phone_resp'=>$phone_resp,
            'class_id'=>$class_id,
            'username'=>$username, 'password'=>$plainPassword,
            'ecole_name'=>$nom_ecole, 'code_ecole'=>$code_ecole
        ]);

        $ok = true;
        $alert = '<div class="alert alert-success">✅ Inscription enregistrée. Identifiant: <b>'.e($username).'</b> — Mot de passe: <b>'.e($plainPassword).'</b>'
               . ($email!=='' ? ' — un e-mail a été envoyé à l’élève.' : ' — aucun e-mail élève (adresse manquante).')
               . ' Les administrateurs ont été notifiés.</div>';

        $_POST = []; // reset form

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alert = '<div class="alert alert-danger">❌ '.$e->getMessage().'</div>';
    }
}

// ───────────── Classes (pour le sélecteur) ─────────────
$classes = [];
try {
    if (!empty($code_ecole)) {
        $sqlC = "SELECT c.id AS identity, c.description AS description, c.classe AS classe,
                        n.description AS niveau, s.description AS section, o.description AS options
                 FROM classes c
                 LEFT JOIN section s ON c.section=s.id
                 LEFT JOIN niveau  n ON c.niveau=n.id
                 LEFT JOIN options o ON c.options=o.id
                 WHERE c.code_ecole = :ec
                 ORDER BY n.description, c.classe, s.description, o.description";
        $stmt = $pdo->prepare($sqlC); $stmt->execute([':ec'=>$code_ecole]);
    } else {
        $stmt = $pdo->query("SELECT
                    classes.id AS identity, classes.description AS description, classes.classe AS classe,
                    niveau.description AS niveau, section.description AS section, options.description AS options
                FROM classes
                LEFT JOIN section ON classes.section=section.id
                LEFT JOIN niveau  ON classes.niveau=niveau.id
                LEFT JOIN options ON classes.options=options.id");
    }
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Kelasi - Inscription élève</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon & Fonts & CSS -->
    <link href="img/favicon.ico" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@600&family=Lobster+Two:wght@700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <style>
    .preview {
        max-width: 150px;
        max-height: 150px;
        border-radius: 8px;
        display: block;
        margin-top: 6px;
    }
    </style>
</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg bg-white navbar-light sticky-top px-4 px-lg-5 py-lg-0">
            <a href="." class="navbar-brand">
                <img src="../../img/logo.png" alt="" srcset="">
            </a>
            <button type="button" class="navbar-toggler" data-bs-toggle="collapse"
                data-bs-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav mx-auto"></div>
                <?php if (!empty($handle_ecole)): ?>
                <a href=".?ecole=<?= e($handle_ecole) ?>"
                    class="btn btn-primary rounded-pill px-3 d-none d-lg-block">Accueil<i
                        class="fa fa-arrow-right ms-3"></i></a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="container mb-4 mt-4">
            <h1 class="text-uppercase">Inscription</h1>

            <?= $alert ?>

            <div class="card height-auto">
                <div class="card-body">
                    <form class="new-added-form" action="" method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf" value="<?= e($CSRF_TOKEN) ?>">
                        <div class="row">
                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Prénom *</label>
                                <input type="text" name="first_name" class="form-control" required maxlength="100"
                                    value="<?= e(old('first_name')) ?>">
                            </div>
                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Nom de famille *</label>
                                <input type="text" name="last_name" class="form-control" required maxlength="100"
                                    value="<?= e(old('last_name')) ?>">
                            </div>
                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Genre *</label>
                                <select class="select2 form-control" name="gender" required>
                                    <option value="" disabled <?= old('gender')===''?'selected':''; ?>>Sélectionner
                                        votre genre *</option>
                                    <option value="Homme" <?= old('gender')==='Homme'?'selected':''; ?>>Homme</option>
                                    <option value="Femme" <?= old('gender')==='Femme'?'selected':''; ?>>Femme</option>
                                </select>
                            </div>

                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Date de naissance *</label>
                                <input type="date" class="form-control" name="date_of_birth" required
                                    value="<?= e(old('date_of_birth')) ?>">
                            </div>
                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Adresse e-mail</label>
                                <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>"
                                    maxlength="150">
                            </div>
                            <div class="col-xl-4 mb-3 col-lg-6 col-12 form-group">
                                <label>Téléphone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= e(old('phone')) ?>"
                                    maxlength="20" pattern="[0-9+\s().-]{6,20}">
                            </div>

                            <div class="col-lg-6 col-12 form-group mg-t-30">
                                <label class="text-dark-medium">Télécharger la photo de l'élève (150 px x 150 px) —
                                    JPG/PNG/WEBP (max.
                                    <?= $MAX_PHOTO_MB ?> Mo)</label>
                                <input type="file" name="photo" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,image/*" id="photoInput">
                                <img id="photoPreview" class="preview d-none" alt="Prévisualisation">
                            </div>
                            <div class="col-lg-6 col-12 form-group mg-t-30">
                                <label class="text-dark-medium">Téléverser les documents de l'étudiant — PDF/JPG/PNG
                                    (max
                                    <?= $MAX_DOC_MB ?> Mo chacun)</label>
                                <input type="file" name="documents[]" class="form-control"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" multiple>
                            </div>

                            <div class="col-xl-3 col-lg-6 col-12 form-group mt-3">
                                <label>Ecole provenance *</label>
                                <input type="text" name="ecole_provencance" class="form-control" required
                                    value="<?= e(old('ecole_provencance', $handle_ecole ?? '')) ?>">
                            </div>

                            <div class="col-lg-12 col-12 form-group mg-t-30 mt-3">
                                <h3 class="text-dark-medium" style="text-transform: uppercase;">Info du responsable</h3>
                                <hr>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-12 form-group">
                                <label>Nom du père *</label>
                                <input type="text" name="father" class="form-control" required maxlength="50"
                                    value="<?= e(old('father')) ?>">
                            </div>
                            <div class="col-xl-3 col-lg-6 col-12 form-group">
                                <label>Nom de la mère *</label>
                                <input type="text" name="mother" class="form-control" required maxlength="50"
                                    value="<?= e(old('mother')) ?>">
                            </div>
                            <div class="col-xl-3 col-lg-6 col-12 form-group">
                                <label>Téléphone *</label>
                                <input type="tel" name="phone_responsable" class="form-control" required maxlength="20"
                                    pattern="[0-9+\s().-]{6,20}" value="<?= e(old('phone_responsable')) ?>">
                            </div>
                            <div class="col-xl-3 col-lg-6 col-12 form-group">
                                <label>Email *</label>
                                <input type="email" name="email_responsable" class="form-control" required
                                    maxlength="150" value="<?= e(old('email_responsable')) ?>">
                            </div>

                            <div class="col-lg-12 col-12 mt-3 mg-t-30">
                                <h3 class="text-dark-medium" style="text-transform: uppercase;">Affectation</h3>
                                <hr>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-12">
                                <label>Classe</label>
                                <select class="select2 form-control" name="classe">
                                    <option value="">Please Select Classe *</option>
                                    <?php foreach($classes as $row): ?>
                                    <option value="<?= (int)$row['identity'] ?>"
                                        <?= old('classe')==(string)$row['identity']?'selected':''; ?>>
                                        <?= e($row['classe'].' '.$row['description'].' '.$row['niveau'].' '.$row['section'].' '.$row['options']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($classes)): ?>
                                <div class="small text-danger mt-1">Aucune classe trouvée (vérifiez code_ecole).</div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 form-group mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accept" id="accept" value="1"
                                        <?= old('accept')?'checked':''; ?> required>
                                    <label class="form-check-label" for="accept">J’atteste l’exactitude des informations
                                        et j’accepte la charte.</label>
                                </div>
                            </div>

                            <div class="col-12 form-group mg-t-8 mt-4">
                                <button type="submit" class="btn btn-success" name="submit">Soumettre</button>
                                <button type="reset" class="btn btn-danger">Annuler</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($ok): ?>
                    <div class="alert alert-info mt-3">
                        📌 Conseil : modifiez le mot de passe à la première connexion.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="container-fluid bg-dark text-white-50 footer pt-5 mt-5">
            <div class="container py-4 text-center">
                <small>&copy; Kelasi</small>
            </div>
        </div>

        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- JS libs -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Aperçu photo
    const input = document.getElementById('photoInput');
    const prev = document.getElementById('photoPreview');
    if (input) {
        input.addEventListener('change', () => {
            const f = input.files && input.files[0];
            if (!f) {
                prev.classList.add('d-none');
                return;
            }
            const url = URL.createObjectURL(f);
            prev.src = url;
            prev.classList.remove('d-none');
        });
    }
    </script>
</body>

</html>