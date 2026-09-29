<?php require 'paserelle_url.php'; ?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title><?php echo $nom ?> | Kelasi</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@600&family=Lobster+Two:wght@700&display=swap"
        rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
    <style>
    :root {
        --primary: #007BFF;
        --background: #f0f2f5;
        --card-bg: #ffffff;
        --border-color: #ddd;
        --text-color: #333;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        background-color: var(--background);
        color: var(--text-color);
        padding: 20px;
    }

    h1 {
        text-align: center;
        color: var(--primary);
        margin-bottom: 30px;
    }

    .card {
        background-color: var(--card-bg);
        border-radius: 8px;
        padding: 20px;
        margin: auto;
        max-width: 1000px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .form-group {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .form-field {
        flex: 1;
        min-width: 200px;
    }

    .form-field input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    th {
        background-color: var(--primary);
        color: white;
        font-weight: 600;
    }

    input[type="number"],
    input[type="text"] {
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #ccc;
        width: 100%;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }

    input[type="number"]:focus,
    input[type="text"]:focus {
        border-color: var(--primary);
        outline: none;
    }

    #total {
        font-size: 20px;
        font-weight: bold;
        text-align: right;
        margin-top: 20px;
        color: #222;
    }

    @media (max-width: 768px) {

        table,
        thead,
        tbody,
        th,
        td,
        tr {
            display: block;
        }

        tr {
            margin-bottom: 15px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            padding: 10px;
        }

        td {
            border: none;
            padding: 10px 0;
        }

        td:before {
            content: attr(data-label);
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        th {
            display: none;
        }

        #total {
            text-align: center;
        }

        .form-group {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white navbar-light sticky-top px-4 px-lg-5 py-lg-0">
        <a href="index.html" class="navbar-brand">
            <img src="https://kelasi.education/wp-content/uploads/2025/02/cropped-expert-comptable-qui-accompagne-un-createur-dentreprise-1-1.png"
                alt="" srcset="">
            <!-- <h1 class="m-0 text-primary"><i class="fa fa-book-reader me-3"></i>Kelasi</h1> -->
            <!-- <h1 class="m-0 text-primary"><i class="fa fa-book-reader me-3"></i><?php echo $nom ?></h1> -->
        </a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <div class="navbar-nav mx-auto">
                <!-- <a href="index.html" class="nav-item nav-link active">Home</a>
                <a href="#apropos_de_nous" class="nav-item nav-link">A propos de nous</a>
                <a href="notation.php?ecole=<?php echo $url_session_ecole; ?>" class="nav-item nav-link"
                    target="_blank">Notation</a>
                <div class="d-none nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Pages</a>
                    <div class="dropdown-menu rounded-0 rounded-bottom border-0 shadow-sm m-0">
                        <a href="facility.html" class="dropdown-item">School Facilities</a>
                        <a href="team.html" class="dropdown-item">Popular Teachers</a>
                        <a href="call-to-action.html" class="dropdown-item">Become A Teachers</a>
                        <a href="appointment.html" class="dropdown-item">Make Appointment</a>
                        <a href="testimonial.html" class="dropdown-item">Testimonial</a>
                        <a href="404.html" class="dropdown-item">404 Error</a>
                    </div>
                </div>
                <a href="#contact" class="nav-item nav-link">Contact Us</a> -->
            </div>
            <a href=".?ecole=<?php echo $url_session_ecole; ?>"
                class="btn btn-primary rounded-pill px-3 d-none d-lg-block">Accueil<i
                    class="fa fa-arrow-right ms-3"></i></a>
        </div>
    </nav>
    <h1 class="mt-5">Évaluation des critères</h1>

    <div class="card">

        <div class="form-group">
            <div class="form-field">
                <label for="url_ecole">Nom de l’école :</label>
                <input type="text" placeholder="<?php echo $nom; ?>" value="<?php echo $nom; ?>" readonly>
                <input type="hidden" id="url_ecole" name="url_ecole" placeholder="<?php echo $url_session_ecole; ?>"
                    value="<?php echo $url_session_ecole; ?>" readonly>
            </div>
            <div class="form-field">
                <label for="evaluateur">Nom de l’évaluateur :</label>
                <input type="text" id="evaluateur" placeholder="Ex : Mme Dupont">
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Critère</th>
                    <th>Pondération</th>
                    <th>Note attribuée</th>
                    <th>Observation / Justification</th>
                </tr>
            </thead>
            <tbody id="criteriaTable">
                <!-- Généré en JS -->
            </tbody>
        </table>

        <div id="total">Total des notes : 0</div>
        <!-- <div style="margin-top: 30px; text-align: right;">
            <button onclick="afficherEvaluation()"
                style="padding: 10px 20px; font-size: 16px; background-color: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                Évaluer
            </button>
        </div> -->

        <div style="margin-top: 30px; text-align: right;">
            <!-- <button onclick="afficherEvaluation()"
                style="padding: 10px 20px; margin-right: 10px; background-color: #28a745; color: white; border: none; border-radius: 5px;">Évaluer</button> -->
            <button onclick="enregistrerEvaluation()"
                style="padding: 10px 20px; background-color:rgb(35, 68, 236); color: white; border: none; border-radius: 5px;">Evaluer</button>
        </div>


        <div id="evaluationResult" style="margin-top: 20px; text-align: center;">
            <!-- Les étoiles et message apparaîtront ici -->
        </div>

    </div>

    <div id="stars" style="margin-top: 20px; font-size: 30px; color: gold; text-align: center;"></div>

    </div>

    <!-- <h2 style="margin-top: 40px;">📋 Évaluations enregistrées</h2>
    <div id="listeEvaluations" class="container mt-4"></div> -->

    <script>
    const criteres = [{
            nom: "Résultats académiques",
            poids: 20
        },
        {
            nom: "Qualité de l’enseignement",
            poids: 15
        },
        {
            nom: "Environnement scolaire",
            poids: 10
        },
        {
            nom: "Gouvernance et transparence",
            poids: 10
        },
        {
            nom: "Satisfaction des élèves et parents",
            poids: 10
        },
        {
            nom: "Inclusion et équité",
            poids: 5
        },
        {
            nom: "Innovation et numérique",
            poids: 5
        },
        {
            nom: "Recherche (publications, brevets, financement)",
            poids: 10
        },
        {
            nom: "Employabilité des diplômés",
            poids: 10
        },
        {
            nom: "Ouverture et partenariats",
            poids: 5
        }
    ];

    const tableBody = document.getElementById("criteriaTable");

    criteres.forEach((critere, index) => {
        const row = document.createElement("tr");

        row.innerHTML = `
        <td data-label="Critère">${critere.nom}</td>
        <td data-label="Pondération">${critere.poids}</td>
        <td data-label="Note">
          <input type="number" id="note${index}" min="0" max="${critere.poids}" oninput="validerEtCalculer(${index}, ${critere.poids})">
        </td>
        <td data-label="Observation"><input type="text" placeholder="Votre observation..."></td>
      `;

        tableBody.appendChild(row);
    });

    function validerEtCalculer(index, max) {
        const noteInput = document.getElementById(`note${index}`);
        let val = parseFloat(noteInput.value);

        if (val > max) {
            alert(`Note maximale pour ce critère : ${max}`);
            noteInput.value = max;
        } else if (val < 0) {
            noteInput.value = 0;
        }

        calculerTotal();
    }

    function calculerTotal() {
        let total = 0;

        criteres.forEach((_, index) => {
            const val = parseFloat(document.getElementById(`note${index}`).value);
            total += isNaN(val) ? 0 : val;
        });

        document.getElementById("total").innerText = `Total des notes : ${total}`;
    }

    function afficherEvaluation() {
        const totalMax = criteres.reduce((acc, c) => acc + c.poids, 0); // 100
        const total = parseFloat(document.getElementById("total").innerText.replace(/\D/g, "")) || 0;

        const noteSur5 = (total / totalMax) * 5;
        const fullStars = Math.floor(noteSur5);
        const halfStar = noteSur5 % 1 >= 0.5 ? 1 : 0;
        const emptyStars = 5 - fullStars - halfStar;

        let starsHTML = '';
        for (let i = 0; i < fullStars; i++) starsHTML += '★';
        if (halfStar) starsHTML += '☆'; // ou '⯪' pour une vraie demi-étoile
        for (let i = 0; i < emptyStars; i++) starsHTML += '✩';

        document.getElementById("stars").innerHTML = `Évaluation globale :<br>${starsHTML}`;
    }

    function afficherEvaluation() {
        const totalMax = criteres.reduce((acc, c) => acc + c.poids, 0); // 100
        const total = parseFloat(document.getElementById("total").innerText.replace(/\D/g, "")) || 0;

        const noteSur5 = (total / totalMax) * 5;
        const fullStars = Math.floor(noteSur5);
        const hasHalf = noteSur5 % 1 >= 0.25 && noteSur5 % 1 < 0.75;
        const emptyStars = 5 - fullStars - (hasHalf ? 1 : 0);

        let starsHTML = '';

        const starFull =
            `<svg width="32" height="32" viewBox="0 0 24 24" fill="gold" xmlns="http://www.w3.org/2000/svg"><path d="M12 .587l3.668 7.431L24 9.748l-6 5.848L19.335 24 12 20.015 4.665 24 6 15.596 0 9.748l8.332-1.73z"/></svg>`;
        const starHalf =
            `<svg width="32" height="32" viewBox="0 0 24 24" fill="gold" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="half"><stop offset="50%" stop-color="gold"/><stop offset="50%" stop-color="lightgray"/></linearGradient></defs><path fill="url(#half)" d="M12 .587l3.668 7.431L24 9.748l-6 5.848L19.335 24 12 20.015 4.665 24 6 15.596 0 9.748l8.332-1.73z"/></svg>`;
        const starEmpty =
            `<svg width="32" height="32" viewBox="0 0 24 24" fill="lightgray" xmlns="http://www.w3.org/2000/svg"><path d="M12 .587l3.668 7.431L24 9.748l-6 5.848L19.335 24 12 20.015 4.665 24 6 15.596 0 9.748l8.332-1.73z"/></svg>`;

        for (let i = 0; i < fullStars; i++) starsHTML += starFull;
        if (hasHalf) starsHTML += starHalf;
        for (let i = 0; i < emptyStars; i++) starsHTML += starEmpty;

        // Message qualitatif
        let message = "";
        if (noteSur5 >= 4.5) message = "Excellent";
        else if (noteSur5 >= 3.5) message = "Très bien";
        else if (noteSur5 >= 2.5) message = "Satisfaisant";
        else if (noteSur5 >= 1.5) message = "Insuffisant";
        else message = "À améliorer";

        document.getElementById("evaluationResult").innerHTML = `
    <div style="margin-bottom: 10px;">Évaluation globale :</div>
    <div style="display: flex; justify-content: center; gap: 4px;">${starsHTML}</div>
    <div style="margin-top: 10px; font-size: 18px; font-weight: bold;">${message}</div>
  `;
    }


    function enregistrerEvaluation() {
        const url_ecole = document.getElementById("url_ecole").value;
        const evaluateur = document.getElementById("evaluateur").value;
        const totalMax = criteres.reduce((acc, c) => acc + c.poids, 0);
        const total = parseFloat(document.getElementById("total").innerText.replace(/\D/g, "")) || 0;
        const noteSur5 = (total / totalMax) * 5;

        let mention = "";
        if (noteSur5 >= 4.5) mention = "Excellent";
        else if (noteSur5 >= 3.5) mention = "Très bien";
        else if (noteSur5 >= 2.5) mention = "Satisfaisant";
        else if (noteSur5 >= 1.5) mention = "Insuffisant";
        else mention = "À améliorer";

        fetch('enregistrer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    url_ecole,
                    evaluateur,
                    total_note: total,
                    note_sur_5: noteSur5.toFixed(2),
                    mention
                })
            })
            .then(res => res.text())
            .then(response => {
                alert("Évaluation enregistrée avec succès !");
                afficherListeEvaluations(); // recharger affichage
                resetForm();
            })
            .catch(err => {
                // alert("Erreur lors de l’enregistrement.");
                console.error(err);
            });
    }

    function resetForm() {
        document.getElementById("evaluateur").value = "";

        // Réinitialise toutes les notes
        criteres.forEach((_, index) => {
            document.getElementById(`note${index}`).value = "";
        });

        // Remet le total à 0
        document.getElementById("total").innerText = "Total des notes : 0";
    }
    </script>

</body>

</html>