<?php
// register_process.php
session_start();
require_once '../database/db_connect.php'; // doit définir $pdo (PDO)

$errors = [];

// Utiles
function v($key){ return isset($_POST[$key]) ? trim((string)$_POST[$key]) : ''; }

// Config liens d’accès
$APP_ACCESS_URL = 'https://kelasi.education/mykelasi/my_school/'; // Lien d’accès principal
$APP_LOGIN_URL  = 'https://kelasi.education/mykelasi/my_school/'; // Si tu as une page login distincte, mets-la ici

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: new_school/');
  exit;
}

$first_name       = filter_var(v('first_name'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$last_name        = filter_var(v('last_name'),  FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$username         = filter_var(v('username'),   FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email            = filter_var(v('email'),      FILTER_SANITIZE_EMAIL);
$phone            = filter_var(v('phone'),      FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password         = (string)($_POST['password'] ?? '');
$confirm_password = (string)($_POST['confirm_password'] ?? '');
$numero_bancaire  = v('numero_bancaire');
$role             = 'promoteur'; // <-- rôle attendu ici

// Validation
if ($first_name === '')  $errors[] = "Le prénom est requis.";
if ($last_name === '')   $errors[] = "Le nom de famille est requis.";
if ($username === '')    $errors[] = "Le nom d'utilisateur est requis.";
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
if ($phone === '')       $errors[] = "Le téléphone est requis.";

if ($password === '')           $errors[] = "Le mot de passe est requis.";
if ($confirm_password === '')   $errors[] = "La confirmation du mot de passe est requise.";
if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas.";

// (Optionnel) Règles de robustesse de mot de passe
// if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";

// Unicité username / email
if (!$errors) {
  try {
    // username
    $st = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $st->execute([':u'=>$username]);
    if ($st->fetch()) $errors[] = "Ce nom d'utilisateur existe déjà.";

    // email
    $st = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
    $st->execute([':e'=>$email]);
    if ($st->fetch()) $errors[] = "Cet email existe déjà.";
  } catch (PDOException $e) {
    $errors[] = "Erreur base de données (vérification): ".$e->getMessage();
  }
}

// Si erreurs -> retour formulaire avec old inputs
if ($errors) {
  $_SESSION['reg_errors'] = $errors;
  $_SESSION['reg_old'] = [
    'first_name'=>$first_name,
    'last_name'=>$last_name,
    'username'=>$username,
    'email'=>$email,
    'phone'=>$phone,
    'numero_bancaire'=>$numero_bancaire,
  ];
  header('Location: ../new_school/');
  exit;
}

// Insertion
try {
  $hashed = password_hash($password, PASSWORD_DEFAULT);

  $sql = "INSERT INTO users
            (username, password, email, role, first_name, last_name, phone, numero_bancaire, code_ecole)
          VALUES
            (:username, :password, :email, :role, :first_name, :last_name, :phone, :numero_bancaire, NULL)";
  $st = $pdo->prepare($sql);
  $ok = $st->execute([
    ':username'        => $username,
    ':password'        => $hashed,
    ':email'           => $email,
    ':role'            => $role,
    ':first_name'      => $first_name,
    ':last_name'       => $last_name,
    ':phone'           => $phone,
    ':numero_bancaire' => $numero_bancaire ?: ''
  ]);

  if (!$ok) {
    throw new RuntimeException("Échec d'enregistrement utilisateur.");
  }

  // Envoi Email d’accueil (sans mot de passe pour la sécurité)
  $sent = sendWelcomeMail($email, $first_name, $username, $APP_ACCESS_URL, $APP_LOGIN_URL);

  // Message final
  $_SESSION['reg_success'] = $sent
    ? "Inscription réussie ! Un email vous a été envoyé avec vos accès et le lien pour vous connecter."
    : "Inscription réussie ! (⚠️ L’email n’a pas pu être envoyé). Vous pouvez accéder ici : $APP_ACCESS_URL";

  header('Location: ../my_school/?success=1');
  exit;

} catch (PDOException $e) {
  $_SESSION['reg_errors'] = ["Erreur lors de l'inscription : ".$e->getMessage()];
  $_SESSION['reg_old'] = [
    'first_name'=>$first_name,
    'last_name'=>$last_name,
    'username'=>$username,
    'email'=>$email,
    'phone'=>$phone,
    'numero_bancaire'=>$numero_bancaire,
  ];
  header('Location: ../new_school/');
  exit;
}

/**
 * Envoie un email HTML simple avec le lien d’accès et d’authentification.
 * Remplace par PHPMailer si tu as un SMTP (recommandé).
 */
function sendWelcomeMail(string $to, string $firstName, string $username, string $accessUrl, string $loginUrl): bool {
  // PARAMÈTRES expéditeur — à personnaliser
  $from     = 'no-reply@kelasi.education';
  $fromName = 'MyKelasi';

  $subject = 'Bienvenue sur MyKelasi — Vos accès promoteur';
  $html = '
  <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222">
    <h2>Bienvenue, '.htmlspecialchars($firstName).'</h2>
    <p>Votre compte promoteur a été créé avec succès.</p>
    <p><strong>Identifiant :</strong> '.htmlspecialchars($username).'<br>
       <em>Pour votre sécurité, votre mot de passe n’est pas communiqué par email.</em></p>
    <p>
      <a href="'.htmlspecialchars($loginUrl).'"
         style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px">
         Se connecter à MyKelasi
      </a>
    </p>
    <p>Accès direct à votre espace : <a href="'.htmlspecialchars($accessUrl).'">'.htmlspecialchars($accessUrl).'</a></p>
    <hr>
    <p style="font-size:12px;color:#666">Si vous n’êtes pas à l’origine de cette inscription, ignorez cet email.</p>
  </div>';

  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=UTF-8\r\n";
  $headers .= "From: ".$fromName." <".$from.">\r\n";
  // (Optionnel) Reply-To:
  // $headers .= "Reply-To: support@kelasi.education\r\n";

  // IMPORTANT : La fonction mail() nécessite une config serveur (sendmail/SMTP).
  // Pour un envoi fiable, passe à PHPMailer + SMTP.
  return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $headers);
}
