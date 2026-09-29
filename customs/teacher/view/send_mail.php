<?php
// customs/teacher/view/send_mail.php
declare(strict_types=1);

/**
 * CONFIG
 * - MK_MAIL_MODE: 'mail' (utilise mail()) ou 'file' (écrit seulement .eml)
 * - MK_MAIL_FROM: adresse expediteur (utilisez un domaine autorisé par votre hébergeur)
 * - MK_MAIL_FROM_NAME: nom expediteur
 * - MK_MAIL_OUTBOX: dossier où stocker les .eml
 * - MK_MAIL_SAVE_FAILED_ONLY: si false, sauvegarde .eml même quand l'envoi réussit
 */
if (!defined('MK_MAIL_MODE')) define('MK_MAIL_MODE', 'mail'); // 'mail' | 'file'
if (!defined('MK_MAIL_FROM')) define('MK_MAIL_FROM', 'no-reply@mykelasi.local');
if (!defined('MK_MAIL_FROM_NAME')) define('MK_MAIL_FROM_NAME', 'MyKelasi');
if (!defined('MK_MAIL_OUTBOX')) define('MK_MAIL_OUTBOX', __DIR__ . '/_mail_outbox');
if (!defined('MK_MAIL_SAVE_FAILED_ONLY')) define('MK_MAIL_SAVE_FAILED_ONLY', true);

/**
 * Envoi simple + fallback .eml
 * @param string      $to      destinataire
 * @param string      $subject sujet UTF-8
 * @param string      $body    texte brut UTF-8
 * @param string|null $replyTo adresse reply-to
 * @param string|null $err     message d'erreur (retour)
 * @return bool       true si mail() a accepté l’envoi, false sinon
 */
function send_mail_smart(string $to, string $subject, string $body, ?string $replyTo = null, ?string &$err = null): bool
{
    $err = null;
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = "Destinataire invalide: {$to}";
        // Même si invalide, on dump quand même un .eml pour debug
        mk_mail_dump_eml($to, $subject, $body, mk_mail_headers($replyTo));
        return false;
    }

    $headersStr = mk_mail_headers($replyTo);

    $sent = true;
    if (MK_MAIL_MODE === 'mail') {
        // Encodage MIME du sujet
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $sent = @mail($to, $encodedSubject, $body, $headersStr);
        if (!$sent) {
            $err = "mail() a échoué (serveur mail indisponible ou refusé).";
        }
    } else {
        // Mode fichier uniquement
        $sent = false;
    }

    // Sauvegarde .eml (si échec, ou toujours si SAVE_FAILED_ONLY=false)
    if (!is_dir(MK_MAIL_OUTBOX)) {
        @mkdir(MK_MAIL_OUTBOX, 0775, true);
    }
    if (!$sent || (defined('MK_MAIL_SAVE_FAILED_ONLY') && MK_MAIL_SAVE_FAILED_ONLY === false)) {
        mk_mail_dump_eml($to, $subject, $body, $headersStr);
    }

    return $sent;
}

/** Fabrique les headers texte/UTF-8 + From + Reply-To */
function mk_mail_headers(?string $replyTo = null): string
{
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $from = sprintf('%s <%s>', MK_MAIL_FROM_NAME, MK_MAIL_FROM);
    $headers[] = "From: {$from}";
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: {$replyTo}";
    }
    $headers[] = "X-Mailer: PHP/" . phpversion();
    return implode("\r\n", $headers);
}

/** Écrit un fichier .eml dans MK_MAIL_OUTBOX */
function mk_mail_dump_eml(string $to, string $subject, string $body, string $headersStr): void
{
    if (!is_dir(MK_MAIL_OUTBOX)) {
        @mkdir(MK_MAIL_OUTBOX, 0775, true);
    }
    $fname = MK_MAIL_OUTBOX . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.eml';
    $eml  = "To: {$to}\r\n";
    $eml .= "Subject: " . '=?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $eml .= $headersStr . "\r\n\r\n";
    $eml .= $body;
    @file_put_contents($fname, $eml);
}
