<?php
// nos_ecole.php

// 1. Connexion à la base de données
require 'database/db_connect.php';

// 2. Récupérer toutes les écoles approuvées
$stmt = $pdo->prepare("SELECT * FROM ecoles WHERE statut = 'approuver' ORDER BY nom_ecole ASC");
$stmt->execute();
$ecoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Écoles - Kelasi</title>
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous">

    <style>
    :root {
        --brand-blue: #2A388F;
        --brand-cyan: #00AEEF;
        --brand-orange: #F7941D;
        --brand-dark: #0F172A;
        --brand-gray: #475569;
        --brand-bg-light: #F8FAFC;
        --card-shadow: 0 15px 30px -10px rgba(42, 56, 143, 0.08);
        --card-shadow-hover: 0 20px 35px -5px rgba(0, 174, 239, 0.2);
        --radius-lg: 20px;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--brand-bg-light);
        color: var(--brand-gray);
    }

    /* NAVBAR / HEADER */
    .navbar {
        background-color: #ffffff !important;
        border-bottom: 2px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        padding: 0.8rem 0;
    }

    .navbar-brand img {
        height: 40px;
    }

    /* HERO */
    .hero {
        position: relative;
        padding-top: 150px;
        padding-bottom: 70px;
        background: linear-gradient(135deg, #0F172A 0%, #2A388F 60%, #1e2660 100%);
        color: white;
        overflow: hidden;
    }

    .hero::before {
        content: "";
        position: absolute;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(0, 174, 239, 0.25) 0%, rgba(0, 0, 0, 0) 70%);
        top: -50px;
        right: -50px;
        border-radius: 50%;
    }

    .hero h1 {
        font-size: clamp(2.2rem, 5vw, 3.5rem);
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    /* SEARCH BAR */
    .search-container {
        max-width: 600px;
        margin: -30px auto 40px auto;
        position: relative;
        z-index: 10;
    }

    .search-bar {
        background: #ffffff;
        border-radius: 50px;
        padding: 6px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }

    .search-bar .input-group-text {
        background: transparent;
        border: none;
        color: var(--brand-cyan);
        padding-left: 1.5rem;
        font-size: 1.2rem;
    }

    .search-bar .form-control {
        border: none;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        background: transparent;
    }

    .search-bar .form-control:focus {
        box-shadow: none;
    }

    /* CARDS ÉCOLE */
    .card-ecole {
        border-radius: var(--radius-lg);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: var(--card-shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-ecole:hover {
        transform: translateY(-6px);
        box-shadow: var(--card-shadow-hover);
        border-color: rgba(0, 174, 239, 0.3);
    }

    .school-logo-wrapper {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        margin: 0 auto 1.25rem;
        padding: 4px;
        background: linear-gradient(135deg, var(--brand-blue), var(--brand-cyan));
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
    }

    .school-logo-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        background: #ffffff;
    }

    .ville-pays {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--brand-bg-light);
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-gray);
    }

    .btn-brand-blue {
        background-color: var(--brand-blue);
        color: #ffffff;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-brand-blue:hover {
        background-color: #1f2a6d;
        color: #ffffff;
    }

    .btn-brand-outline {
        border: 1px solid #cbd5e1;
        color: var(--brand-dark);
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-brand-outline:hover {
        background-color: var(--brand-bg-light);
        border-color: #94a3b8;
    }

    .btn-brand-primary {
        background-color: var(--brand-blue);
        color: #ffffff;
        border-radius: 50px;
        font-weight: 600;
        padding: 12px;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-brand-primary:hover {
        background-color: #1f2a6d;
        color: #ffffff;
    }

    /* SECTION CONTACT & FOOTER HELPER CLASSES */
    .section-padding {
        padding: 80px 0;
    }

    .text-brand-blue {
        color: var(--brand-blue) !important;
    }

    .text-brand-orange {
        color: var(--brand-orange) !important;
    }

    .feature-icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .card-custom {
        background: #ffffff;
        border-radius: var(--radius-lg);
        border: 1px solid #e2e8f0;
        box-shadow: var(--card-shadow);
    }

    .form-control-styled {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 12px 16px;
    }

    .form-control-styled:focus {
        border-color: var(--brand-cyan);
        box-shadow: 0 0 0 3px rgba(0, 174, 239, 0.15);
    }

    footer {
        background-color: var(--brand-dark);
        color: #94a3b8;
        padding-top: 60px;
        padding-bottom: 30px;
    }

    footer h5 {
        color: #ffffff;
        font-weight: 700;
        margin-bottom: 1.25rem;
    }

    footer a {
        color: #94a3b8;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    footer a:hover {
        color: var(--brand-cyan);
    }

    .social-icon {
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff !important;
    }

    .social-icon:hover {
        background: var(--brand-cyan);
    }
    </style>
</head>

<body>
    <!-- NAVBAR (HEADER AVEC BACKGROUND BLANC STRUCTURÉ) -->
    <nav class="navbar navbar-expand-lg fixed-top" aria-label="Menu principal">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="img/logo.png" alt="Logo Kelasi">
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false" aria-label="Basculer la navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link fw-semibold text-dark me-2" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-brand-blue px-4" href="login/">Se connecter</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero text-center position-relative">
        <div class="container position-relative" style="z-index: 2;">
            <h1>Nos Écoles Partenaires</h1>
            <p class="lead opacity-85 mx-auto max-w-600">Découvrez les établissements affiliés au réseau Kelasi et
                accédez directement à leurs portails digitaux.</p>
        </div>
    </section>

    <!-- BARRE DE RECHERCHE -->
    <div class="container search-container">
        <div class="search-bar input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par nom, ville ou pays...">
        </div>
    </div>

    <!-- LISTE DES ÉCOLES -->
    <section class="container mb-5">
        <div class="row g-4" id="ecolesContainer">
            <?php foreach ($ecoles as $ecole): ?>
            <div class="col-lg-4 col-md-6 ecole-card">
                <div class="card card-ecole h-100 text-center p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="school-logo-wrapper">
                            <?php 
                                $logo_path = !empty($ecole['logo']) ? 'uploads/logo/' . htmlspecialchars($ecole['logo']) : 'img/symbole-kelasi.png';
                            ?>
                            <img src="<?= $logo_path ?>" alt="Logo <?= htmlspecialchars($ecole['nom_ecole']) ?>"
                                class="school-logo-img">
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($ecole['nom_ecole']) ?></h5>

                        <div class="mb-3">
                            <span class="ville-pays">
                                <i class="fas fa-location-dot text-brand-orange"></i>
                                <?= htmlspecialchars($ecole['ville'] ?? 'Ville non précisée') ?><?= !empty($ecole['pays']) ? ', ' . htmlspecialchars($ecole['pays']) : '' ?>
                            </span>
                        </div>

                        <?php if (!empty($ecole['adress'])): ?>
                        <p class="text-muted small mb-3">
                            <?= htmlspecialchars(substr($ecole['adress'], 0, 80)) . (strlen($ecole['adress']) > 80 ? '...' : '') ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 pt-3 border-top mt-3">
                        <a href="nos_ecoles/home/?ecole=<?= urlencode($ecole['url_ecole']) ?>"
                            class="btn btn-brand-blue btn-sm flex-grow-1 py-2">
                            <i class="fas fa-globe me-1"></i> Portail
                        </a>
                        <a href="nos_ecoles/home/inscription.php?ecole=<?= urlencode($ecole['url_ecole']) ?>"
                            class="btn btn-brand-outline btn-sm flex-grow-1 py-2">
                            <i class="fas fa-user-plus me-1"></i> S'inscrire
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- CONTACT -->
    <section id="contact" class="section-padding bg-light">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="text-brand-blue fw-bold text-uppercase small">Assistance</span>
                    <h2 class="section-title mt-1 mb-4">Besoin d'un accompagnement ?</h2>
                    <p class="text-muted mb-4">Notre équipe d'experts est à votre disposition pour vous effectuer une
                        démonstration personnalisée ou répondre à vos questions.</p>
                    <div class="d-flex align-items-center mb-3">
                        <div class="feature-icon-wrapper bg-white shadow-sm text-brand-blue mb-0 me-3"
                            style="width: 48px; height: 48px;">
                            <i class="fas fa-envelope fs-6"></i>
                        </div>
                        <div>
                            <small class="text-muted d-block">Adresse e-mail</small>
                            <span class="fw-semibold text-dark">produitsdigitauxbureau@gmail.com</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="feature-icon-wrapper bg-white shadow-sm text-success mb-0 me-3"
                            style="width: 48px; height: 48px;">
                            <i class="fas fa-phone fs-6"></i>
                        </div>
                        <div>
                            <small class="text-muted d-block">Téléphone</small>
                            <span class="fw-semibold text-dark">+243 000 000 000</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card card-custom p-4 p-md-5">
                        <h4 class="fw-bold text-dark mb-4">Envoyez-nous un message</h4>
                        <form id="contactForm" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" name="name" class="form-control form-control-styled"
                                        placeholder="Nom complet" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="email" name="email" class="form-control form-control-styled"
                                        placeholder="Adresse e-mail" required>
                                </div>
                                <div class="col-12">
                                    <textarea name="message" class="form-control form-control-styled" rows="4"
                                        placeholder="Votre message ou demande spécifique..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <div id="formAlert"></div>
                                    <button type="submit" class="btn btn-brand-primary w-100 mt-2">Envoyer la
                                        demande</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-lg-4">
                    <img src="img/logo.png" alt="Logo Kelasi" class="mb-3" style="height: 40px;">
                    <p class="small text-muted mb-4">Plateforme digitale dédiée à la modernisation de l'écosystème
                        éducatif africain.</p>
                    <div class="d-flex gap-2">
                        <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon" aria-label="X/Twitter"><i class="fab fa-x-twitter"></i></a>
                        <a href="#" class="social-icon" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>Navigation</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="#">Accueil</a></li>
                        <li class="mb-2"><a href="#about">À propos</a></li>
                        <li class="mb-2"><a href="#stats">Impact</a></li>
                        <li class="mb-2"><a href="#schools">Écoles</a></li>
                        <li class="mb-2"><a href="#blog">Ressources</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>Accès Direct</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="login/">Connexion</a></li>
                        <li class="mb-2"><a href="chox-compte.php">Inscription</a></li>
                        <li class="mb-2"><a href="#contact">Support</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5>Information</h5>
                    <p class="small text-muted">Kinshasa, République Démocratique du Congo</p>
                </div>
            </div>
            <div class="border-top border-secondary pt-4 text-center small">
                <p class="mb-0">&copy; <?= date('Y') ?> Kelasi. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>

    <!-- Recherche Live JS -->
    <script>
    document.getElementById('searchInput').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const ecoleCards = document.querySelectorAll('.ecole-card');

        ecoleCards.forEach(card => {
            const cardContent = card.innerText.toLowerCase();
            if (cardContent.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
    </script>
</body>

</html>