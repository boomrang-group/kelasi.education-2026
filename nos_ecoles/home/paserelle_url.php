<?php
// get_landingpage_session.php — charge toutes les infos depuis landingpage pour ?ecole=...
declare(strict_types=1);

// 1) Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2) DB
require __DIR__ . '/../../database/db_connect.php';
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur DB.');
}

try {
    // 3) Lire + valider le paramètre ?ecole=handle (ex: boomrang)
    if (!isset($_GET['ecole'])) {
        http_response_code(400);
        exit("Paramètre 'ecole' manquant.");
    }
    $handle = trim((string)$_GET['ecole']);
    if ($handle === '') {
        http_response_code(400);
        exit("Paramètre 'ecole' vide.");
    }
    // Adapte le pattern si besoin
    if (!preg_match('/^[a-z0-9._-]{2,100}$/i', $handle)) {
        header('Location: https://www.kelasi.education', true, 302);
        exit;
    }

    // 4) Récup depuis landingpage (id, url_ecole, bio, nom_ecole, code_ecole)
    $sql = "SELECT * FROM landingpage WHERE url_ecole = :u LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':u' => $handle]);
    $lp  = $st->fetch(PDO::FETCH_ASSOC);

    if (!$lp) {
        // Non trouvé → home publique
        header('Location: https://www.kelasi.education', true, 302);
        exit;
    }

    // 5) Stockage en session
    //   - Tout brut dans $_SESSION['landingpage']
    //   - Aliases pratiques à la racine de la session
    $_SESSION['landingpage']  = $lp; // contient: id, url_ecole, bio, nom_ecole, code_ecole
    $_SESSION['ecole']        = $lp['url_ecole'];     // handle principal (ex: boomrang)
    $_SESSION['url_ecole']    = $lp['url_ecole'];     // alias
    $_SESSION['nom_ecole']    = $lp['nom_ecole'];     // alias
    $_SESSION['bio_ecole']    = $lp['bio'];           // alias
    $_SESSION['code_ecole']   = $lp['code_ecole'];    // ← IMPORTANT
    
    $url_session_ecole = $_SESSION['url_ecole'];
    $nom = $_SESSION['nom_ecole'];
    $bio = $_SESSION['bio_ecole'];

    // (Option) Si tu veux contrôler que code_ecole n'est pas vide :
    if (empty($_SESSION['code_ecole'])) {
        // Ici tu peux soit vider la session, soit rediriger, soit loguer l’incident.
        // On redirige proprement :
        header('Location: https://www.kelasi.education', true, 302);
        exit;
    }

    // Succès : NE PAS exit — laisse la page appelante continuer.

} catch (Throwable $e) {
    http_response_code(500);
    exit('Erreur: ' . $e->getMessage());
} finally {
    // 6) Fermer proprement la connexion
    $pdo = null;
}
