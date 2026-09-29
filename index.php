<?php
// 1. INCLUSION DE LA BASE DE DONNÉES
require 'database/db_connect.php';

// 2. LOGIQUE DE ROUTAGE (Vérification de l'URL)
$path = $_SERVER['REQUEST_URI'];
$segments = explode('/', $path);

$atSegment = null;
foreach ($segments as $seg) {
    if (strpos($seg, '@') === 0) {
        // Récupérer le nom de l'école sans le '@'
        $atSegment = substr($seg, 1);
        break;
    }
}

// 3. TRAITEMENT SI UNE ÉCOLE EST DEMANDÉE
if ($atSegment) {
    $nom = htmlspecialchars($atSegment);

    // Vérifier si l'école existe dans la base de données
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM landingpage WHERE url_ecole = ?");
    $stmt->execute([$nom]);
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        // L'école existe : Redirection vers sa page dédiée
        $url_nouveau = urlencode($nom);
        header("Location: /nos_ecoles/home/?ecole=$url_nouveau");
        exit;
    } else {
        // L'école n'existe pas : Redirection vers l'accueil principal
        header("Location: /");
        exit;
    }
}

// Récupérer les écoles approuvées (statut = 'approuver') triées par date de création
$stmt = $pdo->prepare("
    SELECT id, nom_ecole, code_ecole, url_ecole, ville, province_etat, pays, logo, telephone1, telephone2
    FROM ecoles
    WHERE statut = 'approuver'
    ORDER BY date_creation DESC
    LIMIT 10
");
$stmt->execute();
$ecoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔢 Compter les écoles approuvées
$stmt = $pdo->query("SELECT COUNT(*) FROM ecoles WHERE statut = 'approuver'");
$total_ecoles = $stmt->fetchColumn();

// 🎓 Compter les élèves
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$total_eleves = $stmt->fetchColumn();

// 👨‍🏫 Compter les professeurs
$stmt = $pdo->query("SELECT COUNT(*) FROM teacher");
$total_profs = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Kelasi - Solution digitale intelligente pour moderniser la gestion des établissements scolaires en Afrique.">
    <title>Kelasi - Écosystème Éducatif Intelligent</title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
        crossorigin="anonymous" />

    <style>
    :root {
        /* Charte Graphique basée sur le Logo Kelasi */
        --brand-blue: #2A388F;
        /* Bleu Roi Principal */
        --brand-cyan: #00AEEF;
        /* Bleu Ciel Cyan */
        --brand-orange: #F7941D;
        /* Accentuation Orange */
        --brand-dark: #0F172A;
        /* Fond Sombre/Texte Titres */
        --brand-gray: #475569;
        /* Texte Secondaire */
        --brand-bg-light: #F8FAFC;
        /* Fond Clair */

        --card-shadow: 0 15px 30px -10px rgba(42, 56, 143, 0.08);
        --card-shadow-hover: 0 20px 35px -5px rgba(0, 174, 239, 0.2);
        --radius-lg: 20px;
        --radius-md: 14px;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--brand-gray);
        background-color: #ffffff;
        overflow-x: hidden;
    }

    /* PRELOADER STYLES */
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #ffffff;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }

    #preloader.fade-out {
        opacity: 0;
        visibility: hidden;
    }

    .preloader-logo {
        height: 65px;
        margin-bottom: 25px;
        animation: pulseLogo 1.5s ease-in-out infinite alternate;
    }

    @keyframes pulseLogo {
        0% {
            transform: scale(0.96);
            opacity: 0.85;
        }

        100% {
            transform: scale(1.05);
            opacity: 1;
        }
    }

    .preloader-progress-container {
        width: 220px;
        background-color: #e2e8f0;
        height: 6px;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }

    .preloader-progress-bar {
        width: 0%;
        height: 100%;
        background: linear-gradient(90deg, var(--brand-blue), var(--brand-cyan));
        border-radius: 10px;
        transition: width 0.2s ease-out;
    }

    .preloader-text {
        margin-top: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-blue);
        letter-spacing: 0.5px;
    }

    /* NAVBAR */
    .navbar {
        transition: all 0.3s ease;
        padding: 1.25rem 0;
    }

    .navbar.scrolled {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 4px 20px rgba(42, 56, 143, 0.08);
        padding: 0.75rem 0;
    }

    .navbar-brand img {
        height: 42px;
    }

    .nav-link {
        font-weight: 600;
        color: #ffffff;
        margin: 0 0.5rem;
        transition: color 0.2s ease;
    }

    .navbar.scrolled .nav-link {
        color: var(--brand-dark) !important;
    }

    .nav-link:hover {
        color: var(--brand-cyan) !important;
    }

    /* HERO */
    .hero {
        position: relative;
        min-height: 100vh;
        background: linear-gradient(135deg, #0f172a 0%, #2A388F 60%, #1e2660 100%);
        padding-top: 140px;
        padding-bottom: 80px;
        display: flex;
        align-items: center;
        overflow: hidden;
    }

    .hero::before {
        content: "";
        position: absolute;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(0, 174, 239, 0.25) 0%, rgba(0, 0, 0, 0) 70%);
        top: -100px;
        right: -100px;
        border-radius: 50%;
    }

    .hero-title {
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -0.02em;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 6px 16px;
        border-radius: 50px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: var(--brand-cyan);
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }

    /* BUTTONS */
    .btn-brand-primary {
        background-color: var(--brand-orange);
        color: #fff;
        border-radius: 50px;
        padding: 14px 32px;
        font-weight: 700;
        border: none;
        box-shadow: 0 10px 20px rgba(247, 148, 29, 0.3);
        transition: all 0.3s ease;
    }

    .btn-brand-primary:hover {
        background-color: #e08313;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 15px 25px rgba(247, 148, 29, 0.4);
    }

    .btn-brand-outline {
        border: 2px solid rgba(255, 255, 255, 0.4);
        color: #fff;
        border-radius: 50px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-brand-outline:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        border-color: #fff;
    }

    /* SECTIONS */
    .section-padding {
        padding: 90px 0;
    }

    .section-title {
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--brand-dark);
        letter-spacing: -0.02em;
    }

    .text-brand-blue {
        color: var(--brand-blue) !important;
    }

    .text-brand-cyan {
        color: var(--brand-cyan) !important;
    }

    .text-brand-orange {
        color: var(--brand-orange) !important;
    }

    /* CARDS */
    .card-custom {
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-lg);
        background: #ffffff;
        box-shadow: var(--card-shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-custom:hover {
        transform: translateY(-6px);
        box-shadow: var(--card-shadow-hover);
        border-color: rgba(0, 174, 239, 0.3);
    }

    /* FEATURE ICONS */
    .feature-icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1.25rem;
    }

    /* COUNTERS HORIZONTAL ROW */
    .counter-item-row {
        background: #ffffff;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .counter-item-row:hover {
        transform: translateX(6px);
        border-color: var(--brand-cyan);
    }

    .counter-icon-box {
        width: 65px;
        height: 65px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }

    .counter-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    /* SCHOOL SLIDER */
    .school-card-enhanced {
        border-radius: var(--radius-lg);
        padding: 2rem 1.5rem 1.5rem;
        text-align: center;
        background: #ffffff;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .school-card-enhanced:hover {
        border-color: var(--brand-cyan);
        transform: translateY(-6px);
        box-shadow: 0 20px 30px rgba(42, 56, 143, 0.1);
    }

    .school-card-enhanced .badge-status {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 50px;
        background: rgba(0, 174, 239, 0.1);
        color: var(--brand-blue);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .school-logo-wrapper {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        margin: 0.5rem auto 1.25rem;
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

    .school-location-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--brand-bg-light);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--brand-gray);
    }

    /* BLOG */
    .blog-card {
        overflow: hidden;
        border-radius: var(--radius-lg);
    }

    .blog-card .img-container {
        overflow: hidden;
        height: 220px;
    }

    .blog-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .blog-card:hover img {
        transform: scale(1.05);
    }

    /* TESTIMONIALS */
    .testimonial-card {
        background: #ffffff;
        border-radius: var(--radius-lg);
        padding: 2rem;
        border: 1px solid #e2e8f0;
        box-shadow: var(--card-shadow);
        position: relative;
        transition: all 0.3s ease;
    }

    .testimonial-card:hover {
        border-color: var(--brand-cyan);
    }

    /* CTA SECTION */
    .cta-banner {
        background: linear-gradient(135deg, var(--brand-blue) 0%, #1a235c 100%);
        border-radius: 28px;
        padding: 70px 40px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .cta-banner::after {
        content: "";
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 300px;
        height: 300px;
        background: rgba(0, 174, 239, 0.15);
        border-radius: 50%;
    }

    /* CONTACT FORM */
    .form-control-styled {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 14px 18px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .form-control-styled:focus {
        border-color: var(--brand-cyan);
        box-shadow: 0 0 0 4px rgba(0, 174, 239, 0.15);
        outline: none;
    }

    /* FOOTER */
    footer {
        background: var(--brand-dark);
        color: #94a3b8;
        padding-top: 80px;
        padding-bottom: 30px;
    }

    footer h5 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 1.5rem;
    }

    footer a {
        color: #94a3b8;
        text-decoration: none;
        transition: color 0.2s;
    }

    footer a:hover {
        color: var(--brand-cyan);
    }

    .social-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        transition: all 0.3s ease;
    }

    .social-icon:hover {
        background: var(--brand-cyan);
        color: #fff;
        transform: translateY(-3px);
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

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-dark" aria-label="Navigation principale">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <img src="img/logo.png" alt="Logo Kelasi">
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false" aria-label="Basculer le menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#about">Plateforme</a></li>
                    <li class="nav-item"><a class="nav-link" href="#stats">Impact</a></li>
                    <li class="nav-item"><a class="nav-link" href="#schools">Écoles</a></li>
                    <li class="nav-item"><a class="nav-link" href="#blog">Ressources</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
                        <a href="login/" class="btn btn-brand-outline btn-sm px-4">Se connecter</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center gy-5">
                <div class="col-lg-7 text-white">
                    <div class="hero-badge">
                        <i class="fas fa-sparkles"></i>
                        <span>Écosystème scolaire nouvelle génération</span>
                    </div>
                    <h1 class="hero-title mb-4">
                        Pilotez votre établissement avec intelligibilité & simplicité
                    </h1>
                    <p class="lead opacity-85 mb-5 text-light style-paragraph">
                        Kelasi centralise la scolarité, automatise le suivi scolaire et renforce le lien entre la
                        direction, les enseignants et les familles.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="chox-compte.php" class="btn btn-brand-primary">
                            Commencer gratuitement
                            <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="login/" class="btn btn-brand-outline">Se connecter</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="position-relative">
                        <div class="card card-custom p-3 bg-white text-dark shadow-lg">
                            <img src="https://images.pexels.com/photos/5905702/pexels-photo-5905702.jpeg"
                                class="img-fluid rounded-4" alt="Élèves et enseignant en classe en Afrique">
                            <div class="p-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0 text-brand-blue">Gestion 360° Unifiée</h6>
                                    <small class="text-muted">Inscriptions, notes, présence & finances</small>
                                </div>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                                    <i class="fas fa-check-circle me-1"></i> En direct
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FONCTIONNALITÉS CLÉS (ABOUT) -->
    <section id="about" class="section-padding bg-light">
        <div class="container">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-brand-cyan fw-bold text-uppercase tracking-wider small">Découvrir Kelasi</span>
                <h2 class="section-title mt-2">La plateforme tout-en-un de gestion scolaire</h2>
                <p class="text-muted mt-2">Kelasi simplifie le quotidien des établissements scolaires en réunissant la
                    gestion administrative, financière et pédagogique dans un seul espace digital.</p>
            </div>

            <div class="row g-4">
                <!-- 1. Gestion Administrative & Classes -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-primary-subtle text-brand-blue">
                            <i class="fas fa-school"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Gestion Administrative & Classes</h5>
                        <p class="text-muted mb-0">Configuration simplifiée des structures d'enseignement (maternelle,
                            primaire, secondaire), création des classes, attribution des professeurs et inscription
                            fluide des élèves.</p>
                    </div>
                </div>

                <!-- 2. Gestion des Frais & Paiements -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-success-subtle text-success">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Paiements & Suivi Financier</h5>
                        <p class="text-muted mb-0">Gestion transparente du minerval, des frais d'inscription et des
                            frais divers. Prise en charge des règlements en caisse (physiques) et validation des
                            paiements en ligne.</p>
                    </div>
                </div>

                <!-- 3. Espace E-Learning & Cours -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-info-subtle text-brand-cyan">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Espace Pédagogique Intégré</h5>
                        <p class="text-muted mb-0">Mise à disposition des leçons enrichies (PDF, images, audio, vidéos),
                            création de quiz interactifs et accès sécurisé aux cours pour les élèves à jour de leurs
                            cotisations.</p>
                    </div>
                </div>

                <!-- 4. Horaires & Présences -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-warning-subtle text-brand-orange">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Emplois du Temps & Présences</h5>
                        <p class="text-muted mb-0">Planning dynamique pour les cours, interrogations et examens.
                            Emargement et suivi précis de l'assiduité des élèves par les enseignants et la direction.
                        </p>
                    </div>
                </div>

                <!-- 5. Communication en Temps Réel -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-danger-subtle text-danger">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Communiqués & Annonces</h5>
                        <p class="text-muted mb-0">Canal de diffusion instantané pour partager les informations clés et
                            les avis officiels entre l'établissement, les enseignants, les élèves et les parents.</p>
                    </div>
                </div>

                <!-- 6. Supervisions & Multi-Espaces -->
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <div class="feature-icon-wrapper bg-secondary-subtle text-dark">
                            <i class="fas fa-users-gear"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Espaces Dédiés par Rôle</h5>
                        <p class="text-muted mb-0">Portails sur-mesure et sécurisés pour les Promoteurs (supervision
                            globale), Administrateurs (gestion courante), Professeurs (cours & notes) et Élèves.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION STATISTIQUES (2 COLONNES) -->
    <section id="stats" class="section-padding">
        <div class="container">
            <div class="row align-items-center gy-5">
                <!-- COLONNE 1 : COMPTEURS AVEC ICÔNES -->
                <div class="col-lg-6">
                    <span class="text-brand-blue fw-bold text-uppercase small tracking-wider">Notre Impact</span>
                    <h2 class="section-title mt-1 mb-4">Une plateforme adoptée par de grands établissements</h2>
                    <p class="text-muted mb-4">Grâce à notre infrastructure fiable et sécurisée, nous accompagnons
                        chaque jour les acteurs majeurs de l'éducation vers la réussite digitale.</p>

                    <div class="d-flex flex-column gap-3">
                        <!-- Établissements -->
                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-primary-subtle text-brand-blue">
                                <i class="fas fa-school"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-blue counter" data-target="<?= $total_ecoles ?>">0
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Établissements Partenaires</h6>
                            </div>
                        </div>

                        <!-- Élèves -->
                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-info-subtle text-brand-cyan">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-cyan counter" data-target="<?= $total_eleves ?>">0
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Élèves Accompagnés</h6>
                            </div>
                        </div>

                        <!-- Professeurs -->
                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-warning-subtle text-brand-orange">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-orange counter" data-target="<?= $total_profs ?>">0
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Enseignants Connectés</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLONNE 2 : IMAGE D'ILLUSTRATION -->
                <div class="col-lg-6">
                    <div class="position-relative">
                        <div class="card card-custom p-2 border-0 shadow-lg overflow-hidden">
                            <img src="https://images.pexels.com/photos/11025024/pexels-photo-11025024.jpeg"
                                class="img-fluid rounded-4" alt="Étudiants et technologie éducative">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ÉCOLES PARTENAIRES -->
    <section id="schools" class="section-padding bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <span class="text-brand-blue fw-bold text-uppercase small tracking-wider">Réseau d'excellence</span>
                    <h2 class="section-title mt-1">Nos Écoles Partenaires</h2>
                </div>
                <a href="nos_ecole.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">Voir toutes les écoles
                    <i class="fas fa-arrow-right ms-1"></i></a>
            </div>

            <div class="swiper schoolSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($ecoles as $ecole): ?>
                    <div class="swiper-slide">
                        <div class="school-card-enhanced">
                            <span class="badge-status"><i class="fas fa-shield-check me-1"></i> Partenaire</span>
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
                                    <span class="school-location-badge">
                                        <i class="fas fa-location-dot text-brand-orange"></i>
                                        <?= htmlspecialchars($ecole['ville'] ?? 'Ville') ?><?= !empty($ecole['pays']) ? ', '.$ecole['pays'] : '' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="pt-3 border-top d-flex gap-2">
                                <a href="nos_ecoles/home/?ecole=<?php echo urlencode($ecole['url_ecole']); ?>"
                                    class="btn btn-primary btn-sm flex-grow-1 rounded-pill fw-semibold"
                                    style="background-color: var(--brand-blue); border-color: var(--brand-blue);">
                                    Portail Web
                                </a>
                                <a href="nos_ecoles/home/inscription.php?ecole=<?php echo urlencode($ecole['url_ecole']); ?>"
                                    class="btn btn-outline-secondary btn-sm flex-grow-1 rounded-pill fw-semibold">
                                    S'inscrire
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination mt-4 position-relative"></div>
            </div>
        </div>
    </section>

    <!-- BLOG -->
    <section id="blog" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <span class="text-brand-cyan fw-bold text-uppercase small">Actualités & Insights</span>
                <h2 class="section-title mt-1">Dernières Publications</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card card-custom blog-card h-100">
                        <div class="img-container">
                            <img src="https://images.pexels.com/photos/5905718/pexels-photo-5905718.jpeg"
                                alt="Jeunes élèves africains étudiant ensemble">
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="fw-bold text-dark">La transformation digitale de l'enseignement en 2026</h5>
                            <p class="text-muted small flex-grow-1">Comment les nouvelles technologies simplifient le
                                travail administratif et pédagogique au quotidien.</p>
                            <!-- <a href="#" class="text-brand-blue fw-bold text-decoration-none mt-3">Lire la suite <i
                                    class="fas fa-arrow-right ms-1"></i></a> -->
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-custom blog-card h-100">
                        <div class="img-container">
                            <img src="https://images.pexels.com/photos/11025059/pexels-photo-11025059.jpeg"
                                alt="Élève travaillant sur une tablette dans une école">
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="fw-bold text-dark">Pourquoi migrer vers une plateforme intégrée ?</h5>
                            <p class="text-muted small flex-grow-1">Gain de temps, réduction des erreurs de saisie et
                                sécurisation optimale des données de votre école.</p>
                            <!-- <a href="#" class="text-brand-blue fw-bold text-decoration-none mt-3">Lire la suite <i
                                    class="fas fa-arrow-right ms-1"></i></a> -->
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-custom blog-card h-100">
                        <div class="img-container">
                            <img src="https://images.pexels.com/photos/34162709/pexels-photo-34162709.jpeg"
                                alt="Projet éducatif numérique en groupe">
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="fw-bold text-dark">Optimiser la relation entre parents et corps éducatif</h5>
                            <p class="text-muted small flex-grow-1">Les canaux de communication modernes pour assurer un
                                suivi personnalisé et engagé de l'élève.</p>
                            <!-- <a href="#" class="text-brand-blue fw-bold text-decoration-none mt-3">Lire la suite <i
                                    class="fas fa-arrow-right ms-1"></i></a> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <span class="text-brand-blue fw-bold text-uppercase small">Témoignages</span>
                <h2 class="section-title mt-1">Ce qu'en disent nos utilisateurs</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-brand-orange mb-3">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="text-muted fst-italic">"Kelasi a fondamentalement transformé la gestion
                                administrative et le contrôle financier de notre établissement. C'est un outil intuitif
                                et indispensable pour tout dirigeant moderne."</p>
                        </div>
                        <div class="mt-4 pt-3 border-top d-flex align-items-center gap-3">
                            <div class="feature-icon-wrapper bg-primary-subtle text-brand-blue mb-0"
                                style="width:48px; height:48px;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Albin MPUTU</h6>
                                <small class="text-brand-blue fw-semibold">Directeur Général</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="testimonial-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-brand-orange mb-3">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="text-muted fst-italic">"La saisie des notes et le calcul automatique des moyennes
                                me font gagner un temps précieux à chaque fin de trimestre. Mes cours sont bien mieux
                                organisés."</p>
                        </div>
                        <div class="mt-4 pt-3 border-top d-flex align-items-center gap-3">
                            <div class="feature-icon-wrapper bg-info-subtle text-brand-cyan mb-0"
                                style="width:48px; height:48px;">
                                <i class="fas fa-chalkboard-user"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Marie K.</h6>
                                <small class="text-muted">Enseignante en Sciences</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="testimonial-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-brand-orange mb-3">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="text-muted fst-italic">"Les retours des parents sont extrêmement positifs, l'accès
                                aux informations d'assiduité et aux avis importants se fait désormais en temps réel."
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-top d-flex align-items-center gap-3">
                            <div class="feature-icon-wrapper bg-warning-subtle text-brand-orange mb-0"
                                style="width:48px; height:48px;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Paul L.</h6>
                                <small class="text-muted">Responsable Pédagogique</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="section-padding">
        <div class="container">
            <div class="cta-banner text-center">
                <h2 class="display-6 fw-bold mb-3">Prêt à digitaliser votre établissement ?</h2>
                <p class="lead opacity-90 mb-4 max-w-600 mx-auto">Rejoignez la plateforme de référence et transformez la
                    gestion de votre école dès aujourd'hui.</p>
                <a href="chox-compte.php" class="btn btn-brand-primary btn-lg rounded-pill px-5 shadow-lg">Créer un
                    compte maintenant</a>
            </div>
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
                    <img src="img/logo.png" alt="Logo Kelasi">
                    <p class="small text-muted mb-4 text-white">Plateforme digitale dédiée à la modernisation de
                        l'écosystème
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
                    <p class="small text-muted text-white">Kinshasa, République Démocratique du Congo</p>
                </div>
            </div>
            <div class="border-top border-secondary pt-4 text-center small">
                <p class="mb-0">&copy; <?= date('Y') ?> Kelasi. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- JS SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" crossorigin="anonymous"></script>

    <script>
    // LOGIQUE DU PRELOADER AVEC PROGRESS BAR
    (function() {
        let progress = 0;
        const progressBar = document.getElementById('preloaderBar');
        const progressText = document.getElementById('preloaderText');
        const preloader = document.getElementById('preloader');

        const interval = setInterval(() => {
            progress += Math.floor(Math.random() * 12) + 5;
            if (progress > 90) {
                progress = 90;
                clearInterval(interval);
            }
            if (progressBar && progressText) {
                progressBar.style.width = progress + '%';
                progressText.innerText = 'Chargement... ' + progress + '%';
            }
        }, 120);

        window.addEventListener('load', function() {
            clearInterval(interval);
            if (progressBar && progressText) {
                progressBar.style.width = '100%';
                progressText.innerText = 'Chargement... 100%';
            }
            setTimeout(() => {
                if (preloader) {
                    preloader.classList.add('fade-out');
                }
            }, 300);
        });
    })();

    // FORMULAIRE DE CONTACT AJAX
    document.getElementById("contactForm").addEventListener("submit", function(e) {
        e.preventDefault();
        const form = this;
        const alertBox = document.getElementById("formAlert");

        fetch("contact_traitement.php", {
                method: "POST",
                body: new FormData(form)
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === "success") {
                    alertBox.innerHTML =
                        `<div class="alert alert-success mt-3 py-2 small">Message transmitted avec succès.</div>`;
                    form.reset();
                } else {
                    alertBox.innerHTML =
                        `<div class="alert alert-danger mt-3 py-2 small">Une erreur est survenue lors de l'envoi.</div>`;
                }
            })
            .catch(() => {
                alertBox.innerHTML =
                    `<div class="alert alert-danger mt-3 py-2 small">Erreur réseau, veuillez réessayer.</div>`;
            });
    });

    // CARROUSEL SWIPER
    const swiper = new Swiper(".schoolSwiper", {
        slidesPerView: 1,
        spaceBetween: 24,
        loop: true,
        autoplay: {
            delay: 3500,
            disableOnInteraction: false
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true
        },
        breakpoints: {
            640: {
                slidesPerView: 2
            },
            1024: {
                slidesPerView: 3
            }
        }
    });

    // EFFET DE NAVIGATION SCROLL
    window.addEventListener("scroll", function() {
        const navbar = document.querySelector(".navbar");
        navbar.classList.toggle("scrolled", window.scrollY > 40);
    });

    // COMPTEURS INTELLIGENTS
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = +counter.getAttribute('data-target');
                let count = 0;
                const speed = target / 50;

                const updateCount = () => {
                    count += speed;
                    if (count < target) {
                        counter.innerText = Math.ceil(count);
                        setTimeout(updateCount, 25);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
                observer.unobserve(counter);
            }
        });
    }, {
        threshold: 0.6
    });

    document.querySelectorAll('.counter').forEach(c => observer.observe(c));
    </script>
</body>

</html>