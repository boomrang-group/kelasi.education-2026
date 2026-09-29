<?php
// Connexion à la base de données
require_once __DIR__ . '/../../database/db_connect.php';

// Requête pour récupérer les données
$sql = "SELECT * FROM ecoles";
$stmt = $pdo->query($sql);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Liste des utilisateurs</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap');

    *,
    *:after,
    *:before {
        box-sizing: border-box;
    }

    body {
        font-family: "Lexend", sans-serif;
        line-height: 1.5;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #393232;
    }

    img {
        max-width: 100%;
        display: block;
    }

    .card-list {
        width: 90%;
        max-width: 400px;
    }

    .card {
        background-color: #FFF;
        box-shadow: 0 0 0 1px rgba(#000, .05), 0 20px 50px 0 rgba(#000, .1);
        border-radius: 15px;
        overflow: hidden;
        padding: 1.25rem;
        position: relative;
        transition: .15s ease-in;

        &:hover,
        &:focus-within {
            box-shadow: 0 0 0 2px #16C79A, 0 10px 60px 0 rgba(#000, .1);
            transform: translatey(-5px);
        }
    }

    .card-image {
        border-radius: 10px;
        overflow: hidden;
    }

    .card-header {
        margin-top: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;

        a {
            font-weight: 600;
            font-size: 1.375rem;
            line-height: 1.25;
            padding-right: 1rem;
            text-decoration: none;
            color: inherit;
            will-change: transform;

            &:after {
                content: "";
                position: absolute;
                left: 0;
                top: 0;
                right: 0;
                bottom: 0;
            }
        }


    }

    .icon-button {
        border: 0;
        background-color: #fff;
        border-radius: 50%;
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
        font-size: 1.25rem;
        transition: .25s ease;
        box-shadow: 0 0 0 1px rgba(#000, .05), 0 3px 8px 0 rgba(#000, .15);
        z-index: 1;
        cursor: pointer;
        color: #565656;

        svg {
            width: 1em;
            height: 1em;
        }

        &:hover,
        &:focus {
            background-color: #EC4646;
            color: #FFF;
        }
    }

    .card-footer {
        margin-top: 1.25rem;
        border-top: 1px solid #ddd;
        padding-top: 1.25rem;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }

    .card-meta {
        display: flex;
        align-items: center;
        color: #787878;

        &:first-child:after {
            display: block;
            content: "";
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background-color: currentcolor;
            margin-left: .75rem;
            margin-right: .75rem;
        }

        svg {
            flex-shrink: 0;
            width: 1em;
            height: 1em;
            margin-right: .25em;
        }
    }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.min.js"
        integrity="sha384-RuyvpeZCxMJCqVUGFI0Do1mQrods/hhxYlcVfGPOfQtPJh0JCw12tUAZ/Mv10S7D" crossorigin="anonymous">
    </script>
</head>

<body>
    <h1>Liste des utilisateurs</h1>
    <div class="card-list">
        <article class="card">
            <figure class="card-image">
                <img src="https://images.unsplash.com/photo-1494253109108-2e30c049369b?crop=entropy&cs=srgb&fm=jpg&ixid=MnwxNDU4OXwwfDF8cmFuZG9tfHx8fHx8fHx8MTYyNDcwMTUwOQ&ixlib=rb-1.2.1&q=85"
                    alt="An orange painted blue, cut in half laying on a blue background" />
            </figure>
            <div class="card-header">
                <a href="#">When life gives you oranges</a>
                <button class="icon-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        display="block" id="Heart">
                        <path
                            d="M7 3C4.239 3 2 5.216 2 7.95c0 2.207.875 7.445 9.488 12.74a.985.985 0 0 0 1.024 0C21.125 15.395 22 10.157 22 7.95 22 5.216 19.761 3 17 3s-5 3-5 3-2.239-3-5-3z" />
                    </svg>

                </button>
            </div>
            <div class="card-footer">
                <div class="card-meta card-meta--views">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        display="block" id="EyeOpen">
                        <path
                            d="M21.257 10.962c.474.62.474 1.457 0 2.076C19.764 14.987 16.182 19 12 19c-4.182 0-7.764-4.013-9.257-5.962a1.692 1.692 0 0 1 0-2.076C4.236 9.013 7.818 5 12 5c4.182 0 7.764 4.013 9.257 5.962z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    2,465
                </div>
                <div class="card-meta card-meta--date">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        display="block" id="Calendar">
                        <rect x="2" y="4" width="20" height="18" rx="4" />
                        <path d="M8 2v4" />
                        <path d="M16 2v4" />
                        <path d="M2 10h20" />
                    </svg>
                    Jul 26, 2019
                </div>
            </div>
        </article>
    </div>


    <div class="d-none row">
        <?php foreach ($utilisateurs as $u): ?>
        <div class="col-lg-4 col-sm-12 mb-3">
            <div class="card" style="width: 18rem;">
                <img src="<?= $u['logo'] ?>" class="card-img-top" alt="Logo">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($u['nom_ecole']) ?></h5>
                    <p class="card-text"><?= $u['telephone1'] ?> <?= $u['email'] ?></p>
                    <a href="#" class="btn btn-primary">Inscription</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="traitement_selection.php" >
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="toggle(this)"> Tout cocher</th>
                    <th>ID</th>
                    <th>Organisation</th>
                    <th>Ville</th>
                    <th>Pays</th>
                    <th>Province</th>
                    <th>Logo</th>
                    <th>Document</th>
                    <th>Adresse</th>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Type de pièce</th>
                    <th>Pièce jointe</th>
                    <th>Téléphones</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td><input type="checkbox" name="select[]" value="<?= htmlspecialchars((string)$u['id']) ?>"></td>
                    <td><?= htmlspecialchars((string)$u['id']) ?></td>
                    <td><?= htmlspecialchars((string)$u['organisation']) ?></td>
                    <td><?= htmlspecialchars((string)$u['ville']) ?></td>
                    <td><?= htmlspecialchars((string)$u['pays']) ?></td>
                    <td><?= htmlspecialchars((string)$u['province']) ?></td>
                    <td><img src="<?= htmlspecialchars((string)$u['logo']) ?>" alt="Logo" style="max-width: 50px;"></td>
                    <td><a href="<?= htmlspecialchars((string)$u['docs']) ?>" target="_blank">Voir document</a></td>
                    <td><?= htmlspecialchars((string)$u['adresse']) ?></td>
                    <td><?= htmlspecialchars((string)$u['prenom']) ?></td>
                    <td><?= htmlspecialchars((string)$u['nom']) ?></td>
                    <td><?= htmlspecialchars((string)$u['type_piece']) ?></td>
                    <td><img src="<?= htmlspecialchars((string)$u['piece_jointe']) ?>" alt="Pièce jointe" style="max-width: 50px;"></td>
                    <td><?= htmlspecialchars((string)$u['telephone1']) ?><br><?= htmlspecialchars((string)$u['telephone2']) ?></td>
                    <td><?= htmlspecialchars((string)$u['email']) ?></td>
                    <td><?= htmlspecialchars((string)$u['username']) ?></td>
                    <td><?= htmlspecialchars((string)$u['statut']) ?></td>
                    <td><?= htmlspecialchars((string)$u['date_enregistrement']) ?></td>
                    <td>
                        <a href="editer.php?id=<?= htmlspecialchars((string)$u['id']) ?>">Éditer</a> |
                        <a href="copier.php?id=<?= htmlspecialchars((string)$u['id']) ?>">Copier</a> |
                        <a href="supprimer.php?id=<?= htmlspecialchars((string)$u['id']) ?>"
                            onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <select name="action">
            <option value="">Avec la sélection :</option>
            <option value="supprimer">Supprimer</option>
            <option value="approuver">Approuver</option>
        </select>
        <button type="submit">Exécuter</button>
    </form>

    <script>
    function toggle(source) {
        checkboxes = document.querySelectorAll('input[name="select[]"]');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
    </script>
</body>

</html>