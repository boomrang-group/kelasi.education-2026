<?php
include '../database/db_connect.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nos écoles | MyKelasi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .text {
            font-size: 3rem;
            font-weight: bold;
            color: #0b78e4ff;
        }
        .lead {
            text-align: justify;
            font-size: 1.3rem;
            font-weight: 300;
        }
        .card-school {
            transition: transform .2s;
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .card-school:hover {
            transform: scale(1.03);
        }
        .card-img-top {
            height: 160px;
            object-fit: contain;
            background: #f9fafb;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>

<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="https://kelasi.education/wp-content/uploads/2025/02/cropped-expert-comptable-qui-accompagne-un-createur-dentreprise-1-1.png"
                 alt="Kelasi.Education" height="60">
        </a>
    </div>
</nav>

<div class="container my-5">
    <div class="row mb-5">
        <div class="col-lg-6">
            <h1 class="text mb-3">- NOS ÉCOLES -</h1>
            <p class="lead">
                Kelasi.Education propose des programmes et des cours en ligne provenant d’écoles engagées
                dans l’innovation et l’excellence éducative.
            </p>
        </div>
        <div class="col-lg-6 text-center">
            <img src="https://kelasi.education/wp-content/uploads/2025/03/kelasi-2_055057-1.png"
                 class="img-fluid" alt="Kelasi">
        </div>
    </div>

    <div class="row">
        <?php
        $stmt = $pdo->query("
            SELECT 
                e.nom_ecole,
                e.logo,
                e.code_ecole,
                ev.total_note AS note,
                ev.note_sur_5,
                ev.mention,
                e.url_ecole
            FROM ecoles AS e
            LEFT JOIN (
                SELECT * FROM evaluee
                WHERE (url_ecole, date_eval) IN (
                    SELECT url_ecole, MAX(date_eval) FROM evaluee GROUP BY url_ecole
                )
            ) AS ev ON e.url_ecole = ev.url_ecole
            ORDER BY ev.total_note DESC
        ");

        while ($row = $stmt->fetch()):
            $note = (int)($row["note"] ?? 0);
            $stars = $note ? ceil($note / 20) : 0;

            // fichier dans la base
            $logoFile = trim($row['logo'] ?? "");

            // chemin web correct (DEPUIS CE FICHIER)
            $logoWebPath = "../uploads/ecoles/" . $logoFile;

            // chemin serveur pour vérifier existence
            $logoFsPath = __DIR__ . "/../uploads/ecoles/" . $logoFile;

            // si pas trouvé => placeholder
            if ($logoFile === "" || !file_exists($logoFsPath)) {
                $logoWebPath = "https://kelasi.education/wp-content/uploads/2025/02/cropped-expert-comptable-qui-accompagne-un-createur-dentreprise-1-1.png";
            }

            // lien d'accès
            $url_ecole = !empty($row["url_ecole"])
                ? "home?ecole=" . urlencode($row["url_ecole"])
                : "home?ecole=" . urlencode($row["code_ecole"]);
        ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card card-school h-100">
                <img src="<?= htmlspecialchars($logoWebPath) ?>" class="card-img-top"
                     alt="<?= htmlspecialchars($row['nom_ecole']) ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($row['nom_ecole']) ?></h5>
                    <ul class="list-unstyled">
                        <li>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= $i <= $stars ? 'fas' : 'far' ?> fa-star" style="color: gold"></i>
                            <?php endfor; ?>

                            <?php if ($row["note_sur_5"] !== null): ?>
                                <span class="ms-2"><?= htmlspecialchars($row["note_sur_5"]) ?>/5</span>
                            <?php endif; ?>

                            <?php if (!empty($row["mention"])): ?>
                                <br><span class="ms-2 small text-muted"><?= htmlspecialchars($row["mention"]) ?></span>
                            <?php endif; ?>
                        </li>
                    </ul>
                    <a href="<?= htmlspecialchars($url_ecole) ?>"
                       class="btn btn-primary w-100 mt-2" target="_blank">
                        Accéder
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<footer class="container-fluid bg-dark text-white text-center py-3 mt-5">
    © BoomRang Group — Tous droits réservés.
</footer>

</body>
</html>
