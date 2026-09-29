<?php
declare(strict_types=1);

/**
 * URL de connexion
 */
function kelasi_login_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    $scheme = $https ? 'https://' : 'http://';

    $host = $_SERVER['HTTP_HOST'];

    return $scheme . $host . '/login/';
}

/**
 * Envoi des identifiants de connexion
 */
function send_student_credentials(string $to, array $data): bool
{
    if (empty($to)) {
        return false;
    }

    $first      = $data['first'] ?? '';
    $last       = $data['last'] ?? '';
    $username   = $data['username'] ?? '';
    $password   = $data['password'] ?? '';
    $codeEcole  = $data['code_ecole'] ?? '';
    $ecole      = $data['ecole_name'] ?? 'Kelasi';
    $loginUrl   = $data['login_url'] ?? kelasi_login_url();

    $subject = "Vos identifiants de connexion - {$ecole}";

    $message = "
Bonjour {$first} {$last},

Votre compte a été créé avec succès.

----------------------------------------
Établissement : {$ecole}
Code école    : {$codeEcole}

Nom d'utilisateur : {$username}
Mot de passe      : {$password}
----------------------------------------

Connexion :

{$loginUrl}

Nous vous conseillons de modifier votre mot de passe après votre première connexion.

Cordialement,

{$ecole}
";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: {$ecole} <no-reply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";

    return @mail($to, $subject, $message, $headers);
}