<?php
declare(strict_types=1);

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Vérifier que l'utilisateur est connecté
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: ../../login/index.php');
    exit;
}

// Vérifier le rôle autorisé
$role = strtolower(trim((string) $_SESSION['role']));

if ($role !== 'eleve_encadre') {
    header('Location: ../../login/index.php');
    exit;
}

// Informations de l'utilisateur connecté
$userId = (int) $_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? 'Élève encadré');
$firstName = (string) ($_SESSION['first_name'] ?? '');
$lastName = (string) ($_SESSION['last_name'] ?? '');

$nomComplet = trim($firstName . ' ' . $lastName);

if ($nomComplet === '') {
    $nomComplet = $username;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Espace Élève encadré</title>

    <style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        color: #263238;
    }

    .navbar {
        background: #174ea6;
        color: white;
        padding: 18px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .navbar h2 {
        margin: 0;
        font-size: 20px;
    }

    .container {
        max-width: 1100px;
        margin: 35px auto;
        padding: 0 20px;
    }

    .welcome {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px #0000000d;
    }

    .welcome h1 {
        margin-top: 0;
        color: #174ea6;
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-top: 25px;
    }

    .card {
        background: white;
        padding: 22px;
        border-radius: 10px;
        box-shadow: 0 2px 10px #0000000d;
    }

    .card h3 {
        margin-top: 0;
        color: #174ea6;
    }

    .logout {
        background: #dc3545;
        color: white;
        padding: 10px 16px;
        text-decoration: none;
        border-radius: 5px;
    }

    .logout:hover {
        background: #b02a37;
    }
    </style>
</head>

<body>

    <header class="navbar">
        <h2>Espace Élève encadré</h2>

        <a class="logout" href="../../login/logout.php">
            Déconnexion
        </a>
    </header>

    <main class="container">

        <section class="welcome">
            <h1>Bienvenue, <?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?> !</h1>

            <p>
                Bienvenue dans votre espace personnel de répétitorat.
            </p>

            <p>
                <strong>Nom d'utilisateur :</strong>
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </section>

        <section class="cards">

            <article class="card">
                <h3>Mes cours</h3>
                <p>Consultez vos cours de répétitorat.</p>
            </article>

            <article class="card">
                <h3>Mes exercices</h3>
                <p>Retrouvez vos exercices et travaux.</p>
            </article>

            <article class="card">
                <h3>Mon profil</h3>
                <p>Consultez vos informations personnelles.</p>
            </article>

        </section>

    </main>

</body>

</html>