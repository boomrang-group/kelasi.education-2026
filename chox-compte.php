<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Kelasi - Premium</title>
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome pour icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous">

    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #e0e7ff, #f0fdfa);
        margin: 0;
        padding: 0;
    }

    .hero-section {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .card-option {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border-radius: 25px;
        padding: 2.5rem 2rem;
        text-align: center;
        transition: all 0.4s ease;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        position: relative;
    }

    .card-option:hover {
        transform: translateY(-15px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    }

    .card-option .icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
        color: #4f46e5;
    }

    .card-option h3 {
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .card-option p {
        color: #495057;
        margin-bottom: 2rem;
        font-size: 1.05rem;
    }

    .btn-register {
        border-radius: 50px;
        padding: 12px 35px;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .btn-promoteur {
        background-color: #4f46e5;
        color: white;
    }

    .btn-promoteur:hover {
        background-color: #3730a3;
    }

    .btn-eleve {
        background-color: #06b6d4;
        color: white;
    }

    .btn-eleve:hover {
        background-color: #0284c7;
    }

    @media (max-width: 991px) {
        .hero-section {
            flex-direction: column;
            gap: 2.5rem;
        }
    }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-dark bg-light" aria-label="Menu principal">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <img src="img/logo.png" alt="Chargement logo..." srcset="">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false" aria-label="Basculer la navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="btn btn-primary" href="login">Se connecter</a></li>
                </ul>
            </div>
        </div>
    </nav>


    <section class="hero-section container mt-5">
        <div class="row w-100 g-4 justify-content-center">

            <!-- Promoteur / École -->
            <div class="col-lg-5 col-md-6">
                <div class="card-option h-100">
                    <div class="icon"><i class="fas fa-school"></i></div>
                    <h3>Promoteur / École</h3>
                    <p>
                        Gérez facilement votre établissement scolaire avec Kelasi :
                        inscriptions, bulletins, statistiques et communication avec les parents.
                        Une solution digitale complète pour moderniser votre école et simplifier l'administration.
                    </p>
                    <a href="new_school/" class="btn btn-promoteur btn-register"><i
                            class="fas fa-plus me-2"></i>S'inscrire</a>
                </div>
            </div>

            <!-- Élève / Parent -->
            <div class="col-lg-5 col-md-6">
                <div class="card-option h-100">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Élève / Parent</h3>
                    <p>
                        Suivez les progrès scolaires, accédez aux bulletins,
                        communiquez avec les enseignants et recevez toutes les informations importantes en temps réel.
                        Une interface simple et intuitive pour chaque famille.
                    </p>
                    <a href="nos_ecole.php" class="btn btn-eleve btn-register"><i
                            class="fas fa-user-plus me-2"></i>S'inscrire</a>
                </div>
            </div>

        </div>
    </section>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
</body>

</html>