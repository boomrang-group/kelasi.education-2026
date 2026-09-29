<?php
// admin/fixation-frais-scolaire.php — Checkbox par classe, Tout sélectionner, CSRF, Aperçu (AJAX & Tableaux)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- DB connect ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

// --- Auth de base ---
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = strtolower((string)($_SESSION['role'] ?? ''));
$isAdmin  = in_array($role, ['admin','administrateur'], true);

// code_ecole
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId && !$codeEcole) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $codeEcole = (string)($st->fetchColumn() ?: '');
    if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
}
if (!$isAdmin || !$codeEcole) { http_response_code(403); exit('Accès refusé.'); }

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// --- Classes de CETTE école ---
$stmt = $pdo->prepare("
    SELECT c.id, c.classe, c.description AS desc_classe,
           n.description AS niveau, s.description AS section, o.description AS opt
    FROM classes c
    LEFT JOIN niveau  n ON c.niveau    = n.id
    LEFT JOIN section s ON c.section   = s.id
    LEFT JOIN options o ON c.options   = o.id
    WHERE c.code_ecole = :code
    ORDER BY n.description, s.description, o.description, c.classe, c.description
");
$stmt->execute([':code'=>$codeEcole]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper chargement des frais existants
function getFraisActuels(PDO $pdo, string $codeEcole, string $table): array {
    $sql = "
        SELECT f.*, c.classe AS nom_classe, c.description AS desc_classe,
               n.description AS nom_niveau, s.description AS nom_section, o.description AS nom_option
        FROM {$table} f
        INNER JOIN classes c ON (c.id = f.classe AND c.code_ecole = f.code_ecole)
        LEFT JOIN niveau n ON c.niveau = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE f.code_ecole = :code
        ORDER BY n.description, s.description, o.description, c.classe
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':code' => $codeEcole]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$fraisInscription = getFraisActuels($pdo, $codeEcole, 'frais_d_inscription');
$fraisMinerval    = getFraisActuels($pdo, $codeEcole, 'minerval');
$fraisAutres      = getFraisActuels($pdo, $codeEcole, 'autres_frais');

// Message simple
$msg = '';
if (!empty($_GET['msg'])) {
    $map = [
        'created'  => '✅ Enregistrement effectué.',
        'error'    => '❌ Une erreur est survenue.',
        'badclass' => '⚠️ Classe invalide ou non liée à cette école.',
        'invalid'  => '⚠️ Champs requis manquants.',
    ];
    $msg = $map[$_GET['msg']] ?? '';
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Fixation des frais</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    html {
        scroll-behavior: smooth;
    }

    .apercu-loading {
        padding: 20px;
        text-align: center;
        font-style: italic;
        opacity: .85
    }

    .nav-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
        margin-left: auto
    }

    .hint {
        color: #6b7280;
        font-size: .9rem
    }

    /* Modern Design Overhauls */
    .custom-tab-header {
        border-bottom: 2px solid #e5e7eb;
        gap: 0.5rem;
    }

    .custom-tab-header .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 600;
        color: #4b5563;
        padding: 0.75rem 1.25rem;
        transition: all 0.2s ease;
    }

    .custom-tab-header .nav-link.active {
        border-color: #ffae01;
        color: #042954;
        background: transparent;
    }

    .select-all-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }

.classes-grid {
    display: grid;
    /* Ajustez minmax(260px, 1fr) si les étiquettes passent trop sur deux lignes */
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
    gap: 0.75rem;
    max-height: 380px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

    /*.classes-grid {*/
    /*    display: grid;*/
    /*    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));*/
    /*    gap: 0.75rem;*/
    /*    max-height: 380px;*/
    /*    overflow-y: auto;*/
    /*    padding-right: 0.25rem;*/
    /*}*/

    /*.classes-grid .item {*/
    /*    border: 1px solid #e2e8f0;*/
    /*    border-radius: 0.5rem;*/
    /*    padding: 0.65rem 0.85rem;*/
    /*    background: #fff;*/
    /*    display: flex;*/
    /*    align-items: center;*/
    /*    gap: 0.65rem;*/
    /*    cursor: pointer;*/
    /*    transition: all 0.15s ease-in-out;*/
    /*    margin-bottom: 0;*/
    /*    font-size: 0.9rem;*/
    /*    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);*/
    /*}*/

.classes-grid .item {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem; /* Légèrement augmenté pour aérer le bloc */
    background: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    margin-bottom: 0;
    font-size: 1.1rem; /* 👈 Augmenté de 0.9rem à 1.1rem (ou 1.2rem selon vos préférences) */
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

    .classes-grid .item:hover {
        border-color: #cbd5e1;
        background-color: #f8fafc;
    }

.classes-grid .item input[type="checkbox"] {
    width: 1.3rem;  /* 👈 Passé de 1.1rem à 1.3rem */
    height: 1.3rem; /* 👈 Passé de 1.1rem à 1.3rem */
    cursor: pointer;
    accent-color: #042954;
}

    /*.classes-grid .item input[type="checkbox"] {*/
    /*    width: 1.1rem;*/
    /*    height: 1.1rem;*/
    /*    cursor: pointer;*/
    /*    accent-color: #042954;*/
    /*}*/

    .sidebar-form-card {
        /* background: #f8fafc; */
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1.25rem;
    }

    .frais-table-container {
        margin-top: 2.5rem;
        border-top: 2px dashed #e2e8f0;
        padding-top: 1.75rem;
    }

    /* .table-custom-header {
        background-color: #042954 !important;
        color: #ffffff;
    } */

    .soft-note {
        font-size: .85rem;
        color: #6b7280;
        display: block;
        margin-top: 0.25rem;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <?php if ($msg): ?>
                <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                    <?= h($msg) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card height-auto">
                            <div class="card-body">
                                <div class="heading-layout1 d-flex align-items-center mb-4">
                                    <div class="item-title">
                                        <h3 class="mb-1">Fixation des frais scolaires</h3>
                                        <div class="hint">Sélectionnez une ou plusieurs classes puis affectez-leur le
                                            montant correspondant.</div>
                                    </div>
                                </div>

                                <!-- Nav -->
                                <ul class="nav nav-tabs custom-tab-header" id="fraisTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="tablink-inscription" data-toggle="tab"
                                            href="#tab-inscription" role="tab" aria-controls="tab-inscription"
                                            aria-selected="true" data-type="inscription" data-form="#form-inscription">
                                            <i class="fas fa-file-signature mr-2"></i>Inscription
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tablink-minerval" data-toggle="tab" href="#tab-minerval"
                                            role="tab" aria-controls="tab-minerval" aria-selected="false"
                                            data-type="minerval" data-form="#form-minerval">
                                            <i class="fas fa-graduation-cap mr-2"></i>Minerval
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tablink-autres" data-toggle="tab" href="#tab-autres"
                                            role="tab" aria-controls="tab-autres" aria-selected="false"
                                            data-type="autres" data-form="#form-autres">
                                            <i class="fas fa-receipt mr-2"></i>Autres frais
                                        </a>
                                    </li>
                                </ul>

                                <!-- Contenu des onglets -->
                                <div class="tab-content pt-4" id="fraisTabsContent">

                                    <!-- INSCRIPTION -->
                                    <div class="tab-pane fade show active" id="tab-inscription" role="tabpanel">
                                        <form id="form-inscription" action="service/fixation-frais-scolaire.php"
                                            method="POST" autocomplete="off" novalidate>
                                            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                            <div class="row">
                                                <div class="col-xl-7 col-lg-6 form-group">
                                                    <div
                                                        class="select-all-box d-flex align-items-center justify-content-between">
                                                        <label
                                                            class="form-check-inline mb-0 font-weight-bold text-dark cursor-pointer">
                                                            <input type="checkbox" id="insc-all" class="mr-2"> Tout
                                                            sélectionner
                                                        </label>
                                                        <span
                                                            class="badge badge-pill badge-light border text-muted"><?= count($classes) ?>
                                                            classe(s)</span>
                                                    </div>
                                                    <div class="classes-grid" id="insc-classes">
                                                        <?php foreach ($classes as $c):
                                                        $lbl = trim(($c['classe'] ?? '').' '.($c['desc_classe'] ?? '').' '.($c['niveau'] ?? '').' '.($c['section'] ?? '').' '.($c['opt'] ?? '')); ?>
                                                        <label class="item">
                                                            <input type="checkbox" name="classes[]"
                                                                value="<?= (int)$c['id'] ?>">
                                                            <span><?= h($lbl ?: 'Classe #'.(int)$c['id']) ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="col-xl-5 col-lg-6">
                                                    <div class="sidebar-form-card">
                                                        <!-- <h5 class="mb-3 text-dark font-weight-bold">Paramètres du
                                                            montant</h5> -->
                                                        <div class="form-group mb-4">
                                                            <label class="font-weight-bold">Montant ($) <span
                                                                    class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" step="0.01" min="0"
                                                                    class="form-control" name="montant"
                                                                    placeholder="0.00" required>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">$</span>
                                                                </div>
                                                            </div>
                                                            <small class="soft-note">Obligatoire pour les frais
                                                                d'inscription.</small>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2 mb-1">
                                                            <button type="submit"
                                                                class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mr-2"
                                                                name="save-inscription">
                                                                <i class="fas fa-check mr-1"></i> Appliquer
                                                            </button>
                                                            <button type="reset"
                                                                class="btn-fill-lg bg-blue-dark btn-hover-yellow text-white">
                                                                Annuler
                                                            </button>
                                                        </div>

                                                        <button type="button"
                                                            class="btn-fill-lg bg-info btn-hover-bluedark text-white mr-2 btn-scroll-apercu"
                                                            data-target="#table-inscription-container">
                                                            <i class="fas fa-eye mr-1"></i> Aperçu
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- TABLEAU RECAPITULATIF INSCRIPTION -->
                                        <div class="frais-table-container" id="table-inscription-container">
                                            <h4 class="mb-3 font-weight-bold text-dark">Liste des frais d'inscription
                                                configurés</h4>
                                            <div class="table-responsive">
                                                <table
                                                    class="table table-bordered table-hover bg-white data-table-frais">
                                                    <thead class="table-custom-header">
                                                        <tr>
                                                            <th>Classe / Niveau / Section</th>
                                                            <th class="text-right" style="width: 200px;">Montant ($)
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($fraisInscription as $fi): 
                                                            $lbl = trim(($fi['nom_classe'] ?? '').' '.($fi['desc_classe'] ?? '').' - '.($fi['nom_niveau'] ?? '').' '.($fi['nom_section'] ?? '').' '.($fi['nom_option'] ?? ''));
                                                        ?>
                                                        <tr>
                                                            <td class="align-middle font-weight-medium"><?= h($lbl) ?>
                                                            </td>
                                                            <td
                                                                class="text-right font-weight-bold align-middle text-dark">
                                                                <?= number_format((float)$fi['montant'], 2, ',', ' ') ?>
                                                                $</td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MINERVAL -->
                                    <div class="tab-pane fade" id="tab-minerval" role="tabpanel">
                                        <form id="form-minerval" action="service/fixation-frais-scolaire.php"
                                            method="POST" autocomplete="off" novalidate>
                                            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                            <div class="row">
                                                <div class="col-xl-7 col-lg-6 form-group">
                                                    <div
                                                        class="select-all-box d-flex align-items-center justify-content-between">
                                                        <label
                                                            class="form-check-inline mb-0 font-weight-bold text-dark cursor-pointer">
                                                            <input type="checkbox" id="min-all" class="mr-2"> Tout
                                                            sélectionner
                                                        </label>
                                                        <span
                                                            class="badge badge-pill badge-light border text-muted"><?= count($classes) ?>
                                                            classe(s)</span>
                                                    </div>
                                                    <div class="classes-grid" id="min-classes">
                                                        <?php foreach ($classes as $c):
                                                        $lbl = trim(($c['classe'] ?? '').' '.($c['desc_classe'] ?? '').' '.($c['niveau'] ?? '').' '.($c['section'] ?? '').' '.($c['opt'] ?? '')); ?>
                                                        <label class="item">
                                                            <input type="checkbox" name="classes[]"
                                                                value="<?= (int)$c['id'] ?>">
                                                            <span><?= h($lbl ?: 'Classe #'.(int)$c['id']) ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="col-xl-5 col-lg-6">
                                                    <div class="sidebar-form-card">
                                                        <!-- <h5 class="mb-3 text-dark font-weight-bold">Paramètres du
                                                            montant</h5> -->
                                                        <div class="form-group mb-4">
                                                            <label class="font-weight-bold">Montant ($) <span
                                                                    class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" step="0.01" min="0"
                                                                    class="form-control" name="montant"
                                                                    placeholder="0.00" required>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">$</span>
                                                                </div>
                                                            </div>
                                                            <small class="soft-note">Obligatoire pour le
                                                                Minerval.</small>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2 mb-1">
                                                            <button type="submit"
                                                                class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mr-2"
                                                                name="save-minerval">
                                                                <i class="fas fa-check mr-1"></i> Appliquer
                                                            </button> <button type="reset"
                                                                class="btn-fill-lg bg-blue-dark btn-hover-yellow text-white">
                                                                Annuler
                                                            </button>
                                                        </div>

                                                        <button type="button"
                                                            class="btn-fill-lg bg-info btn-hover-bluedark text-white mr-2 btn-scroll-apercu"
                                                            data-target="#table-minerval-container">
                                                            <i class="fas fa-eye mr-1"></i> Aperçu
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- TABLEAU RECAPITULATIF MINERVAL -->
                                        <div class="frais-table-container" id="table-minerval-container">
                                            <h4 class="mb-3 font-weight-bold text-dark">Liste des frais de minerval
                                                configurés</h4>
                                            <div class="table-responsive">
                                                <table
                                                    class="table table-bordered table-hover bg-white data-table-frais">
                                                    <thead class="table-custom-header">
                                                        <tr>
                                                            <th>Classe / Niveau / Section</th>
                                                            <th class="text-right" style="width: 200px;">Montant ($)
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($fraisMinerval as $fm): 
                                                            $lbl = trim(($fm['nom_classe'] ?? '').' '.($fm['desc_classe'] ?? '').' - '.($fm['nom_niveau'] ?? '').' '.($fm['nom_section'] ?? '').' '.($fm['nom_option'] ?? ''));
                                                        ?>
                                                        <tr>
                                                            <td class="align-middle font-weight-medium"><?= h($lbl) ?>
                                                            </td>
                                                            <td
                                                                class="text-right font-weight-bold align-middle text-dark">
                                                                <?= number_format((float)$fm['montant'], 2, ',', ' ') ?>
                                                                $</td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- AUTRES FRAIS -->
                                    <div class="tab-pane fade" id="tab-autres" role="tabpanel">
                                        <form id="form-autres" action="service/fixation-frais-scolaire.php"
                                            method="POST" autocomplete="off" novalidate>
                                            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                                            <div class="row">
                                                <div class="col-xl-7 col-lg-6 form-group">
                                                    <div
                                                        class="select-all-box d-flex align-items-center justify-content-between">
                                                        <label
                                                            class="form-check-inline mb-0 font-weight-bold text-dark cursor-pointer">
                                                            <input type="checkbox" id="autres-all" class="mr-2"> Tout
                                                            sélectionner
                                                        </label>
                                                        <span
                                                            class="badge badge-pill badge-light border text-muted"><?= count($classes) ?>
                                                            classe(s)</span>
                                                    </div>
                                                    <div class="classes-grid" id="autres-classes">
                                                        <?php foreach ($classes as $c):
                                                        $lbl = trim(($c['classe'] ?? '').' '.($c['desc_classe'] ?? '').' '.($c['niveau'] ?? '').' '.($c['section'] ?? '').' '.($c['opt'] ?? '')); ?>
                                                        <label class="item">
                                                            <input type="checkbox" name="classes[]"
                                                                value="<?= (int)$c['id'] ?>">
                                                            <span><?= h($lbl ?: 'Classe #'.(int)$c['id']) ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="col-xl-5 col-lg-6">
                                                    <div class="sidebar-form-card">
                                                        <h5 class="mb-3 text-dark font-weight-bold">Détails des frais
                                                        </h5>
                                                        <div class="form-group mb-3">
                                                            <label class="font-weight-bold">Montant ($) <span
                                                                    class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" step="0.01" min="0"
                                                                    class="form-control" name="montant"
                                                                    placeholder="0.00" required>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">$</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group mb-4">
                                                            <label class="font-weight-bold">Description <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" name="description"
                                                                placeholder="Ex: Frais de laboratoire" required>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2 mb-1">
                                                            <button type="submit"
                                                                class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mr-2"
                                                                name="save-autre">
                                                                <i class="fas fa-check mr-1"></i> Appliquer
                                                            </button><button type="reset"
                                                                class="btn-fill-lg bg-blue-dark btn-hover-yellow text-white">
                                                                Annuler
                                                            </button>
                                                        </div>

                                                        <button type="button"
                                                            class="btn-fill-lg bg-info btn-hover-bluedark text-white mr-2 btn-scroll-apercu"
                                                            data-target="#table-autres-container">
                                                            <i class="fas fa-eye mr-1"></i> Aperçu
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- TABLEAU RECAPITULATIF AUTRES FRAIS -->
                                        <div class="frais-table-container" id="table-autres-container">
                                            <h4 class="mb-3 font-weight-bold text-dark">Liste des autres frais
                                                configurés</h4>
                                            <div class="table-responsive">
                                                <table
                                                    class="table table-bordered table-hover bg-white data-table-frais">
                                                    <thead class="table-custom-header">
                                                        <tr>
                                                            <th>Classe / Niveau / Section</th>
                                                            <th>Description</th>
                                                            <th class="text-right" style="width: 200px;">Montant ($)
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($fraisAutres as $fa): 
                                                            $lbl = trim(($fa['nom_classe'] ?? '').' '.($fa['desc_classe'] ?? '').' - '.($fa['nom_niveau'] ?? '').' '.($fa['nom_section'] ?? '').' '.($fa['nom_option'] ?? ''));
                                                        ?>
                                                        <tr>
                                                            <td class="align-middle font-weight-medium"><?= h($lbl) ?>
                                                            </td>
                                                            <td class="align-middle"><span
                                                                    class="badge badge-light border text-dark font-weight-normal"><?= h($fa['description'] ?? '') ?></span>
                                                            </td>
                                                            <td
                                                                class="text-right font-weight-bold align-middle text-dark">
                                                                <?= number_format((float)$fa['montant'], 2, ',', ' ') ?>
                                                                $</td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /tab-content -->
                            </div>
                        </div>
                    </div>
                </div>

                <?php require_once('layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
    $(function() {
        // --- 1. Initialisation de la Pagination DataTables sur les Tableaux ---
        $('.data-table-frais').DataTable({
            "language": {
                "sEmptyTable": "Aucune donnée disponible dans le tableau",
                "sInfo": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                "sInfoEmpty": "Affichage de 0 à 0 sur 0 entrée",
                "sInfoFiltered": "(filtré de _MAX_ entrées au total)",
                "sLengthMenu": "Afficher _MENU_ entrées",
                "sLoadingRecords": "Chargement...",
                "sProcessing": "Traitement...",
                "sSearch": "Rechercher :",
                "sZeroRecords": "Aucun résultat trouvé",
                "oPaginate": {
                    "sFirst": "Premier",
                    "sLast": "Dernier",
                    "sNext": "Suivant",
                    "sPrevious": "Précédent"
                }
            },
            "pageLength": 10,
            "responsive": true,
            "ordering": true
        });

        // --- 2. Action du bouton "Aperçu" (Scroll fluide vers le tableau) ---
        $('.btn-scroll-apercu').on('click', function() {
            const targetId = $(this).data('target');
            if ($(targetId).length) {
                $('html, body').animate({
                    scrollTop: $(targetId).offset().top - 80
                }, 500);
            }
        });

        // --- 3. Gestion de la sélection multiple des classes ---
        function bindSelectAll(masterId, containerId) {
            const $m = $(masterId),
                $c = $(containerId);
            if (!$m.length || !$c.length) return;
            $m.on('change', function() {
                $c.find('input[type="checkbox"][name="classes[]"]').prop('checked', this.checked);
            });
        }
        bindSelectAll('#insc-all', '#insc-classes');
        bindSelectAll('#min-all', '#min-classes');
        bindSelectAll('#autres-all', '#autres-classes');

        // --- 4. Validation formulaire ---
        function requireAtLeastOneClass($form) {
            if ($form.find('input[name="classes[]"]:checked').length === 0) {
                alert('Veuillez cocher au moins une classe.');
                return false;
            }
            return true;
        }

        $('#form-inscription, #form-minerval, #form-autres').on('submit', function(e) {
            if (!requireAtLeastOneClass($(this))) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>

</html>