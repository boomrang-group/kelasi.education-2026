<?php
session_start();

// Détruire la session
$_SESSION = array();
session_destroy();

// Empêcher la mise en cache côté navigateur
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// On ne fait pas de redirection PHP ici pour pouvoir injecter du JS qui bloque le retour
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Déconnexion</title>
    <script>
    // Bloquer le bouton retour
    (function() {
        // Empiler une nouvelle entrée dans l'historique
        history.pushState(null, null, location.href);

        window.addEventListener('popstate', function(event) {
            // Quand l'utilisateur clique sur retour, on empile à nouveau la page actuelle
            history.pushState(null, null, location.href);
        });
    })();

    // Redirection après 1 seconde
    setTimeout(function() {
        window.location.href = "https://kelasi.education";
    }, 1000);
    </script>
</head>

<body>
    <p>Vous avez été déconnecté. Redirection en cours...</p>
</body>

</html>