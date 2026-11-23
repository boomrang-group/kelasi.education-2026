<?php
// nos_ecoles.php

// 1. Connexion à la base de données
require 'database/db_connect.php';

// 2. Récupérer toutes les écoles approuvées
$stmt = $pdo->prepare("SELECT * FROM ecoles WHERE statut = 'approuver' ORDER BY nom_ecole ASC");
$stmt->execute();
$ecoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Compter les écoles
$total_ecoles = count($ecoles);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelasi - Nos Écoles</title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --brand-blue: #2A388F;
        --brand-cyan: #00AEEF;
        --brand-orange: #F7941D;
        --brand-dark: #0F172A;
        --brand-gray: #475569;
        --brand-bg-light: #F8FAFC;
        --gradient-primary: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #9333ea 100%);
        --card-shadow: 0 15px 30px -10px rgba(42, 56, 143, 0.08);
        --card-shadow-hover: 0 20px 35px -5px rgba(0, 174, 239, 0.2);
        --radius-lg: 20px;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--brand-bg-light);
        color: var(--brand-gray);
        padding-top: 76px;
    }

    /* PRELOADER STYLING */
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #ffffff;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }

    .preloader-logo {
        max-width: 150px;
        height: auto;
        margin-bottom: 1.5rem;
        animation: pulseLogo 1.5s infinite ease-in-out;
    }

    @keyframes pulseLogo {
        0% {
            transform: scale(0.98);
            opacity: 0.8;
        }

        50% {
            transform: scale(1.05);
            opacity: 1;
        }

        100% {
            transform: scale(0.98);
            opacity: 0.8;
        }
    }

    .preloader-progress-container {
        width: 220px;
        height: 6px;
        background-color: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }

    .preloader-progress-bar {
        width: 0%;
        height: 100%;
        background: var(--gradient-primary);
        border-radius: 10px;
        transition: width 0.2s ease-out;
    }

    .preloader-text {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-gray);
    }

    /* NAVBAR / HEADER */
    .navbar {
        background-color: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(12px);
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
        padding: 0.8rem 0;
        z-index: 1030;
    }

    .navbar-brand img {
        height: 40px;
    }

    .btn-custom-primary {
        background: var(--gradient-primary);
        color: white;
        border-radius: 50rem;
        padding: 0.6rem 1.4rem;
        font-weight: 600;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(6, 182, 212, 0.25);
    }

    .btn-custom-primary:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(147, 51, 234, 0.35);
    }

    /* HERO */
    .hero {
        position: relative;
        padding-top: 80px;
        padding-bottom: 80px;
        background: linear-gradient(135deg, #0F172A 0%, #2A388F 60%, #1e2660 100%);
        color: white;
        overflow: hidden;
    }

    .hero::before {
        content: "";
        position: absolute;
        width: 450px;
        height: 450px;
        background: radial-gradient(circle, rgba(0, 174, 239, 0.25) 0%, rgba(0, 0, 0, 0) 70%);
        top: -100px;
        right: -100px;
        border-radius: 50%;
    }

    .hero h1 {
        font-size: clamp(2.2rem, 5vw, 3.5rem);
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    /* SEARCH BAR */
    .search-container {
        max-width: 650px;
        margin: -32px auto 40px auto;
        position: relative;
        z-index: 10;
    }

    .search-bar {
        background: #ffffff;
        border-radius: 50px;
        padding: 6px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
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
        gap: 6px;
        background: var(--brand-bg-light);
        padding: 5px 14px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-gray);
        border: 1px solid #e2e8f0;
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

    <!-- PRELOADER -->
    <div id="preloader">
        <img src="img/logo.png" alt="Logo Kelasi" class="preloader-logo">
        <div class="preloader-progress-container">
            <div class="preloader-progress-bar" id="preloaderBar"></div>
        </div>
        <div class="preloader-text" id="preloaderText">Chargement... 0%</div>
    </div>

    <!-- NAVBAR HEADER -->
    <nav class="navbar navbar-expand-lg fixed-top" aria-label="Menu principal">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="img/logo.png" alt="Logo Kelasi">
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false"
                aria-label="Basculer la navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link fw-semibold text-dark px-3" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item"><a class="nav-link fw-semibold text-dark px-3"
                            href="index.php#features">Fonctionnalités</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold text-dark px-3"
                            href="index.php#contact">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 ms-lg-3">
                    <a href="login.php"
                        class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold text-dark border-0">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
                    </a>
                    <a href="register.php" class="btn btn-custom-primary">
                        <i class="bi bi-person-plus me-1"></i> S'inscrire
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero text-center position-relative">
        <div class="container position-relative" style="z-index: 2;">
            <div
                class="badge bg-white bg-opacity-10 text-cyan rounded-pill px-3 py-2 mb-3 border border-white border-opacity-20">
                <i class="fas fa-school text-warning me-1"></i> Écosystème Kelasi .Education
            </div>
            <h1>Nos Écoles Partenaires</h1>
            <p class="lead opacity-85 mx-auto max-w-600 mb-0">Découvrez les établissements affiliés au réseau Kelasi et
                accédez directement à leurs portails digitaux.</p>
        </div>
    </section>

    <!-- BARRE DE RECHERCHE -->
    <div class="container search-container">
        <div class="search-bar input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="searchInput" class="form-control"
                placeholder="Rechercher par nom d'école, ville ou pays...">
        </div>
    </div>

    <!-- LISTE DES ÉCOLES -->
    <section class="container-fluid mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-building-columns text-brand-blue me-2"></i>
                Établissements répertoriés</h4>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold" id="schoolCount">
                <?= $total_ecoles ?> école(s)
            </span>
        </div>

        <div class="row g-4" id="ecolesContainer">
            <?php if (!empty($ecoles)): ?>
            <?php foreach ($ecoles as $ecole): ?>
            <div class="col-lg-3 col-md-6 ecole-card">
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
            <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-school-circle-xmark display-3 text-muted mb-3"></i>
                <h5 class="fw-bold text-dark">Aucune école approuvée pour le moment.</h5>
                <p class="text-muted">Revenez plus tard ou contactez notre support pour plus d'informations.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- MESSAGE AUCUN RÉSULTAT RECHERCHE -->
        <div id="noResults" class="text-center py-5 d-none">
            <i class="fas fa-magnifying-glass display-4 text-muted mb-3"></i>
            <h5 class="fw-bold text-dark">Aucun établissement trouvé</h5>
            <p class="text-muted">Essayez de modifier votre terme de recherche.</p>
        </div>
    </section>

    <!-- SECTION CTA : CRÉEZ VOTRE ÉCOLE -->
    <section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white;">
        <div class="container py-3 text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <span class="badge bg-info bg-opacity-20 text-white fw-bold px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i> Rejoignez le réseau
                    </span>
                    <h3 class="fw-bold mb-3">Votre établissement n'est pas encore répertorié ?</h3>
                    <p class="text-secondary mb-4" style="color: #94a3b8 !important;">
                        Bénéficiez de la puissance de la plateforme Kelasi pour gérer vos frais de scolarité,
                        inscriptions, bulletins et communications en toute simplicité.
                    </p>
                    <a href="register.php" class="btn btn-custom-primary btn-lg px-5 py-3 fs-6">
                        <i class="bi bi-plus-circle-fill me-2"></i> Inscrire mon école maintenant
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT / SUPPORT -->
    <section id="contact" class="section-padding bg-light">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="text-brand-blue fw-bold text-uppercase small"><i class="fas fa-headset me-1"></i>
                        Assistance</span>
                    <h2 class="section-title mt-1 mb-4 fw-bold text-dark">Besoin d'un accompagnement ?</h2>
                    <p class="text-muted mb-4">Notre équipe d'experts est à votre disposition pour vous effectuer une
                        démonstration personnalisée ou répondre à vos questions.</p>
                    <div class="d-flex align-items-center mb-3">
                        <div class="feature-icon-wrapper bg-white shadow-sm text-brand-blue mb-0 me-3"
                            style="width: 48px; height: 48px;">
                            <i class="fas fa-envelope fs-6"></i>
                        </div>
                        <div>
                            <small class="text-muted d-block">Adresse e-mail</small>
                            <span class="fw-semibold text-dark">contact@kelasi.education</span>
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
                        <form id="demoForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Nom complet</label>
                                    <input type="text" class="form-control" placeholder="Mehdou Mbula" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">E-mail
                                        professionnel</label>
                                    <input type="email" class="form-control" placeholder="contact@kelasi.education" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Nom de
                                        l'établissement</label>
                                    <input type="text" class="form-control" placeholder="Ex: CS Raynée International School" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Téléphone /
                                        WhatsApp</label>
                                    <input type="tel" class="form-control" placeholder="+243 000 000 000" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-secondary">Message</label>
                                    <textarea class="form-control" rows="3"
                                        placeholder="Décrivez brièvement les besoins de votre école..."></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-custom-primary w-100 py-3">Envoyer ma demande
                                        de démo</button>
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
                    <p class="small text-white mb-4">Plateforme digitale dédiée à la modernisation de l'écosystème
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
                        <li class="mb-2"><a href="index.php">Accueil</a></li>
                        <li class="mb-2"><a href="index.php#features">Fonctionnalités</a></li>
                        <li class="mb-2"><a href="nos_ecoles.php">Écoles</a></li>
                        <li class="mb-2"><a href="index.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>Accès Direct</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="login.php">Connexion</a></li>
                        <li class="mb-2"><a href="register.php">Inscription</a></li>
                        <li class="mb-2"><a href="#contact">Support</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5>Information</h5>
                    <p class="small mb-1"><i class="fas fa-location-dot me-2"></i>Kinshasa, République
                        Démocratique du Congo</p>
                    <p class="small text-white"><i class="fas fa-envelope me-2"></i>contact@kelasi.education</p>
                </div>
            </div>
            <div class="border-top border-secondary pt-4 text-center small">
                <p class="mb-0">&copy; <?= date('Y') ?> Kelasi .Education. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // 1. GESTION DU PRELOADER
        const preloader = document.getElementById('preloader');
        const preloaderBar = document.getElementById('preloaderBar');
        const preloaderText = document.getElementById('preloaderText');
        let progress = 0;

        const progressInterval = setInterval(() => {
            progress += Math.floor(Math.random() * 12) + 5;
            if (progress >= 100) {
                progress = 100;
                clearInterval(progressInterval);
                preloaderBar.style.width = '100%';
                preloaderText.innerText = 'Chargement... 100%';

                setTimeout(() => {
                    preloader.style.opacity = '0';
                    preloader.style.visibility = 'hidden';
                }, 300);
            } else {
                preloaderBar.style.width = progress + '%';
                preloaderText.innerText = `Chargement... ${progress}%`;
            }
        }, 50);

        // 2. RECHERCHE DYNAMIQUE EN TEMPS RÉEL
        const searchInput = document.getElementById('searchInput');
        const ecoleCards = document.querySelectorAll('.ecole-card');
        const noResults = document.getElementById('noResults');
        const schoolCount = document.getElementById('schoolCount');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                let visibleCount = 0;

                ecoleCards.forEach(card => {
                    const cardText = card.innerText.toLowerCase();
                    if (cardText.includes(query)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                schoolCount.innerText = `${visibleCount} école(s)`;

                if (visibleCount === 0 && ecoleCards.length > 0) {
                    noResults.classList.remove('d-none');
                } else {
                    noResults.classList.add('d-none');
                }
            });
        }

        // 3. SOUMISSION DU FORMULAIRE DE CONTACT
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const alertBox = document.getElementById('formAlert');
                alertBox.innerHTML =
                    '<div class="alert alert-success mt-2 rounded-3"><i class="fas fa-check-circle me-2"></i>Votre message a été envoyé avec succès ! Nous vous recontacterons rapidement.</div>';
                this.reset();
                setTimeout(() => alertBox.innerHTML = '', 6000);
            });
        }
    });
    </script>
</body>

</html>