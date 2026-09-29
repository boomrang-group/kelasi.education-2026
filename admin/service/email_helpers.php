<?php
// service/email_helpers.php — petits helpers d'envoi d'email HTML

function send_mail(string $to, string $subject, string $html, string $from = 'Kelasi <no-reply@kelasi.education>'): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from}\r\n";
    // Encodage UTF-8 du sujet
    $subj = '=?UTF-8?B?'.base64_encode($subject).'?=';
    return @mail($to, $subj, $html, $headers);
}

/**
 * Envoie les identifiants au professeur et en copie à l’admin.
 * @param string $teacherEmail
 * @param string $teacherFullName
 * @param string $username
 * @param string $plainPassword
 * @param string $ecoleName
 * @param string $codeEcole
 * @param string|null $adminEmail
 * @param string $loginLink  URL du portail prof (adapter si besoin)
 */
function email_teacher_credentials(
    string $teacherEmail,
    string $teacherFullName,
    string $username,
    string $plainPassword,
    string $ecoleName,
    string $codeEcole,
    ?string $adminEmail,
    string $loginLink = 'https://kelasi.education/login/'
): void {

    $tName = htmlspecialchars($teacherFullName, ENT_QUOTES, 'UTF-8');
    $u     = htmlspecialchars($username,       ENT_QUOTES, 'UTF-8');
    $p     = htmlspecialchars($plainPassword,  ENT_QUOTES, 'UTF-8');
    $ec    = htmlspecialchars($ecoleName,      ENT_QUOTES, 'UTF-8');
    $code  = htmlspecialchars($codeEcole,      ENT_QUOTES, 'UTF-8');
    $link  = htmlspecialchars($loginLink,      ENT_QUOTES, 'UTF-8');

    $html_teacher = "
      <div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;'>
        <p>Bonjour {$tName},</p>
        <p>Votre compte enseignant pour l’école <strong>{$ec}</strong> (code: <strong>{$code}</strong>) a été créé sur <strong>Kelasi</strong>.</p>
        <p>Accédez au portail : <a href='{$link}' target='_blank'>{$link}</a></p>
        <p><u>Identifiants</u> :</p>
        <ul>
          <li>Login : <strong>{$u}</strong></li>
          <li>Mot de passe : <strong>{$p}</strong></li>
        </ul>
        <p>Merci de changer votre mot de passe après la première connexion.</p>
        <p>Cordialement,<br>Kelasi</p>
      </div>
    ";
    @send_mail($teacherEmail, "Vos accès Enseignant - {$ec}", $html_teacher);

    if ($adminEmail) {
        $html_admin = "
          <div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;'>
            <p>Bonjour,</p>
            <p>Un nouvel enseignant a été créé pour l’école <strong>{$ec}</strong> (code: <strong>{$code}</strong>).</p>
            <p><u>Accès envoyés à l’enseignant</u> :</p>
            <ul>
              <li>Login : <strong>{$u}</strong></li>
              <li>Mot de passe : <strong>{$p}</strong></li>
            </ul>
            <p>Portail : <a href='{$link}' target='_blank'>{$link}</a></p>
            <p>Cordialement,<br>Kelasi</p>
          </div>
        ";
        @send_mail($adminEmail, "Nouvel enseignant créé - {$ec}", $html_admin);
    }
}
