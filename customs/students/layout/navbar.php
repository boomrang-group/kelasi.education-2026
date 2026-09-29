<?php
// customs/students/layout/navbar.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'functions.php';
require_once '../../../database/db_connect.php'; // ajuste le chemin si besoin
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }


function slugify(string $txt): string {
    $txt = trim(mb_strtolower($txt, 'UTF-8'));
    $txt = preg_replace('~[^\pL\d]+~u', '-', $txt);         // espaces -> tirets
    $txt = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt); // enlever accents
    $txt = preg_replace('~[^-\w]+~', '', $txt);             // garder lettres/chiffres/-
    $txt = preg_replace('~-+~', '-', $txt);                 // tirets multiples
    return trim($txt, '-') ?: 'ecole';
}

// Par défaut
$nom_ecole   = 'Mon École';
$url_ecole   = '';           // slug public (sans @), ex: "lycee-msa"
$display_url = '';           // affichage complet

// Récupération par session
$code_ecole = $_SESSION['code_ecole'] ?? '';
if ($code_ecole) {
    try {
        $st = $pdo->prepare("SELECT nom, url_ecole FROM ecoles WHERE code_ecole = :ce LIMIT 1");
        $st->execute([':ce'=>$code_ecole]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['nom']))       $nom_ecole = $row['nom'];
            if (!empty($row['url_ecole'])) $url_ecole = $row['url_ecole'];
        }
    } catch (Throwable $e) { /* silencieux */ }
}

// Fallback : si pas d'url_ecole, tenter un slug à partir du nom
if (!$url_ecole && $nom_ecole) $url_ecole = slugify($nom_ecole);

// Construire l’URL d’affichage seulement si on a un slug
// if ($url_ecole) $display_url = "www.kelasi.education/@{$url_ecole}";

// Nom utilisateur
$username = $_SESSION['username'] ?? 'students';
?>
<div class="navbar navbar-expand-md header-menu-one bg-light">
    <div class="nav-bar-header-one">
        <div class="header-logo">
            <a href="index.php">
                <img src="../../../img/logo.png" alt="logo">
            </a>
        </div>
        <div class="toggle-button sidebar-toggle">
            <button type="button" class="item-link">
                <span class="btn-icon-wrap">
                    <span></span><span></span><span></span>
                </span>
            </button>
        </div>
    </div>

    <div class="d-md-none mobile-nav-bar">
        <button class="navbar-toggler pulse-animation" type="button" data-toggle="collapse" data-target="#mobile-navbar" aria-expanded="false">
            <i class="far fa-arrow-alt-circle-down"></i>
        </button>
        <button type="button" class="navbar-toggler sidebar-toggle-mobile">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="header-main-menu collapse navbar-collapse" id="mobile-navbar">
        <ul class="navbar-nav">
            <li class="navbar-item header-search-bar">
                <div class="input-group stylish-input-group">
                    <span class="mt-3">
                        <!--<?= h($nom_ecole) ?><br>-->
                        <!--<?php if ($display_url): ?>-->
                        <!--    <a href="<?= h('https://'.$display_url) ?>" target="_blank"><?= h($display_url) ?></a>-->
                        <!--<?php else: ?>-->
                        <!--    <span class="text-muted small">Espace public non configuré</span>-->
                        <!--<?php endif; ?>-->
                    </span>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav">
            <li class="navbar-item dropdown header-admin">
                <a class="navbar-nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
                    <div class="admin-title">
                        <h5 class="item-title"><?= h($username) ?></h5>
                        <span>J'suis élève</span>
                    </div>
                    <div class="admin-img">
                        <img src="../../../img/figure/admin.jpg" alt="Admin">
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <div class="item-header">
                        <h6 class="item-title"><?= h($username) ?></h6>
                    </div>
                    <div class="item-content">
                        <ul class="settings-list">
                            <li><a href="../view/my_account.php"><i class="flaticon-user"></i>Mon Profil</a></li>
                            <li><a href="../../../login/logout.php"><i class="flaticon-turn-off"></i>Log Out</a></li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</div>
