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
    <title>Kelasi - Écosystème Éducatif Intelligent</title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- FontAwesome Icons (v6.5.1 CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --primary-cyan: #06b6d4;
        --primary-blue: #0284c7;
        --accent-purple: #9333ea;
        --accent-purple-light: #a855f7;
        --bg-light: #f8fafc;
        --bg-dark-section: #0f172a;
        --card-dark: #1e1b4b;
        --text-dark: #0f172a;
        --text-muted: #64748b;
        --gradient-primary: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #9333ea 100%);
        --brand-blue: #0284c7;
        --brand-cyan: #06b6d4;
        --brand-orange: #f97316;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: #ffffff;
        color: var(--text-dark);
        overflow-x: hidden;
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
        max-width: 160px;
        height: auto;
        margin-bottom: 1.5rem;
        animation: pulseLogo 1.5s infinite ease-in-out;
    }

    @keyframes pulseLogo {
        0% { transform: scale(0.98); opacity: 0.8; }
        50% { transform: scale(1.05); opacity: 1; }
        100% { transform: scale(0.98); opacity: 0.8; }
    }

    .preloader-progress-container {
        width: 240px;
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
        color: var(--text-muted);
    }

    /* GENERAL STYLES */
    .text-gradient {
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .navbar-custom {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        transition: all 0.3s ease;
        z-index: 1030;
    }

    .btn-custom-primary {
        background: var(--gradient-primary);
        color: white;
        border-radius: 50rem;
        padding: 0.65rem 1.6rem;
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

    .btn-custom-outline {
        border: 1px solid #cbd5e1;
        color: #334155;
        border-radius: 50rem;
        padding: 0.65rem 1.6rem;
        font-weight: 600;
        background: white;
        transition: all 0.3s ease;
    }

    .btn-custom-outline:hover {
        border-color: var(--accent-purple);
        color: var(--accent-purple);
        background-color: #fdf4ff;
    }

    .badge-pill-custom {
        background: rgba(6, 182, 212, 0.1);
        color: var(--primary-blue);
        border: 1px solid rgba(6, 182, 212, 0.2);
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.4rem 1.1rem;
        border-radius: 50rem;
        display: inline-block;
    }

    .card-custom {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 1.25rem;
        transition: all 0.3s ease;
    }

    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 30px rgba(147, 51, 234, 0.08);
        border-color: rgba(147, 51, 234, 0.2);
    }

    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(147, 51, 234, 0.15) 100%);
        color: var(--accent-purple);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        margin-bottom: 1rem;
    }

    /* Marquee Slider */
    .marquee-wrapper {
        overflow: hidden;
        white-space: nowrap;
        position: relative;
        padding: 1rem 0;
    }

    .marquee-wrapper::before,
    .marquee-wrapper::after {
        content: "";
        position: absolute;
        top: 0;
        width: 100px;
        height: 100%;
        z-index: 2;
        pointer-events: none;
    }

    .marquee-wrapper::before {
        left: 0;
        background: linear-gradient(to right, #f8fafc, transparent);
    }

    .marquee-wrapper::after {
        right: 0;
        background: linear-gradient(to left, #f8fafc, transparent);
    }

    .marquee-track {
        display: inline-flex;
        gap: 1.5rem;
        animation: marqueeScroll 30s linear infinite;
    }

    .marquee-wrapper:hover .marquee-track {
        animation-play-state: paused;
    }

    @keyframes marqueeScroll {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }

    .partner-badge-slide {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 50rem;
        padding: 0.5rem 1.25rem 0.5rem 0.6rem;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #334155;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        flex-shrink: 0;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .partner-badge-slide:hover {
        border-color: var(--primary-blue);
        transform: translateY(-2px);
        color: var(--primary-blue);
    }

    .school-logo-img {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    /* Counter Styles */
    .section-padding {
        padding: 5rem 0;
    }

    .counter-item-row {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1rem 1.25rem;
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    .counter-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .counter-value {
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1;
    }

    .floating-badge {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 12px 20px;
        border-radius: 50rem;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        font-size: 0.85rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10;
    }

    .ui-dashboard-mockup {
        background-color: #f1f5f9;
        font-family: 'Inter', sans-serif;
        text-align: left;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
        user-select: none;
    }

    .dash-sidebar {
        background-color: #03254c;
        color: #94a3b8;
        font-size: 0.7rem;
        padding: 8px 0;
    }

    .dash-sidebar-item {
        padding: 6px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #cbd5e1;
    }

    .dash-sidebar-item.active {
        background-color: #0284c7;
        color: #ffffff;
        font-weight: 600;
    }

    .dash-header {
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 6px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.7rem;
    }

    .dash-stat-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
    }

    .dash-stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .laptop-frame {
        border: 10px solid #1e293b;
        border-bottom-width: 14px;
        border-radius: 16px 16px 4px 4px;
        background: #1e293b;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .laptop-frame::after {
        content: "";
        display: block;
        width: 130%;
        height: 12px;
        background: #334155;
        margin-left: -15%;
        border-radius: 0 0 10px 10px;
    }

    .tablet-frame {
        border: 8px solid #0f172a;
        border-radius: 18px;
        background: #0f172a;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
        position: relative;
    }

    .phone-frame {
        border: 7px solid #0f172a;
        border-radius: 24px;
        background: #0f172a;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        position: relative;
    }

    .card-dark-mode {
        background: var(--card-dark);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 1.25rem;
        color: white;
        padding: 1.75rem;
        height: 100%;
        transition: transform 0.3s ease;
    }

    .card-dark-mode:hover {
        transform: translateY(-5px);
        border-color: var(--primary-cyan);
    }

    .icon-box-dark {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: rgba(6, 182, 212, 0.15);
        color: var(--primary-cyan);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .reveal {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .reveal.active {
        opacity: 1;
        transform: translateY(0);
    }

    .blog-img {
        height: 190px;
        object-fit: cover;
        border-top-left-radius: 1.25rem;
        border-top-right-radius: 1.25rem;
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
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="img/logo.png" alt="Logo Kelasi" style="max-height: 40px;">
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold text-secondary" style="font-size: 0.95rem;">
                    <li class="nav-item"><a class="nav-link px-3 text-dark" href="#">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#features">Fonctionnalités</a></li>
                    <!-- <li class="nav-item"><a class="nav-link px-3" href="#appareils">Interface</a></li> -->
                    <li class="nav-item"><a class="nav-link px-3" href="#espaces">Espaces</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#partenaires">Partenaires</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#blog">Actualités</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <!-- Boutons Se connecter et S'inscrire ajoutés dans la navbar -->
                    <a href="login/" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold text-dark border-0">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
                    </a>
                    <a href="chox-compte.php" class="btn btn-custom-primary">
                        <i class="bi bi-person-plus me-1"></i> S'inscrire
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- SECTION 1: HERO -->
    <section class="py-5 bg-white position-relative">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 reveal">
                    <div class="badge-pill-custom mb-3">
                        <i class="bi bi-mortarboard-fill me-1"></i> Plateforme de gestion scolaire de nouvelle génération
                    </div>
                    <h1 class="display-4 fw-extrabold text-dark tracking-tight mb-3" style="font-weight: 800;">
                        Pilotez votre établissement avec <span class="text-gradient">clarté</span> et simplicité
                    </h1>
                    <p class="text-secondary fs-6 mb-4 me-lg-4" style="line-height: 1.6;">
                        Que vous soyez une école primaire, un lycée ou une université, <strong>Kelasi .Education</strong> regroupe la gestion de vos frais de scolarité, inscriptions, bulletins et absences sur un espace unique.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-5">
                        <a href="#contact" class="btn btn-custom-primary">Demander une démo <i class="bi bi-arrow-right ms-1"></i></a>
                        <a href="#features" class="btn btn-custom-outline">Découvrir nos fonctionnalités</a>
                    </div>
                    <div class="row border-top pt-4 g-3 text-secondary" style="font-size: 0.9rem;">
                        <div class="col-auto d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-info fs-5"></i>
                            <span><strong>Plus de 500+</strong><br>écoles actives</span>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2 ms-sm-4">
                            <i class="bi bi-shield-check text-primary fs-5"></i>
                            <span><strong>99.9%</strong><br>disponibilité</span>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2 ms-sm-4">
                            <i class="bi bi-headset fs-5" style="color: var(--accent-purple);"></i>
                            <span><strong>Support</strong><br>24/7 dédié</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 reveal">
                    <div class="position-relative">
                        <div class="laptop-frame">
                            <div class="ui-dashboard-mockup">
                                <div class="dash-header">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="fw-bold text-dark" style="font-size: 0.75rem;">Raynée International School</div>
                                        <span class="text-muted" style="font-size: 0.65rem;">kelasi.education/@raynee-inter</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="text-end">
                                            <div class="fw-bold text-dark" style="font-size: 0.65rem;">N'YESI MPOLO</div>
                                            <div class="text-muted" style="font-size: 0.55rem;">Administrateur</div>
                                        </div>
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.65rem;">N</div>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <div class="dash-sidebar" style="width: 28%;">
                                        <div class="dash-sidebar-item active"><i class="bi bi-speedometer2"></i> Tableau de bord</div>
                                        <div class="dash-sidebar-item"><i class="bi bi-door-closed"></i> Classes</div>
                                        <div class="dash-sidebar-item"><i class="bi bi-person-badge"></i> Enseignants</div>
                                        <div class="dash-sidebar-item"><i class="bi bi-people"></i> Élèves</div>
                                        <div class="dash-sidebar-item"><i class="bi bi-journal-text"></i> Nos cours</div>
                                        <div class="dash-sidebar-item"><i class="bi bi-wallet2"></i> Finances (Scolarité)</div>
                                    </div>
                                    <div class="p-3" style="width: 72%;">
                                        <div class="fw-bold text-dark mb-1" style="font-size: 0.8rem;">TABLEAU DE BORD !</div>
                                        <div class="text-muted mb-3" style="font-size: 0.6rem;">Bienvenue à l'école : <strong>Raynée International School</strong></div>
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <div class="dash-stat-card">
                                                    <div class="dash-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
                                                    <div>
                                                        <div class="text-muted" style="font-size: 0.55rem;">Élèves</div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">46</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="dash-stat-card">
                                                    <div class="dash-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-workspace"></i></div>
                                                    <div>
                                                        <div class="text-muted" style="font-size: 0.55rem;">Enseignants</div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">31</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="dash-stat-card">
                                                    <div class="dash-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-easel"></i></div>
                                                    <div>
                                                        <div class="text-muted" style="font-size: 0.55rem;">Classes</div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">59</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="dash-stat-card">
                                                    <div class="dash-stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash"></i></div>
                                                    <div>
                                                        <div class="text-muted" style="font-size: 0.55rem;">Finances</div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">$ 4,078.00</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="floating-badge">
                            <i class="bi bi-patch-check-fill text-success fs-4"></i>
                            <span>Tableau de bord en temps réel <br><small class="text-muted fw-normal">Raynée International School</small></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 2: PARTNER LOGOS SLIDER -->
    <section id="partenaires" class="py-4 bg-custom-light border-top border-bottom">
        <div class="container mb-3 text-center reveal">
            <span class="text-uppercase small fw-bold text-muted tracking-wider d-block mb-3">Adopté par plus de 500+ grands établissements scolaires</span>
            <a href="nos_ecole.php" class="btn btn-primary rounded-pill px-4 fw-bold">
                Voir toutes les écoles <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="marquee-wrapper">
            <div class="marquee-track">
                <?php 
                for ($i = 0; $i < 2; $i++): 
                    foreach ($ecoles as $ecole): 
                        $logo_path = !empty($ecole['logo']) ? 'uploads/logo/' . htmlspecialchars($ecole['logo']) : 'img/symbole-kelasi.png';
                ?>
                <a href="nos_ecoles/home/?ecole=<?= urlencode($ecole['url_ecole']); ?>" class="partner-badge-slide">
                    <img src="<?= $logo_path ?>" alt="Logo <?= htmlspecialchars($ecole['nom_ecole']) ?>" class="school-logo-img">
                    <span><?= htmlspecialchars($ecole['nom_ecole']) ?></span>
                </a>
                <?php 
                    endforeach; 
                endfor; 
                ?>
            </div>
        </div>
    </section>

    <!-- SECTION STATS & IMPACT -->
    <section id="stats" class="section-padding bg-white">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 reveal">
                    <span class="text-brand-blue fw-bold text-uppercase small tracking-wider">Notre Impact</span>
                    <h2 class="fw-bold mt-1 mb-4">Une plateforme adoptée par de grands établissements</h2>
                    <p class="text-muted mb-4">Grâce à notre infrastructure fiable et sécurisée, nous accompagnons chaque jour les acteurs majeurs de l'éducation vers la réussite digitale.</p>

                    <div class="d-flex flex-column gap-3">
                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-primary-subtle text-brand-blue">
                                <i class="fas fa-school"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-blue counter" data-target="<?= $total_ecoles ?>">0</div>
                                <h6 class="fw-bold text-dark mb-0">Établissements Partenaires</h6>
                            </div>
                        </div>

                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-info-subtle text-brand-cyan">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-cyan counter" data-target="<?= $total_eleves ?>">0</div>
                                <h6 class="fw-bold text-dark mb-0">Élèves Accompagnés</h6>
                            </div>
                        </div>

                        <div class="counter-item-row">
                            <div class="counter-icon-box bg-warning-subtle text-brand-orange">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="counter-value text-brand-orange counter" data-target="<?= $total_profs ?>">0</div>
                                <h6 class="fw-bold text-dark mb-0">Enseignants Connectés</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 reveal">
                    <div class="position-relative">
                        <div class="card card-custom p-2 border-0 shadow-lg overflow-hidden">
                            <img src="https://images.pexels.com/photos/11025024/pexels-photo-11025024.jpeg" class="img-fluid rounded-4" alt="Étudiants et technologie éducative">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 3: FEATURES GRID -->
    <section id="features" class="py-5 bg-custom-light">
        <div class="container py-4">
            <div class="text-center max-w-xl mx-auto mb-5 reveal">
                <span class="fw-bold text-uppercase fs-7 tracking-wider" style="color: var(--accent-purple);">SOLUTIONS & SERVICES</span>
                <h2 class="fw-bold mt-2 text-dark">La plateforme tout-en-un de gestion scolaire</h2>
                <p class="text-secondary small">De la gestion financière au suivi pédagogique, tous vos besoins regroupés en une seule solution moderne.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-cash-stack"></i></div>
                        <h5 class="fw-bold mb-2">Administration & finances</h5>
                        <p class="text-secondary small mb-0">Suivez les paiements des frais de scolarité, gérez la trésorerie et générez les reçus bancaires automatiquement.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-person-check"></i></div>
                        <h5 class="fw-bold mb-2">Présences & retards en temps réel</h5>
                        <p class="text-secondary small mb-0">Un suivi instantané des absences pour informer immédiatement les parents d'élèves par SMS ou notifications.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-journal-bookmark"></i></div>
                        <h5 class="fw-bold mb-2">Cahier de texte & devoirs en ligne</h5>
                        <p class="text-secondary small mb-0">Partage de cours, devoirs à domicile et ressources pédagogiques pour une continuité des apprentissages partout.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-people"></i></div>
                        <h5 class="fw-bold mb-2">Portail dédié parents & élèves</h5>
                        <p class="text-secondary small mb-0">Accès direct aux notes, bulletins, devoirs, calendrier et messagerie sécurisée avec l'équipe enseignante.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-award"></i></div>
                        <h5 class="fw-bold mb-2">Notes, évaluations & bulletins</h5>
                        <p class="text-secondary small mb-0">Calcul automatique des moyennes, génération des bulletins scolaires officiels et relevés de notes imprimables.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card card-custom p-4 h-100">
                        <div class="icon-box"><i class="bi bi-calendar3"></i></div>
                        <h5 class="fw-bold mb-2">Emploi du temps interactif</h5>
                        <p class="text-secondary small mb-0">Planification optimisée des salles et des cours avec alerte immédiate lors des changements d'horaires.</p>
                    </div>
                </div>
                <div class="col-12 reveal">
                    <div class="card card-custom p-4 text-center bg-white">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                            <div class="icon-box mb-0 me-2" style="width: 38px; height: 38px; font-size: 1.1rem;"><i class="bi bi-layers"></i></div>
                            <h5 class="fw-bold mb-0">Gestion de la vie scolaire globale</h5>
                        </div>
                        <p class="text-secondary small mb-0 max-w-2xl mx-auto">Offrez à votre établissement un environnement numérique moderne, collaboratif et totalement sécurisé.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NOUVELLE SECTION : CRÉEZ MAINTENANT VOTRE ÉCOLE -->
    <section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white;">
        <div class="container py-4 text-center reveal">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <span class="badge bg-info bg-opacity-20 text-info fw-bold px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i> Lancez votre établissement en ligne
                    </span>
                    <h2 class="display-5 fw-bold mb-3">Créez maintenant votre école sur Kelasi</h2>
                    <p class="text-secondary mb-4" style="color: #94a3b8 !important;">
                        Digitalisez l'administration, facilitez le suivi des élèves et connectez vos enseignants en quelques minutes. Rejoignez notre réseau d'excellence dès aujourd'hui.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="register.php" class="btn btn-custom-primary btn-lg px-5 py-3">
                            <i class="bi bi-plus-circle-fill me-2"></i> Créer mon école
                        </a>
                        <a href="#contact" class="btn btn-outline-light rounded-pill px-5 py-3 fw-semibold">
                            En savoir plus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 4: DEVICE ACCESSIBILITY -->
    <section id="appareils" class="py-5 bg-white">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="fw-bold text-uppercase fs-7" style="color: var(--primary-cyan);">ACCÈS PARTOUT</span>
                <h2 class="fw-bold mt-2">Kelasi sur tous vos appareils</h2>
                <p class="text-secondary small">Visualisez l'interface fluide de gestion Kelasi adaptée aux ordinateurs, tablettes et smartphones.</p>
            </div>

            <div class="row align-items-center justify-content-center g-4">
                <div class="col-lg-6 reveal">
                    <div class="laptop-frame">
                        <div class="ui-dashboard-mockup">
                            <div class="dash-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-bold text-dark" style="font-size: 0.7rem;">Raynée International School</div>
                                    <span class="text-muted" style="font-size: 0.6rem;">kelasi.education/@raynee-international</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-dark border" style="font-size:0.55rem;">Administrateur</span>
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 0.6rem;">N</div>
                                </div>
                            </div>
                            <div class="d-flex" style="min-height:220px;">
                                <div class="dash-sidebar" style="width: 30%;">
                                    <div class="dash-sidebar-item active"><i class="bi bi-speedometer2"></i> Tableau de bord</div>
                                    <div class="dash-sidebar-item"><i class="bi bi-door-closed"></i> Classes</div>
                                    <div class="dash-sidebar-item"><i class="bi bi-person-badge"></i> Enseignants</div>
                                    <div class="dash-sidebar-item"><i class="bi bi-people"></i> Élèves</div>
                                    <div class="dash-sidebar-item"><i class="bi bi-wallet2"></i> Finances</div>
                                </div>
                                <div class="p-2" style="width: 70%;">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 0.75rem;">TABLEAU DE BORD !</div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <div class="dash-stat-card">
                                                <div class="dash-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
                                                <div>
                                                    <div class="text-muted" style="font-size: 0.5rem;">Élèves</div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">46</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="dash-stat-card">
                                                <div class="dash-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-workspace"></i></div>
                                                <div>
                                                    <div class="text-muted" style="font-size: 0.5rem;">Enseignants</div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">31</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="dash-stat-card">
                                                <div class="dash-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-easel"></i></div>
                                                <div>
                                                    <div class="text-muted" style="font-size: 0.5rem;">Classes</div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">59</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="dash-stat-card">
                                                <div class="dash-stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash"></i></div>
                                                <div>
                                                    <div class="text-muted" style="font-size: 0.5rem;">Finances</div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">$ 4,078.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-white p-2 rounded shadow-sm" style="font-size: 0.55rem;">
                                        <div class="d-flex justify-content-between text-muted border-bottom pb-1 mb-1">
                                            <span>Inscription</span><span>Minerval</span><span>Total</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold text-dark">
                                            <span>$ 1,568.00</span><span>$ 2,330.00</span><span class="text-primary">$ 4,078.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3 text-muted small fw-medium">Vue Ordinateur (Dashboard complet)</div>
                </div>

                <div class="col-md-6 col-lg-3 reveal">
                    <div class="tablet-frame p-1">
                        <div class="ui-dashboard-mockup p-2" style="min-height: 240px;">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                <div class="fw-bold text-dark" style="font-size: 0.65rem;">Raynée School</div>
                                <i class="bi bi-list fs-6 text-dark"></i>
                            </div>
                            <div class="fw-bold text-dark mb-2" style="font-size: 0.65rem;">Vue Synthèse</div>
                            <div class="dash-stat-card mb-2">
                                <div class="dash-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
                                <div>
                                    <div class="text-muted" style="font-size: 0.5rem;">Élèves inscrits</div>
                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">46</div>
                                </div>
                            </div>
                            <div class="dash-stat-card mb-2">
                                <div class="dash-stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash"></i></div>
                                <div>
                                    <div class="text-muted" style="font-size: 0.5rem;">Finances totales</div>
                                    <div class="fw-bold text-dark" style="font-size: 0.75rem;">$ 4,078.00</div>
                                </div>
                            </div>
                            <div class="bg-white p-2 rounded border" style="font-size: 0.55rem;">
                                <div class="fw-semibold text-dark mb-1">Paiements Récents</div>
                                <div class="text-success">Minerval: $ 2,330.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3 text-muted small fw-medium">Vue Tablette</div>
                </div>

                <div class="col-md-6 col-lg-3 reveal">
                    <div class="phone-frame p-1">
                        <div class="ui-dashboard-mockup p-2" style="min-height: 240px; border-radius: 18px;">
                            <div class="bg-dark rounded-pill mx-auto mb-2" style="width: 40px; height: 4px;"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-primary" style="font-size: 0.65rem;">Kelasi App</span>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:16px; height:16px; font-size:0.5rem;">N</div>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm mb-2" style="font-size:0.6rem;">
                                <div class="text-muted" style="font-size:0.5rem;">Raynée Int. School</div>
                                <div class="fw-bold text-dark">Encaissements</div>
                                <div class="fs-6 fw-bold text-primary">$ 4,078.00</div>
                            </div>
                            <div class="d-flex gap-1 mb-2">
                                <div class="bg-light p-1 rounded w-50 text-center" style="font-size: 0.5rem;">
                                    <div class="text-muted">Élèves</div>
                                    <div class="fw-bold text-dark">46</div>
                                </div>
                                <div class="bg-light p-1 rounded w-50 text-center" style="font-size: 0.5rem;">
                                    <div class="text-muted">Classes</div>
                                    <div class="fw-bold text-dark">59</div>
                                </div>
                            </div>
                            <div class="btn btn-primary btn-sm w-100 py-1" style="font-size: 0.55rem; border-radius: 20px;">Accéder au menu</div>
                        </div>
                    </div>
                    <div class="text-center mt-3 text-muted small fw-medium">Vue Mobile / Parents</div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 5: USER ROLES -->
    <section id="espaces" class="py-5 bg-dark text-white">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="text-info fw-bold text-uppercase fs-7">ESPACES ADAPTÉS</span>
                <h2 class="fw-bold mt-2">Chacun son espace, chacun ses outils</h2>
                <p class="text-secondary small">Chaque profil d'utilisateur bénéficie d'un portail adapté à ses missions.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3 reveal">
                    <div class="card-dark-mode">
                        <div class="icon-box-dark"><i class="bi bi-person-video3"></i></div>
                        <h5 class="fw-bold fs-6">Enseignants</h5>
                        <p class="text-secondary small mb-0">Saisie simplifiée des notes, suivi des présences, cahier de texte et publication de cours.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 reveal">
                    <div class="card-dark-mode">
                        <div class="icon-box-dark"><i class="bi bi-building-gear"></i></div>
                        <h5 class="fw-bold fs-6">Administrateurs</h5>
                        <p class="text-secondary small mb-0">Gestion comptable, inscription, statistiques financières et pilotage d'établissement.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 reveal">
                    <div class="card-dark-mode">
                        <div class="icon-box-dark"><i class="bi bi-heart-pulse"></i></div>
                        <h5 class="fw-bold fs-6">Parents</h5>
                        <p class="text-secondary small mb-0">Suivi en temps réel des notes, paiements scolaires, devoirs et contact direct avec l'école.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 reveal">
                    <div class="card-dark-mode">
                        <div class="icon-box-dark"><i class="bi bi-mortarboard"></i></div>
                        <h5 class="fw-bold fs-6">Élèves</h5>
                        <p class="text-secondary small mb-0">Consultation des emplois du temps, remise des devoirs et consultation des relevés de notes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 6: TESTIMONIALS -->
    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="fw-bold text-uppercase fs-7" style="color: var(--accent-purple);">RETOURS D'EXPÉRIENCE</span>
                <h2 class="fw-bold mt-2">Ce qu'en disent nos utilisateurs</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-4 reveal">
                    <div class="card card-custom p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="fs-3 mb-2" style="color: var(--accent-purple);"><i class="bi bi-quote"></i></div>
                            <p class="text-secondary small mb-4">"Depuis que nous utilisons Kelasi .Education, la gestion de nos frais scolaires est devenue fluide. Les reçus numériques et les rappels automatiques nous font gagner un temps précieux."</p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top">
                            <div class="avatar-initial" style="width:36px; height:36px; border-radius:50%; background:var(--gradient-primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.85rem;">AM</div>
                            <div>
                                <div class="fw-bold small">Antoine M.</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Directeur des Études</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="card card-custom p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="fs-3 mb-2" style="color: var(--primary-cyan);"><i class="bi bi-quote"></i></div>
                            <p class="text-secondary small mb-4">"La création des bulletins trimestriels me prenait des semaines. Avec Kelasi, tout est calculé automatiquement en un clic avec une présentation impeccable."</p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top">
                            <div class="avatar-initial" style="width:36px; height:36px; border-radius:50%; background:#06b6d4; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.85rem;">PL</div>
                            <div>
                                <div class="fw-bold small">Paul L.</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Enseignant Principal</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="card card-custom p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="fs-3 mb-2" style="color: var(--accent-purple);"><i class="bi bi-quote"></i></div>
                            <p class="text-secondary small mb-4">"L'application mobile parent est parfaite ! Je reçois une alerte immédiate si mon fils a un devoir ou une absence. Je recommande chaleureusement !"</p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top">
                            <div class="avatar-initial" style="width:36px; height:36px; border-radius:50%; background:#a855f7; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.85rem;">SK</div>
                            <div>
                                <div class="fw-bold small">Sarah K.</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Parente d'élève</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 7: CONTACT / FORM -->
    <section id="contact" class="py-5 bg-custom-light">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 reveal">
                    <span class="fw-bold text-uppercase fs-7" style="color: var(--primary-blue);">CONTACT & DÉMONSTRATION</span>
                    <h2 class="fw-bold mt-2 display-6">Prêt à digitaliser votre établissement ?</h2>
                    <p class="text-secondary small my-3">Rejoignez plus de 500 écoles partenaires qui simplifient leur gestion au quotidien grâce à Kelasi .Education.</p>

                    <div class="d-flex align-items-center gap-3 p-3 bg-white rounded-3 shadow-sm mt-4 border border-light">
                        <div class="icon-box mb-0"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="text-muted small">Écrivez-nous directement</div>
                            <div class="fw-bold text-dark">contact@kelasi.education</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 reveal">
                    <div class="card card-custom p-4 p-md-5 bg-white shadow-sm">
                        <h4 class="fw-bold mb-4">Demander une démo gratuite</h4>
                        <form id="demoForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Nom complet</label>
                                    <input type="text" class="form-control" placeholder="Mehdou Mbula" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">E-mail professionnel</label>
                                    <input type="email" class="form-control" placeholder="contact@kelasi.education" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Nom de l'établissement</label>
                                    <input type="text" class="form-control" placeholder="Ex: CS Raynée International School" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Téléphone / WhatsApp</label>
                                    <input type="tel" class="form-control" placeholder="+243 000 000 000" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-secondary">Message</label>
                                    <textarea class="form-control" rows="3" placeholder="Décrivez brièvement les besoins de votre école..."></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-custom-primary w-100 py-3">Envoyer ma demande de démo</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 8: NEWS / BLOG -->
    <section id="blog" class="py-5 bg-white">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="fw-bold text-uppercase fs-7" style="color: var(--primary-cyan);">ACTUALITÉS & CONSEILS</span>
                <h2 class="fw-bold mt-2">Blog & Partage d'expériences</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-4 reveal">
                    <div class="card card-custom h-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=80" alt="Blog 1" class="blog-img">
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <h6 class="fw-bold text-dark mb-2">Comment optimiser la gestion des frais de scolarité dans votre école ?</h6>
                                <p class="text-secondary small">Découvrez nos méthodes pour limiter les retards de paiements et automatiser les relances.</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                <span class="badge bg-light text-primary small">Comptabilité</span>
                                <a href="#" class="text-custom-primary text-decoration-none small fw-bold" style="color: var(--accent-purple);">Lire la suite <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="card card-custom h-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=600&q=80" alt="Blog 2" class="blog-img">
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <h6 class="fw-bold text-dark mb-2">Pourquoi migrer vers un cahier de texte en ligne dès cette rentrée ?</h6>
                                <p class="text-secondary small">Favorisez l'implication des parents d'élèves en leur donnant accès aux devoirs quotidiens.</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                <span class="badge bg-light text-info small">Innovation</span>
                                <a href="#" class="text-custom-primary text-decoration-none small fw-bold" style="color: var(--accent-purple);">Lire la suite <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="card card-custom h-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=600&q=80" alt="Blog 3" class="blog-img">
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <h6 class="fw-bold text-dark mb-2">Générez vos bulletins scolaires en un clic grâce à l'automatisation</h6>
                                <p class="text-secondary small">Fini le calcul manuel des moyennes lors des conseils de classe de fin de période.</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                <span class="badge bg-light text-purple small" style="color: var(--accent-purple);">Bulletins</span>
                                <a href="#" class="text-custom-primary text-decoration-none small fw-bold" style="color: var(--accent-purple);">Lire la suite <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-dark text-white pt-5 pb-4">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-lg-4">
                    <div class="mb-3">
                        <a href="#"><img src="img/logo.png" alt="Logo Kelasi" style="max-height: 40px;"></a>
                    </div>
                    <p class="text-secondary small me-lg-4">
                        La solution logicielle globale pour la gestion administrative, comptable et pédagogique des écoles et instituts modernes.
                    </p>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Navigation</h6>
                    <ul class="list-unstyled text-secondary small d-flex flex-column gap-2">
                        <li><a href="#" class="text-secondary text-decoration-none">Accueil</a></li>
                        <li><a href="#features" class="text-secondary text-decoration-none">Fonctionnalités</a></li>
                        <li><a href="#espaces" class="text-secondary text-decoration-none">Espaces</a></li>
                        <li><a href="#partenaires" class="text-secondary text-decoration-none">Partenaires</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Ressources</h6>
                    <ul class="list-unstyled text-secondary small d-flex flex-column gap-2">
                        <li><a href="#" class="text-secondary text-decoration-none">Guide d'utilisation</a></li>
                        <li><a href="#blog" class="text-secondary text-decoration-none">Blog & Conseils</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Assistance 24/7</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Confidentialité</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-4">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Contactez-nous</h6>
                    <p class="text-secondary small mb-2"><i class="bi bi-geo-alt me-2"></i> Kinshasa, République Démocratique du Congo</p>
                    <p class="text-secondary small mb-3"><i class="bi bi-envelope me-2"></i> contact@kelasi.education</p>
                    <a href="#contact" class="btn btn-sm btn-custom-primary rounded-pill px-4">Demander une démonstration</a>
                </div>
            </div>

            <div class="border-top border-secondary pt-3 text-center text-secondary small"
                style="border-color: rgba(255,255,255,0.1) !important;">
                &copy; 2026 Kelasi .Education. Tous droits réservés.
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Preloader, Scroll Reveal & Counter Animation JS -->
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
        }, 60);

        // 2. SCROLL REVEAL OBSERVER
        const reveals = document.querySelectorAll('.reveal');
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.12
        };
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, observerOptions);
        reveals.forEach(el => revealObserver.observe(el));

        // 3. ANIMATED COUNTERS FOR STATS SECTION
        const counters = document.querySelectorAll('.counter');
        let animated = false;
        const statsSection = document.getElementById('stats');

        function startCounters() {
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const duration = 1800; // ms
                const step = Math.ceil(target / (duration / 20));
                let count = 0;

                const updateCount = () => {
                    count += step;
                    if (count < target) {
                        counter.innerText = count.toLocaleString();
                        setTimeout(updateCount, 20);
                    } else {
                        counter.innerText = target.toLocaleString();
                    }
                };
                updateCount();
            });
        }

        if (statsSection) {
            const statsObserver = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !animated) {
                    animated = true;
                    startCounters();
                }
            }, {
                threshold: 0.3
            });
            statsObserver.observe(statsSection);
        }

        // 4. FORM SUBMIT ALERT HANDLING
        const demoForm = document.getElementById('demoForm');
        if (demoForm) {
            demoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formContainer = this.parentElement;
                const alertMsg = document.createElement('div');
                alertMsg.className = 'alert alert-success mt-3 rounded-3';
                alertMsg.innerHTML =
                    '<i class="bi bi-check-circle-fill me-2"></i> Merci ! Votre demande a bien été enregistrée. Notre équipe vous recontactera sous 24h.';
                this.reset();
                formContainer.appendChild(alertMsg);
                setTimeout(() => alertMsg.remove(), 6000);
            });
        }
    });
    </script>
</body>

</html>