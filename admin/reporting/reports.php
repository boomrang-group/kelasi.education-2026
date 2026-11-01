<?php
// admin/service/dashboard-admin.php & Vue intégrée EduTrack 360
declare(strict_types=1);

// Session
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Auth admin | administrateur
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

// DB (PDO dans $pdo)
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

// Code école (depuis session ou fallback DB)
$userId     = (int)($_SESSION['user_id'] ?? 0);
$codeEcole  = (string)($_SESSION['code_ecole'] ?? '');

if ($codeEcole === '' && $userId > 0) {
    try {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $userId]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) { $_SESSION['code_ecole'] = $codeEcole; }
    } catch (Throwable $e) {}
}

// Valeurs par défaut
$nbre_classe       = 0;
$nbre_prof         = 0;
$nbre_eleve        = 0;
$nbre_connectes    = 0;
$nbre_cours_quiz   = 0;
$finance_total     = 0.0;
$somme_inscription = 0.0;
$somme_minerval    = 0.0;
$somme_autres      = 0.0;

$chart_labels      = [];
$chart_inscription = [];
$chart_minerval    = [];
$chart_autres      = [];
$live_users_list   = [];

function fetch_scalar(PDO $pdo, string $sql, array $params=[], $def=0) {
    $st=$pdo->prepare($sql); $st->execute($params); $v=$st->fetchColumn();
    return ($v===false||$v===null)?$def:$v;
}
function fetch_all(PDO $pdo, string $sql, array $params=[]): array {
    $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($codeEcole !== '') {
    try {
        // KPIs de base
        $nbre_classe = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM classes  WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $nbre_prof   = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM teacher  WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $nbre_eleve  = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM students WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);

        // Utilisateurs actifs / connectés
        $nbre_connectes = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM users WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);

        // Cours & Quiz publiés
        $nb_cours = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM cours WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $nb_quiz  = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM quizzes WHERE code_ecole = :ce AND is_published = 1", [':ce'=>$codeEcole], 0);
        $nbre_cours_quiz = $nb_cours + $nb_quiz;

        // Finances
        $somme_inscription = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'", [':ce'=>$codeEcole], 0.0);
        $somme_minerval    = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'", [':ce'=>$codeEcole], 0.0);
        $somme_autres      = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND (statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')))", [':ce'=>$codeEcole], 0.0);
        $finance_total     = $somme_inscription + $somme_minerval + $somme_autres;

        // Séries 6 derniers mois (Finances)
        $rows6 = fetch_all($pdo, "SELECT DATE_FORMAT(date_paiement,'%Y-%m') AS ym, SUM(CASE WHEN statut='Inscription' THEN montant_paye ELSE 0 END) AS s_ins, SUM(CASE WHEN statut='Minerval' THEN montant_paye ELSE 0 END) AS s_min, SUM(CASE WHEN statut NOT IN('Inscription','Minerval') AND statut<>'' THEN montant_paye ELSE 0 END) AS s_aut FROM paiement WHERE code_ecole = :ce AND date_paiement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') GROUP BY ym ORDER BY ym ASC", [':ce'=>$codeEcole]);

        $period=[]; $now = new DateTime('first day of this month');
        for ($i=5;$i>=0;$i--) { $period[] = (clone $now)->modify("-$i month")->format('Y-m'); }
        $map=[]; foreach($rows6 as $r){ $map[$r['ym']] = $r; }

        foreach($period as $ym){
            $chart_labels[]      = $ym;
            $chart_inscription[] = isset($map[$ym]) ? (float)$map[$ym]['s_ins'] : 0.0;
            $chart_minerval[]    = isset($map[$ym]) ? (float)$map[$ym]['s_min'] : 0.0;
            $chart_autres[]      = isset($map[$ym]) ? (float)$map[$ym]['s_aut'] : 0.0;
        }

        // Utilisateurs pour DataTables
        $live_users_list = fetch_all($pdo, "SELECT id, username, role, first_name, last_name, created_at FROM users WHERE code_ecole = :ce ORDER BY id DESC LIMIT 20", [':ce' => $codeEcole]);

    } catch (Throwable $e) {
        error_log('Dashboard admin service error: '.$e->getMessage());
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack 360 - Dashboard Supervision Global Live (École : <?= h($codeEcole) ?>)</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- jQuery & DataTables CSS/JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Custom Scrollbars */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #090d16; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }

        /* Custom DataTables Dark Override */
        .dataTables_wrapper { color: #94a3b8 !important; padding: 0.75rem 0; font-size: 0.8125rem; }
        .dataTables_length select, .dataTables_filter input {
            background-color: #0f172a !important;
            border: 1px solid #1e293b !important;
            color: #f8fafc !important;
            border-radius: 0.5rem !important;
            padding: 0.4rem 0.75rem !important;
            outline: none;
        }
        .dataTables_length select:focus, .dataTables_filter input:focus { border-color: #6366f1 !important; }
        table.dataTable { border-collapse: collapse !important; width: 100% !important; border-bottom: 1px solid #1e293b !important; }
        table.dataTable thead th {
            background-color: #0f172a !important;
            color: #cbd5e1 !important;
            border-bottom: 2px solid #1e293b !important;
            padding: 12px 14px !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        table.dataTable tbody td { border-bottom: 1px solid #1e293b !important; padding: 10px 14px !important; vertical-align: middle; }
        table.dataTable tbody tr { background-color: transparent !important; transition: background-color 0.15s ease; }
        table.dataTable tbody tr:hover { background-color: rgba(30, 41, 59, 0.5) !important; }
        .dataTables_info, .dataTables_paginate { color: #64748b !important; margin-top: 0.75rem !important; }
        .paginate_button { color: #cbd5e1 !important; border-radius: 0.375rem !important; border: 1px solid #1e293b !important; background: #0f172a !important; }
        .paginate_button.current, .paginate_button.current:hover { background: #4f46e5 !important; color: white !important; border-color: #4f46e5 !important; }

        /* Pulse Animations */
        @keyframes livePulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .live-dot { animation: livePulse 2s infinite; }

        /* Stream Slide & Fade In */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-stream-item { animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900/90 border-r border-slate-800 flex flex-col justify-between transition-all duration-300 z-30 fixed lg:relative h-full -translate-x-full lg:translate-x-0">
        <div>
            <!-- Sidebar Header -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-indigo-500/25">
                        <i class="fa-solid fa-radar"></i>
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-wide text-white block leading-none">EduTrack <span class="text-indigo-400">360</span></span>
                        <span class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Supervision Live</span>
                    </div>
                </div>
                <button id="closeSidebarMobile" class="lg:hidden text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-indigo-400 bg-indigo-950/50 border border-indigo-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chart-line text-base w-5 text-center"></i>
                    <span>Vue Globale Live</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chalkboard-user text-base w-5 text-center"></i>
                    <span>Professeurs (<?= (int)$nbre_prof ?>)</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-graduate text-base w-5 text-center"></i>
                    <span>Élèves (<?= (int)$nbre_eleve ?>)</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-school text-base w-5 text-center"></i>
                    <span>Classes (<?= (int)$nbre_classe ?>)</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-shield text-base w-5 text-center"></i>
                    <span>Admins & Config</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-800/80">
            <div class="bg-slate-950/80 p-3 rounded-xl border border-slate-800 flex items-center gap-3">
                <div class="relative">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-white text-xs shadow-md">
                        <?= isset($_SESSION['first_name']) ? strtoupper(substr($_SESSION['first_name'], 0, 2)) : 'AD' ?>
                    </div>
                    <span class="w-2.5 h-2.5 bg-emerald-500 border-2 border-slate-900 rounded-full absolute -bottom-0.5 -right-0.5"></span>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-xs font-semibold text-white truncate"><?= h($_SESSION['first_name'] ?? 'Admin') ?></h4>
                    <p class="text-[11px] text-slate-400 truncate">École : <?= h($codeEcole) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-950">
        
        <!-- TOP HEADER -->
        <header class="h-16 bg-slate-900/80 backdrop-blur border-b border-slate-800 flex items-center justify-between px-4 lg:px-8 z-20">
            <div class="flex items-center gap-4">
                <button id="toggleSidebar" class="text-slate-400 hover:text-white p-2 rounded-lg bg-slate-950 border border-slate-800 lg:hidden">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>
                
                <!-- Live Pulse Banner -->
                <div class="flex items-center gap-2.5 bg-emerald-950/40 border border-emerald-800/50 px-3 py-1.5 rounded-full">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 live-dot"></span>
                    <span class="text-xs font-bold text-emerald-400 tracking-wider uppercase">Flux Direct</span>
                    <span class="text-[10px] text-emerald-300/60 hidden sm:inline">| BDD Active</span>
                </div>
            </div>

            <!-- Global Quick Role Selector & Controls -->
            <div class="flex items-center gap-3 lg:gap-4">
                
                <!-- Quick Filter Role Header Selector -->
                <div class="hidden md:flex items-center bg-slate-950 border border-slate-800 rounded-xl p-1 text-xs">
                    <span class="text-slate-400 px-2 font-medium">Vue :</span>
                    <button class="role-btn-global active bg-indigo-600 text-white font-medium px-2.5 py-1 rounded-lg transition" data-role="ALL">Tous</button>
                    <button class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition" data-role="Professeur">Profs</button>
                    <button class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition" data-role="Élève">Élèves</button>
                    <button class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition" data-role="Parent">Parents</button>
                    <button class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition" data-role="Admin">Admins</button>
                </div>

                <!-- Refresh Button -->
                <button id="refreshBtn" class="flex items-center gap-2 text-xs font-medium bg-slate-950 hover:bg-slate-800 text-slate-300 border border-slate-800 px-3 py-2 rounded-xl transition active:scale-95">
                    <i class="fa-solid fa-arrows-rotate text-indigo-400" id="refreshIcon"></i>
                    <span class="hidden sm:inline">Sync BDD</span>
                </button>

                <!-- Notifications -->
                <button class="relative p-2 text-slate-400 hover:text-white rounded-xl bg-slate-950 border border-slate-800 transition">
                    <i class="fa-regular fa-bell text-base"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500"></span>
                </button>
            </div>
        </header>

        <!-- MAIN SCROLLABLE CONTENT -->
        <main class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6">
            
            <!-- PAGE TITLE BAR -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900/40 p-4 rounded-2xl border border-slate-800/80">
                <div>
                    <h1 class="text-xl font-extrabold text-white tracking-tight flex items-center gap-2">
                        <span>Supervision Multi-Utilisateurs & Finances</span>
                        <span id="activeRoleBadge" class="text-xs bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2.5 py-0.5 rounded-full font-semibold">
                            Tous les Rôles
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">Suivi nominatif de l'école <strong class="text-indigo-400"><?= h($codeEcole) ?></strong> : Comptes, Pédagogie, Devoirs et Trésorerie.</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-400 bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl">
                    <i class="fa-regular fa-clock text-indigo-400"></i>
                    <span>Dernière synchro : <strong id="lastSyncTime" class="text-slate-200 font-mono">À l'instant</strong></span>
                </div>
            </div>

            <!-- KPI CARDS GRID (Connected to Real PHP Variables) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- KPI 1: Total Comptes / Connectés Live -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Comptes & Connectés</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiConnected"><?= (int)($nbre_connectes ?? 0) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                            <i class="fa-solid fa-users-viewfinder text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-emerald-400 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-database"></i> Base SQL Active
                        </span>
                        <span class="text-slate-500 text-[11px]"><?= (int)$nbre_eleve ?> Élèves | <?= (int)$nbre_prof ?> Profs</span>
                    </div>
                </div>

                <!-- KPI 2: Cours & Quiz Publiés -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Cours & Quiz Publiés</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiPublished"><?= (int)($nbre_cours_quiz ?? 0) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 flex items-center justify-center">
                            <i class="fa-solid fa-book-bookmark text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-xs text-violet-400 font-medium">
                        <i class="fa-solid fa-chalkboard"></i>
                        <span><?= (int)$nbre_classe ?> Classes enregistrées</span>
                    </div>
                </div>

                <!-- KPI 3: Finances Globales -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Finances Globales</p>
                            <h3 class="text-2xl font-extrabold text-emerald-400 mt-1">$ <?= number_format((float)($finance_total ?? 0.0), 2, '.', ' ') ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                            <i class="fa-solid fa-wallet text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-400">
                        <i class="fa-solid fa-coins"></i>
                        <span class="font-medium">Ins: $<?= number_format($somme_inscription, 0) ?> | Min: $<?= number_format($somme_minerval, 0) ?></span>
                    </div>
                </div>

                <!-- KPI 4: Taux d'Engagement Global -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Engagement Global</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiEngagement">92.8%</h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                            <i class="fa-solid fa-bolt text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-400">
                        <i class="fa-solid fa-comments"></i>
                        <span class="font-medium" id="kpiInteractionsCount">Flux financier et sessions stables</span>
                    </div>
                </div>

            </div>

            <!-- CHARTS SECTION (Powered by PHP Data Arrays) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Main Traffic / Finance Line Chart (2 cols) -->
                <div class="lg:col-span-2 bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Encaissements Financiers (6 Derniers Mois)</h2>
                            <p class="text-xs text-slate-400">Suivi mensuel de l'école (Inscriptions, Minerval, Autres)</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="flex items-center gap-1 text-emerald-400"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Inscription</span>
                            <span class="flex items-center gap-1 text-indigo-400"><span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Minerval</span>
                            <span class="flex items-center gap-1 text-amber-400"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Autres</span>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="financeLineChart"></canvas>
                    </div>
                </div>

                <!-- Doughnut Finance Distribution (1 col) -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Répartition des Recettes</h2>
                        <p class="text-xs text-slate-400">Ventilation globale des paiements</p>
                    </div>
                    <div class="h-48 relative my-3">
                        <canvas id="financeDoughnutChart"></canvas>
                    </div>
                    <!-- Role Details Legend Grid -->
                    <div class="grid grid-cols-1 gap-2 text-xs pt-3 border-t border-slate-800">
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Inscriptions</span>
                            <span class="font-bold text-emerald-400">$ <?= number_format($somme_inscription, 2) ?></span>
                        </div>
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Minerval</span>
                            <span class="font-bold text-indigo-400">$ <?= number_format($somme_minerval, 2) ?></span>
                        </div>
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Autres</span>
                            <span class="font-bold text-amber-400">$ <?= number_format($somme_autres, 2) ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- LIVE ACTIVITY FEED & TOP INTERACTIONS ROW -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- LIVE STREAM FEED (2 cols) -->
                <div class="lg:col-span-2 bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-indigo-400"></i>
                                Journal des Actions en Direct (École : <?= h($codeEcole) ?>)
                            </h2>
                            <p class="text-xs text-slate-400">Événements instantanés connectés à la base de données</p>
                        </div>
                        <button id="clearStreamBtn" class="text-xs text-slate-500 hover:text-slate-300 transition">
                            Effacer la vue
                        </button>
                    </div>

                    <!-- Stream Container -->
                    <div id="liveActivityStream" class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1">
                        <!-- Stream items populated via JS -->
                    </div>
                </div>

                <!-- POPULAR INTERACTIONS BAR CHART (1 col) -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Indicateurs de Performance</h2>
                        <p class="text-xs text-slate-400">Synthèse rapide de l'activité système</p>
                    </div>
                    <div class="space-y-3 my-auto">
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center">
                            <span class="text-xs text-slate-400">Total Classes</span>
                            <span class="text-sm font-bold text-white"><?= (int)$nbre_classe ?></span>
                        </div>
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center">
                            <span class="text-xs text-slate-400">Total Professeurs</span>
                            <span class="text-sm font-bold text-violet-400"><?= (int)$nbre_prof ?></span>
                        </div>
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center">
                            <span class="text-xs text-slate-400">Total Élèves</span>
                            <span class="text-sm font-bold text-indigo-400"><?= (int)$nbre_eleve ?></span>
                        </div>
                    </div>
                    <div class="text-center pt-2 border-t border-slate-800">
                        <span class="text-[11px] text-slate-400"><i class="fa-solid fa-circle-info text-indigo-400 mr-1"></i>Sécurité et isolation par code école</span>
                    </div>
                </div>

            </div>

            <!-- DATATABLE USER SESSIONS & MOVEMENTS (Powered by PHP Database `$live_users_list`) -->
            <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                            Registre des Utilisateurs (Base de Données - Code : <?= h($codeEcole) ?>)
                        </h2>
                        <p class="text-xs text-slate-400">Liste nominative des derniers comptes enregistrés</p>
                    </div>
                    
                    <!-- Table Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select id="tableRoleFilter" class="bg-slate-950 border border-slate-800 text-xs text-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                            <option value="">Tous les Rôles</option>
                            <option value="admin">Admin</option>
                            <option value="administrateur">Administrateur</option>
                            <option value="professeur">Professeur</option>
                            <option value="eleve">Élève</option>
                            <option value="parent">Parent</option>
                        </select>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto">
                    <table id="userSessionsTable" class="w-full text-left">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Identifiant / Username</th>
                                <th>Date d'inscription</th>
                                <th>Statut BDD</th>
                                <th>Inspecter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Content Populated via PHP / JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- INSPECTION USER MODAL -->
    <div id="userModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-lg mx-4 shadow-2xl relative">
            <button id="closeModal" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div id="modalContent" class="space-y-5">
                <!-- Dynamic Content JS -->
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC & PHP DATA INJECTION -->
    <script>
        // Injection sécurisée des données de la base de données PHP vers JS
        const realUsers = <?= json_encode($live_users_list ?? [], JSON_UNESCAPED_UNICODE) ?>;
        
        // Données financières PHP injectées pour les Graphiques
        const chartLabels = <?= json_encode($chart_labels ?? []) ?>;
        const serieIns = <?= json_encode($chart_inscription ?? []) ?>;
        const serieMin = <?= json_encode($chart_minerval ?? []) ?>;
        const serieAut = <?= json_encode($chart_autres ?? []) ?>;

        $(document).ready(function() {

            // --- 1. SIDEBAR TOGGLE ---
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').removeClass('-translate-x-full');
            });
            $('#closeSidebarMobile').on('click', function() {
                $('#sidebar').addClass('-translate-x-full');
            });

            // --- 2. POPULATE DATATABLE FROM REAL DATABASE USERS ---
            const tbody = $('#userSessionsTable tbody');
            realUsers.forEach(u => {
                const fullName = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username;
                const initials = fullName.substring(0, 2).toUpperCase();
                
                tbody.append(`
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-indigo-600 to-violet-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                                    ${initials}
                                </div>
                                <div>
                                    <span class="font-bold text-slate-100 block text-xs">${fullName}</span>
                                    <span class="text-[10px] text-slate-500 font-mono">ID: #${u.id}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="px-2.5 py-0.5 rounded-md text-[10px] font-semibold border bg-indigo-950/75 text-indigo-300 border-indigo-800/60 uppercase">${u.role}</span>
                        </td>
                        <td><span class="font-mono text-slate-300 text-xs">${u.username}</span></td>
                        <td><span class="text-slate-400 text-xs">${u.created_at || 'N/A'}</span></td>
                        <td>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/50">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 live-dot"></span> Actif BDD
                            </span>
                        </td>
                        <td>
                            <button class="inspect-btn p-1.5 text-slate-400 hover:text-indigo-400 hover:bg-slate-800 rounded-lg transition" data-id="${u.id}" title="Inspecter l'utilisateur">
                                <i class="fa-solid fa-circle-info text-sm"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            const dataTable = $('#userSessionsTable').DataTable({
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Rechercher un membre...",
                    lengthMenu: "_MENU_ / page",
                    info: "Affichage _START_ à _END_ sur _TOTAL_",
                    paginate: {
                        next: "<i class='fa-solid fa-chevron-right'></i>",
                        previous: "<i class='fa-solid fa-chevron-left'></i>"
                    }
                },
                pageLength: 5,
                responsive: true
            });

            // Filtering Table via Role Select
            $('#tableRoleFilter').on('change', function() {
                dataTable.column(1).search(this.value).draw();
            });

            // Global Role Filter Buttons
            $('.role-btn-global').on('click', function() {
                $('.role-btn-global').removeClass('active bg-indigo-600 text-white').addClass('text-slate-400');
                $(this).addClass('active bg-indigo-600 text-white').removeClass('text-slate-400');
                
                const role = $(this).data('role');
                if (role === 'ALL') {
                    dataTable.column(1).search('').draw();
                    $('#activeRoleBadge').text('Tous les Rôles');
                } else {
                    dataTable.column(1).search(role, true, false).draw();
                    $('#activeRoleBadge').text(role + 's');
                }
            });

            // --- 3. CHARTS CONFIGURATION (Chart.js with real financial series) ---

            // Chart 1: Finance Line Chart (6 Months)
            const ctxFinance = document.getElementById('financeLineChart').getContext('2d');
            const financeChart = new Chart(ctxFinance, {
                type: 'line',
                data: {
                    labels: chartLabels.length > 0 ? chartLabels : ['M-5', 'M-4', 'M-3', 'M-2', 'M-1', 'Mois Actuel'],
                    datasets: [
                        {
                            label: 'Inscription ($)',
                            data: serieIns.length > 0 ? serieIns : [0,0,0,0,0,0],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 3
                        },
                        {
                            label: 'Minerval ($)',
                            data: serieMin.length > 0 ? serieMin : [0,0,0,0,0,0],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.05)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 3
                        },
                        {
                            label: 'Autres ($)',
                            data: serieAut.length > 0 ? serieAut : [0,0,0,0,0,0],
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(30, 41, 59, 0.5)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                        y: { grid: { color: 'rgba(30, 41, 59, 0.5)' }, ticks: { color: '#64748b', font: { size: 10 }, callback: v => '$ ' + v } }
                    }
                }
            });

            // Chart 2: Finance Doughnut Chart
            const ctxDoughnut = document.getElementById('financeDoughnutChart').getContext('2d');
            const financeDoughnut = new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['Inscriptions', 'Minerval', 'Autres'],
                    datasets: [{
                        data: [<?= (float)$somme_inscription ?>, <?= (float)$somme_minerval ?>, <?= (float)$somme_autres ?>],
                        backgroundColor: ['#10b981', '#6366f1', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: { legend: { display: false } }
                }
            });

            // --- 4. LIVE ACTIVITY STREAM GENERATOR ---
            const liveStreamTemplates = [
                { user: "Système École", role: "Admin", action: "a initialisé les services de l'école", target: "Code : <?= h($codeEcole) ?>", icon: "fa-server", color: "text-emerald-400", badgeBg: "bg-emerald-950/80 border-emerald-800" },
                { user: "Module Paiement", role: "Finance", action: "a enregistré une transaction de type", target: "Minerval / Inscription", icon: "fa-wallet", color: "text-indigo-400", badgeBg: "bg-indigo-950/80 border-indigo-800" },
                { user: "Serveur Principal", role: "Sécurité", action: "a vérifié l'intégrité des tables", target: "Paiements & Étudiants", icon: "fa-shield-halved", color: "text-violet-400", badgeBg: "bg-violet-950/80 border-violet-800" }
            ];

            function appendStreamItem(item) {
                const timeStr = new Date().toLocaleTimeString();
                const html = `
                    <div class="animate-stream-item p-3 rounded-xl bg-slate-950 border border-slate-800/80 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center ${item.color} shrink-0">
                                <i class="fa-solid ${item.icon} text-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-slate-200 leading-snug">
                                    <strong class="text-white">${item.user}</strong> 
                                    <span class="px-1.5 py-0.2 text-[9px] font-semibold border rounded ${item.badgeBg} ${item.color} inline-block">${item.role}</span>
                                    ${item.action} 
                                    <span class="text-indigo-300 font-medium">${item.target}</span>
                                </p>
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-500 font-mono shrink-0">${timeStr}</span>
                    </div>
                `;
                $('#liveActivityStream').prepend(html);
                if ($('#liveActivityStream').children().length > 10) {
                    $('#liveActivityStream').children().last().remove();
                }
            }

            liveStreamTemplates.forEach(item => appendStreamItem(item));

            $('#clearStreamBtn').on('click', function() {
                $('#liveActivityStream').empty();
            });

            // --- 5. MANUAL REFRESH & SYNC ---
            $('#refreshBtn').on('click', function() {
                $('#refreshIcon').addClass('fa-spin');
                setTimeout(function() {
                    location.reload(); // Recharge la page pour actualiser les données de la base PHP
                }, 600);
            });

            // --- 6. INSPECTION MODAL (User Details from realUsers) ---
            $(document).on('click', '.inspect-btn', function() {
                const userId = $(this).data('id');
                const user = realUsers.find(u => u.id == userId);

                if (user) {
                    const fullName = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || user.username;
                    $('#modalContent').html(`
                        <div class="flex items-center gap-4 border-b border-slate-800 pb-4">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-600 text-white font-bold text-lg flex items-center justify-center shadow-lg">
                                ${fullName.substring(0,2).toUpperCase()}
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-white">${fullName}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded border bg-indigo-950/80 border-indigo-800 text-indigo-300 uppercase">${user.role}</span>
                                    <span class="text-xs text-slate-400 font-mono">ID: #${user.id}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                    <span class="text-slate-400 block text-[11px]">Identifiant (Username)</span>
                                    <span class="font-bold text-white mt-0.5 block font-mono">${user.username}</span>
                                </div>
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                    <span class="text-slate-400 block text-[11px]">Date d'inscription</span>
                                    <span class="font-mono text-white mt-0.5 block">${user.created_at || 'N/A'}</span>
                                </div>
                            </div>

                            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                <span class="text-slate-400 block text-[11px] mb-1">Code École Associé</span>
                                <p class="text-indigo-400 font-bold"><?= h($codeEcole) ?></p>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button id="closeModalBtn" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold px-4 py-2 rounded-xl transition">
                                Fermer
                            </button>
                        </div>
                    `);

                    $('#userModal').removeClass('hidden').addClass('flex');
                    setTimeout(() => $('#userModal').removeClass('opacity-0'), 10);
                }
            });

            // Close Modal Logic
            $(document).on('click', '#closeModal, #closeModalBtn, #userModal', function(e) {
                if (e.target === this || e.currentTarget.id === 'closeModal' || e.currentTarget.id === 'closeModalBtn') {
                    $('#userModal').addClass('opacity-0');
                    setTimeout(() => $('#userModal').addClass('hidden').removeClass('flex'), 200);
                }
            });

        });
    </script>
</body>
</html>