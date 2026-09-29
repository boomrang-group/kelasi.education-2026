<?php
// email.php
declare(strict_types=1);

// Configurations globales d'expédition
$MAIL_FROM      = 'no-reply@kelasi.education';
$MAIL_FROM_NAME = 'Kelasi';

if (!function_exists('kelasi_login_url')) {
    function kelasi_login_url(): string {
        return 'https://kelasi.education/login/';
    }
}

/**
 * Moteur d'envoi générique utilisant exactement le même formatage d'en-têtes 
 * et d'encodage de sujet.
 */
if (!function_exists('mail_html')) {
    function mail_html(string $to, string $subject, string $html): bool {
        global $MAIL_FROM, $MAIL_FROM_NAME;

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $from     = $MAIL_FROM ?? 'no-reply@kelasi.education';
        $fromName = $MAIL_FROM_NAME ?? 'Kelasi';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $fromName . " <" . $from . ">\r\n";

        // Encodage UTF-8/Base64 du sujet pour éviter le rejet par les serveurs
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        return @mail($to, $subjectEncoded, $html, $headers);
    }
}

/**
 * Envoie les identifiants à l'élève ET/OU au responsable
 * $recipients peut être une chaîne "email@domain.com" ou un tableau d'emails
 */
if (!function_exists('send_student_credentials')) {
    function send_student_credentials($recipients, array $data): bool {
        // Normalisation de la liste des destinataires
        $emails = [];

        if (is_string($recipients)) {
            $emails[] = trim($recipients);
        } elseif (is_array($recipients)) {
            $emails = $recipients;
        }

        // Si le tableau $data contient aussi des e-mails explicitement
        if (!empty($data['email'])) {
            $emails[] = trim($data['email']);
        }
        if (!empty($data['email_responsable'])) {
            $emails[] = trim($data['email_responsable']);
        }

        // Filtrer les emails invalides et supprimer les doublons
        $validEmails = array_unique(array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }));

        if (empty($validEmails)) {
            return false;
        }

        $subject  = "Vos accès Kelasi — Compte élève";
        $loginUrl = $data['login_url'] ?? kelasi_login_url();

        $first = htmlspecialchars($data['first'] ?? '', ENT_QUOTES, 'UTF-8');
        $last  = htmlspecialchars($data['last'] ?? '', ENT_QUOTES, 'UTF-8');
        $user  = htmlspecialchars($data['username'] ?? '', ENT_QUOTES, 'UTF-8');
        $pass  = htmlspecialchars($data['password'] ?? '', ENT_QUOTES, 'UTF-8');
        $ecole = htmlspecialchars($data['ecole_name'] ?? '', ENT_QUOTES, 'UTF-8');

        $html = '
        <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;">
            <h2 style="color:#0d6efd;margin-top:0;">Bienvenue ' . $first . ' ' . $last . '</h2>
            <p>Votre compte élève a été créé avec succès pour l\'établissement <strong>' . $ecole . '</strong>.</p>
            
            <div style="background-color:#f8f9fa;padding:15px;border-left:4px solid #0d6efd;margin:20px 0;">
                <p style="margin:0 0 8px 0;"><strong>Identifiants de connexion :</strong></p>
                <p style="margin:4px 0;"><strong>Nom d\'utilisateur :</strong> ' . $user . '</p>
                <p style="margin:4px 0;"><strong>Mot de passe :</strong> ' . $pass . '</p>
            </div>

            <p>
                <a href="' . htmlspecialchars($loginUrl) . '" 
                   style="display:inline-block;padding:12px 20px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">
                   Accéder à mon espace
                </a>
            </p>
            <p style="font-size:13px;color:#666;">Pour des raisons de sécurité, nous vous conseillons de changer ce mot de passe après votre première connexion.</p>
            <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
            <p style="font-size:12px;color:#888;">Ceci est un e-mail automatique envoyé par MyKelasi, merci de ne pas y répondre directement.</p>
        </div>';

        $atLeastOneSent = false;
        foreach ($validEmails as $recipient) {
            if (mail_html($recipient, $subject, $html)) {
                $atLeastOneSent = true;
            }
        }

        return $atLeastOneSent;
    }
}

/**
 * Notifie les administrateurs/promoteurs de l'école lors d'une nouvelle inscription
 */
if (!function_exists('notify_admins_new_student')) {
    function notify_admins_new_student(PDO $pdo, string $code_ecole, array $data): int {
        $subject = "Notification : Nouvelle inscription élève (" . ($data['ecole_name'] ?? '') . ")";

        $first      = htmlspecialchars($data['first'] ?? '', ENT_QUOTES, 'UTF-8');
        $last       = htmlspecialchars($data['last'] ?? '', ENT_QUOTES, 'UTF-8');
        $email      = htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $phone      = htmlspecialchars($data['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $ecole_name = htmlspecialchars($data['ecole_name'] ?? '', ENT_QUOTES, 'UTF-8');

        $html = '
        <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;padding:15px;">
            <h3 style="color:#0d6efd;">Nouvelle inscription enregistrée</h3>
            <p>Un nouvel élève a été ajouté à votre établissement <strong>' . $ecole_name . '</strong>.</p>
            <ul>
                <li><strong>Nom complet :</strong> ' . $first . ' ' . $last . '</li>
                <li><strong>Email élève :</strong> ' . ($email !== '' ? $email : '<i>Non renseigné</i>') . '</li>
                <li><strong>Téléphone :</strong> ' . ($phone !== '' ? $phone : '<i>Non renseigné</i>') . '</li>
            </ul>
        </div>';

        // Recherche des administrateurs et promoteurs de cette école
        $stmt = $pdo->prepare("
            SELECT email 
            FROM users 
            WHERE code_ecole = :code_ecole 
            AND role IN ('admin', 'administrateur', 'promoteur')
        ");
        $stmt->execute([':code_ecole' => $code_ecole]);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($admins as $admin) {
            $adminEmail = trim($admin['email'] ?? '');
            if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                if (mail_html($adminEmail, $subject, $html)) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}