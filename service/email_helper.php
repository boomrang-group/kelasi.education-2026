<?php
// service/email_helper.php
// Template pour PHPMailer (V08)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Note: Vous devrez installer PHPMailer via composer ou inclure les fichiers manuellement
// require 'vendor/autoload.php';

function get_mailer() {
    $mail = new PHPMailer(true);

    try {
        // Paramètres du serveur (À REMPLACER PAR VOS PARAMÈTRES RÉELS DANS LE .env)
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.example.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: 'user@example.com';
        $mail->Password   = getenv('SMTP_PASS') ?: 'password';
        $mail->SMTPSecure = getenv('SMTP_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(getenv('MAIL_FROM') ?: 'no-reply@kelasi.education', getenv('MAIL_FROM_NAME') ?: 'Kelasi');

        return $mail;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Version sécurisée de mail_html utilisant PHPMailer
 */
function send_email_secure($to, $subject, $html) {
    $mail = get_mailer();
    if (!$mail) return false;

    try {
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed to $to: " . $mail->ErrorInfo);
        return false;
    }
}
