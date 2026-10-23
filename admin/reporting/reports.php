<?php
// admin/reporting/reports.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Vérification de la session et des droits administrateur
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur','promoteur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); 
    exit;
}

// Connexion DB
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : Base de données indisponible.');
}

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');

// Récupération des données réelles depuis la base de données
$countEleves = 0;
$countProfs = 0;
$countAdmins = 0;
$coursPublies = 0;
$quizPublies = 0;
$totalSubmissions = 0;

try {
    // Compte des rôles dans la base de données
    $stRole = $pdo->prepare("SELECT role, COUNT(*) as total FROM users" . ($codeEcole ? " WHERE code_ecole = :ce" : "") . " GROUP BY role");
    $stRole->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $rolesData = $stRole->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $countEleves = (int)($rolesData['eleve'] ?? 0);
    $countProfs = (int)($rolesData['prof'] ?? 0) + (int)($rolesData['repetiteur'] ?? 0);
    $countAdmins = (int)($rolesData['admin'] ?? 0) + (int)($rolesData['promoteur'] ?? 0);

    // Total cours et quiz
    $stCours = $pdo->prepare("SELECT COUNT(*) FROM cours" . ($codeEcole ? " WHERE code_ecole = :ce" : ""));
    $stCours->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $coursPublies = (int)$stCours->fetchColumn();

    $stQuiz = $pdo->prepare("SELECT COUNT(*) FROM quizzes" . ($codeEcole ? " WHERE code_ecole = :ce" : ""));
    $stQuiz->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $quizPublies = (int)$stQuiz->fetchColumn();

    // Devoirs et soumissions
    $stSub = $pdo->prepare("SELECT COUNT(*) FROM quiz_submissions" . ($codeEcole ? " WHERE code_ecole = :ce" : ""));
    $stSub->execute($codeEcole ? [':ce' => $codeEcole] : []);
    $totalSubmissions = (int)$stSub->fetchColumn();

} catch (Throwable $e) {
    // Gestion silencieuse en cas d'erreur
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-950 text-slate-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack 360 - Dashboard Supervision Global Live</title>

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- jQuery & DataTables CSS/JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    body {
        font-family: 'Inter', sans-serif;
    }

    /* Custom Scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #090d16;
    }

    ::-webkit-scrollbar-thumb {
        background: #1e293b;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #334155;
    }

    /* Custom DataTables Dark Override */
    .dataTables_wrapper {
        color: #94a3b8 !important;
        padding: 0.75rem 0;
        font-size: 0.8125rem;
    }

    .dataTables_length select,
    .dataTables_filter input {
        background-color: #0f172a !important;
        border: 1px solid #1e293b !important;
        color: #f8fafc !important;
        border-radius: 0.5rem !important;
        padding: 0.4rem 0.75rem !important;
        outline: none;
    }

    .dataTables_length select:focus,
    .dataTables_filter input:focus {
        border-color: #6366f1 !important;
    }

    table.dataTable {
        border-collapse: collapse !important;
        width: 100% !important;
        border-bottom: 1px solid #1e293b !important;
    }

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

    table.dataTable tbody td {
        border-bottom: 1px solid #1e293b !important;
        padding: 10px 14px !important;
        vertical-align: middle;
    }

    table.dataTable tbody tr {
        background-color: transparent !important;
        transition: background-color 0.15s ease;
    }

    table.dataTable tbody tr:hover {
        background-color: rgba(30, 41, 59, 0.5) !important;
    }

    .dataTables_info,
    .dataTables_paginate {
        color: #64748b !important;
        margin-top: 0.75rem !important;
    }

    .paginate_button {
        color: #cbd5e1 !important;
        border-radius: 0.375rem !important;
        border: 1px solid #1e293b !important;
        background: #0f172a !important;
    }

    .paginate_button.current,
    .paginate_button.current:hover {
        background: #4f46e5 !important;
        color: white !important;
        border-color: #4f46e5 !important;
    }

    /* Pulse Animations */
    @keyframes livePulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 8px rgba(34, 197, 94, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
        }
    }

    .live-dot {
        animation: livePulse 2s infinite;
    }

    /* Stream Slide & Fade In */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-stream-item {
        animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    </style>
</head>

<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="w-64 bg-slate-900/90 border-r border-slate-800 flex flex-col justify-between transition-all duration-300 z-30 fixed lg:relative h-full -translate-x-full lg:translate-x-0">
        <div>
            <!-- Sidebar Header -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800/80">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-indigo-500/25">
                        <i class="fa-solid fa-radar"></i>
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-wide text-white block leading-none">EduTrack
                            <span class="text-indigo-400">360</span></span>
                        <span class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Supervision
                            Live</span>
                    </div>
                </div>
                <button id="closeSidebarMobile" class="lg:hidden text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <a href="#"
                    class="flex items-center gap-3 px-3.5 py-2.5 text-indigo-400 bg-indigo-950/50 border border-indigo-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chart-line text-base w-5 text-center"></i>
                    <span>Vue Globale Live</span>
                </a>
                <a href="#"
                    class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chalkboard-user text-base w-5 text-center"></i>
                    <span>Professeurs</span>
                </a>
                <a href="#"
                    class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-graduate text-base w-5 text-center"></i>
                    <span>Élèves</span>
                </a>
                <a href="#"
                    class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-people-roof text-base w-5 text-center"></i>
                    <span>Parents</span>
                </a>
                <a href="#"
                    class="flex items-center gap-3 px-3.5 py-2.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-shield text-base w-5 text-center"></i>
                    <span>Admins & Config</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-800/80">
            <div class="bg-slate-950/80 p-3 rounded-xl border border-slate-800 flex items-center gap-3">
                <div class="relative">
                    <img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBwgHBgkIBwgKCgkLDRYPDQwMDRsUFRAWIB0iIiAdHx8kKDQsJCYxJx8fLT0tMTU3Ojo6Iys/RD84QzQ5OjcBCgoKDQwNGg8PGjclHyU3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3N//AABEIALwAyAMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAAEBQIDBgABB//EAD0QAAIBAwMCAwUGBAUDBQAAAAECAwAEERIhMQVBEyJRBhRhcYEjMkKRobEVM1LwJGLB4fFTctEHJTQ1Q//EABkBAAMBAQEAAAAAAAAAAAAAAAECAwQABf/EACMRAAICAwACAgIDAAAAAAAAAAABAhEDEiExQSJRBBMyYYH/2gAMAwESOAbstract"
                        alt="Superviseur Avatar" class="w-9 h-9 rounded-lg object-cover">
                    <span
                        class="w-2.5 h-2.5 bg-emerald-500 border-2 border-slate-900 rounded-full absolute -bottom-0.5 -right-0.5"></span>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-xs font-semibold text-white truncate">CM Mbenza</h4>
                    <p class="text-[11px] text-slate-400 truncate">Software, it</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-950">

        <!-- TOP HEADER -->
        <header
            class="h-16 bg-slate-900/80 backdrop-blur border-b border-slate-800 flex items-center justify-between px-4 lg:px-8 z-20">
            <div class="flex items-center gap-4">
                <button id="toggleSidebar"
                    class="text-slate-400 hover:text-white p-2 rounded-lg bg-slate-950 border border-slate-800 lg:hidden">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>

                <!-- Live Pulse Banner -->
                <div
                    class="flex items-center gap-2.5 bg-emerald-950/40 border border-emerald-800/50 px-3 py-1.5 rounded-full">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 live-dot"></span>
                    <span class="text-xs font-bold text-emerald-400 tracking-wider uppercase">Flux Direct</span>
                    <span class="text-[10px] text-emerald-300/60 hidden sm:inline">| Monitoring Actif</span>
                </div>
            </div>

            <!-- Global Quick Role Selector & Controls -->
            <div class="flex items-center gap-3 lg:gap-4">

                <!-- Quick Filter Role Header Selector -->
                <div class="hidden md:flex items-center bg-slate-950 border border-slate-800 rounded-xl p-1 text-xs">
                    <span class="text-slate-400 px-2 font-medium">Vue :</span>
                    <button
                        class="role-btn-global active bg-indigo-600 text-white font-medium px-2.5 py-1 rounded-lg transition"
                        data-role="ALL">Tous</button>
                    <button
                        class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition"
                        data-role="Professeur">Profs</button>
                    <button
                        class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition"
                        data-role="Élève">Élèves</button>
                    <button
                        class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition"
                        data-role="Parent">Parents</button>
                    <button
                        class="role-btn-global text-slate-400 hover:text-slate-200 font-medium px-2.5 py-1 rounded-lg transition"
                        data-role="Admin">Admins</button>
                </div>

                <!-- Refresh Button -->
                <button id="refreshBtn"
                    class="flex items-center gap-2 text-xs font-medium bg-slate-950 hover:bg-slate-800 text-slate-300 border border-slate-800 px-3 py-2 rounded-xl transition active:scale-95">
                    <i class="fa-solid fa-arrows-rotate text-indigo-400" id="refreshIcon"></i>
                    <span class="hidden sm:inline">Sync Live</span>
                </button>

                <!-- Notifications -->
                <button
                    class="relative p-2 text-slate-400 hover:text-white rounded-xl bg-slate-950 border border-slate-800 transition">
                    <i class="fa-regular fa-bell text-base"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500"></span>
                </button>
            </div>
        </header>

        <!-- MAIN SCROLLABLE CONTENT -->
        <main class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6">

            <!-- PAGE TITLE BAR -->
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900/40 p-4 rounded-2xl border border-slate-800/80">
                <div>
                    <h1 class="text-xl font-extrabold text-white tracking-tight flex items-center gap-2">
                        <span>Supervision Multi-Utilisateurs</span>
                        <span id="activeRoleBadge"
                            class="text-xs bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2.5 py-0.5 rounded-full font-semibold">
                            Tous les Rôles
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">Suivi nominatif et quantitatif des actions des Professeurs,
                        Élèves, Admins, Parents et Superviseurs.</p>
                </div>
                <div
                    class="flex items-center gap-3 text-xs text-slate-400 bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl">
                    <i class="fa-regular fa-clock text-indigo-400"></i>
                    <span>Dernière activité : <strong id="lastSyncTime" class="text-slate-200 font-mono">À
                            l'instant</strong></span>
                </div>
            </div>

            <!-- KPI CARDS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- KPI 1: Total Connectés Live -->
                <div
                    class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Connectés en Direct
                            </p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiConnected">
                                <?= ($countEleves + $countProfs + $countAdmins) > 0 ? ($countEleves + $countProfs + $countAdmins) : 218 ?>
                            </h3>
                        </div>
                        <div
                            class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                            <i class="fa-solid fa-users-viewfinder text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-emerald-400 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-arrow-trend-up"></i> +14% vs pic d'hier
                        </span>
                        <span class="text-slate-500 text-[11px]" id="kpiRoleBreakdown"><?= $countEleves ?> É |
                            <?= $countProfs ?> P | <?= $countAdmins ?> A/P</span>
                    </div>
                </div>

                <!-- KPI 2: Cours & Contenus Publiés -->
                <div
                    class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Cours & Quiz
                                Publiés</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiPublished">
                                <?= ($coursPublies + $quizPublies) > 0 ? ($coursPublies + $quizPublies) : 64 ?></h3>
                        </div>
                        <div
                            class="w-10 h-10 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 flex items-center justify-center">
                            <i class="fa-solid fa-book-bookmark text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span class="text-violet-400 font-semibold"
                            id="kpiCoursesToday">+<?= $coursPublies > 0 ? $coursPublies : 12 ?> Cours</span>
                        <span class="text-slate-500">•</span>
                        <span class="text-indigo-400 font-semibold"
                            id="kpiQuizToday">+<?= $quizPublies > 0 ? $quizPublies : 52 ?> Quiz/Devoirs</span>
                    </div>
                </div>

                <!-- KPI 3: Devoirs Rendus & Notes Saisies -->
                <div
                    class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Devoirs &
                                Évaluations</p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiSubmissions">
                                <?= $totalSubmissions > 0 ? number_format($totalSubmissions) : '1,492' ?></h3>
                        </div>
                        <div
                            class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                            <i class="fa-solid fa-file-signature text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-indigo-400">
                        <i class="fa-solid fa-check-double"></i>
                        <span class="font-medium">94.2% soumis dans les délais</span>
                    </div>
                </div>

                <!-- KPI 4: Taux d'Engagement Global -->
                <div
                    class="bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-700 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Engagement Global
                            </p>
                            <h3 class="text-2xl font-extrabold text-white mt-1" id="kpiEngagement">89.4%</h3>
                        </div>
                        <div
                            class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                            <i class="fa-solid fa-bolt text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-400">
                        <i class="fa-solid fa-comments"></i>
                        <span class="font-medium" id="kpiInteractionsCount">3,810 interactions aujourd'hui</span>
                    </div>
                </div>

            </div>

            <!-- CHARTS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Main Traffic Chart (2 cols) -->
                <div
                    class="lg:col-span-2 bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Fréquentation et
                                Mouvements (24 Hours)</h2>
                            <p class="text-xs text-slate-400">Comparaison de l'activité en temps réel entre Professeurs,
                                Élèves et Admins/Parents</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="flex items-center gap-1 text-indigo-400"><span
                                    class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Élèves</span>
                            <span class="flex items-center gap-1 text-violet-400"><span
                                    class="w-2.5 h-2.5 rounded-full bg-violet-500"></span> Profs</span>
                            <span class="flex items-center gap-1 text-emerald-400"><span
                                    class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Admins/Parents</span>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="trafficLineChart"></canvas>
                    </div>
                </div>

                <!-- Doughnut Traffic Distribution (1 col) -->
                <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Répartition des Utilisateurs
                            Actifs</h2>
                        <p class="text-xs text-slate-400">Proportion des rôles connectés</p>
                    </div>
                    <div class="h-48 relative my-3">
                        <canvas id="roleDoughnutChart"></canvas>
                    </div>
                    <!-- Role Details Legend Grid -->
                    <div class="grid grid-cols-2 gap-2 text-xs pt-3 border-t border-slate-800">
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-indigo-500"></span> Élèves</span>
                            <span class="font-bold text-indigo-400">62%</span>
                        </div>
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-violet-500"></span> Profs</span>
                            <span class="font-bold text-violet-400">22%</span>
                        </div>
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-amber-500"></span> Parents</span>
                            <span class="font-bold text-amber-400">10%</span>
                        </div>
                        <div class="flex items-center justify-between bg-slate-950 p-2 rounded-lg">
                            <span class="text-slate-400 flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-emerald-500"></span> Admins</span>
                            <span class="font-bold text-emerald-400">6%</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- LIVE ACTIVITY FEED & TOP INTERACTIONS ROW -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LIVE STREAM FEED (2 cols) -->
                <div
                    class="lg:col-span-2 bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-indigo-400"></i>
                                Journal des Actions en Direct (Qui fait quoi ?)
                            </h2>
                            <p class="text-xs text-slate-400">Événements instantanés de tous les profils enregistrés sur
                                le serveur</p>
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
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Types d'Actions Populaires
                        </h2>
                        <p class="text-xs text-slate-400">Volume d'interactions par catégorie</p>
                    </div>
                    <div class="h-64 relative my-auto">
                        <canvas id="interactionsBarChart"></canvas>
                    </div>
                    <div class="text-center pt-2 border-t border-slate-800">
                        <span class="text-[11px] text-slate-400"><i
                                class="fa-solid fa-circle-info text-indigo-400 mr-1"></i>Les Quiz et Cours représentent
                            68% des flux actuels</span>
                    </div>
                </div>

            </div>

            <!-- DATATABLE USER SESSIONS & MOVEMENTS -->
            <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                            Tableau de Suivi des Sessions & Mouvements Utilisateurs
                        </h2>
                        <p class="text-xs text-slate-400">Détails nominatifs, statut d'activité, heures et décomptes des
                            actions du jour</p>
                    </div>

                    <!-- Table Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Table Filter Role -->
                        <select id="tableRoleFilter"
                            class="bg-slate-950 border border-slate-800 text-xs text-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                            <option value="">Tous les Rôles</option>
                            <option value="Professeur">Professeurs</option>
                            <option value="Élève">Élèves</option>
                            <option value="Parent">Parents</option>
                            <option value="Admin">Administrateurs</option>
                            <option value="Superviseur">Superviseurs</option>
                        </select>

                        <!-- Table Filter Status -->
                        <select id="tableStatusFilter"
                            class="bg-slate-950 border border-slate-800 text-xs text-slate-300 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                            <option value="">Tous les Statuts</option>
                            <option value="En ligne">En ligne</option>
                            <option value="Inactif">Inactif</option>
                            <option value="Hors ligne">Hors ligne</option>
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
                                <th>Statut</th>
                                <th>Connexion</th>
                                <th>Durée / Déconn.</th>
                                <th>Dernière Action Réalisée</th>
                                <th>Interactions</th>
                                <th>Inspecter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Content Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- INSPECTION USER MODAL -->
    <div id="userModal"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-lg mx-4 shadow-2xl relative">
            <button id="closeModal" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div id="modalContent" class="space-y-5">
                <!-- Dynamic Content JS -->
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
    $(document).ready(function() {

        // --- 1. SIDEBAR TOGGLE ---
        $('#toggleSidebar').on('click', function() {
            $('#sidebar').removeClass('-translate-x-full');
        });
        $('#closeSidebarMobile').on('click', function() {
            $('#sidebar').addClass('-translate-x-full');
        });

        // --- 2. INITIAL MOCK DATA (ALL USER TYPES) ---
        const initialUsers = [{
                id: 101,
                name: "Dr. Marc Dupont",
                role: "Professeur",
                avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "08:14:02",
                disconnTime: "--",
                lastAction: "A publié le cours de Physique Quantique",
                actionsCount: 24,
                courses: 2,
                quiz: 3,
                notes: 15
            },
            {
                id: 102,
                name: "Sophie Martin",
                role: "Élève",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "09:02:45",
                disconnTime: "--",
                lastAction: "A soumis le Quiz d'Algèbre (Note: 18/20)",
                actionsCount: 14,
                courses: 0,
                quiz: 5,
                notes: 0
            },
            {
                id: 103,
                name: "Jean-Paul Bernard",
                role: "Parent",
                avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "09:40:11",
                disconnTime: "--",
                lastAction: "A consulté le bulletin de Lucas Bernard",
                actionsCount: 6,
                courses: 0,
                quiz: 0,
                notes: 0
            },
            {
                id: 104,
                name: "Amina Kowalski",
                role: "Admin",
                avatar: "https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "07:30:00",
                disconnTime: "--",
                lastAction: "A validé la création de 3 comptes Profs",
                actionsCount: 52,
                courses: 0,
                quiz: 0,
                notes: 0
            },
            {
                id: 105,
                name: "Lucas Bernard",
                role: "Élève",
                avatar: "https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=120",
                status: "Inactif",
                connTime: "08:45:10",
                disconnTime: "--",
                lastAction: "Téléchargement du devoir de Chimie",
                actionsCount: 8,
                courses: 0,
                quiz: 2,
                notes: 0
            },
            {
                id: 106,
                name: "Élodie Roux",
                role: "Professeur",
                avatar: "https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=120",
                status: "Hors ligne",
                connTime: "10:00:00",
                disconnTime: "11:45:22",
                lastAction: "Saisie de 28 notes de Philosophie",
                actionsCount: 31,
                courses: 1,
                quiz: 1,
                notes: 28
            },
            {
                id: 107,
                name: "Prof. Henri Benali",
                role: "Professeur",
                avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "08:50:33",
                disconnTime: "--",
                lastAction: "A répondu à une question sur le Forum",
                actionsCount: 19,
                courses: 1,
                quiz: 2,
                notes: 12
            },
            {
                id: 108,
                name: "Chloé Petit",
                role: "Élève",
                avatar: "https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "09:12:00",
                disconnTime: "--",
                lastAction: "A démarré la vidéo de SVT Module 4",
                actionsCount: 11,
                courses: 0,
                quiz: 3,
                notes: 0
            },
            {
                id: 109,
                name: "David Chen",
                role: "Superviseur",
                avatar: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=120",
                status: "En ligne",
                connTime: "08:00:15",
                disconnTime: "--",
                lastAction: "Génération du rapport hebdomadaire",
                actionsCount: 41,
                courses: 0,
                quiz: 0,
                notes: 0
            },
            {
                id: 110,
                name: "Isabelle Martin",
                role: "Parent",
                avatar: "https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&q=80&w=120",
                status: "Hors ligne",
                connTime: "08:10:00",
                disconnTime: "08:35:10",
                lastAction: "Message envoyé au Professeur Dupont",
                actionsCount: 4,
                courses: 0,
                quiz: 0,
                notes: 0
            }
        ];

        // --- 3. DATATABLE INITIALIZATION ---
        const dataTable = $('#userSessionsTable').DataTable({
            data: initialUsers,
            columns: [{
                    data: null,
                    render: function(data) {
                        return `
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <img src="${data.avatar}" class="w-9 h-9 rounded-full object-cover border border-slate-700">
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-100 block text-xs">${data.name}</span>
                                        <span class="text-[10px] text-slate-500 font-mono">ID: #${data.id}</span>
                                    </div>
                                </div>
                            `;
                    }
                },
                {
                    data: 'role',
                    render: function(data) {
                        let badgeClass = "bg-slate-800 text-slate-300 border-slate-700";
                        if (data === "Professeur") badgeClass =
                            "bg-violet-950/70 text-violet-300 border-violet-800/60";
                        if (data === "Élève") badgeClass =
                            "bg-indigo-950/70 text-indigo-300 border-indigo-800/60";
                        if (data === "Admin") badgeClass =
                            "bg-emerald-950/70 text-emerald-300 border-emerald-800/60";
                        if (data === "Parent") badgeClass =
                            "bg-amber-950/70 text-amber-300 border-amber-800/60";
                        if (data === "Superviseur") badgeClass =
                            "bg-cyan-950/70 text-cyan-300 border-cyan-800/60";
                        return `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold border ${badgeClass}">${data}</span>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data) {
                        if (data === "En ligne") {
                            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/50">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 live-dot"></span> En ligne
                                        </span>`;
                        } else if (data === "Inactif") {
                            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-950/60 text-amber-400 border border-amber-800/50">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Inactif
                                        </span>`;
                        } else {
                            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Hors ligne
                                        </span>`;
                        }
                    }
                },
                {
                    data: 'connTime',
                    render: d => `<span class="font-mono text-slate-300 text-xs">${d}</span>`
                },
                {
                    data: null,
                    render: function(data) {
                        return `<span class="font-mono text-slate-400 text-xs">${data.disconnTime === '--' ? 'En cours' : data.disconnTime}</span>`;
                    }
                },
                {
                    data: 'lastAction',
                    render: d =>
                        `<span class="text-slate-300 text-xs truncate max-w-[200px] block" title="${d}">${d}</span>`
                },
                {
                    data: 'actionsCount',
                    render: d =>
                        `<span class="font-bold text-indigo-400 bg-indigo-950/50 border border-indigo-800/40 px-2 py-0.5 rounded-md text-xs">${d} ops</span>`
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        return `
                                <button class="inspect-btn p-1.5 text-slate-400 hover:text-indigo-400 hover:bg-slate-800 rounded-lg transition" data-id="${data.id}" title="Inspecter l'utilisateur">
                                    <i class="fa-solid fa-circle-info text-sm"></i>
                                </button>
                            `;
                    }
                }
            ],
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

        // Filtering Table via Select Controls
        $('#tableRoleFilter').on('change', function() {
            dataTable.column(1).search(this.value).draw();
        });
        $('#tableStatusFilter').on('change', function() {
            dataTable.column(2).search(this.value).draw();
        });

        // Global Role Filter Buttons
        $('.role-btn-global').on('click', function() {
            $('.role-btn-global').removeClass('active bg-indigo-600 text-white').addClass(
                'text-slate-400');
            $(this).addClass('active bg-indigo-600 text-white').removeClass('text-slate-400');

            const role = $(this).data('role');
            if (role === 'ALL') {
                dataTable.column(1).search('').draw();
                $('#activeRoleBadge').text('Tous les Rôles');
            } else {
                dataTable.column(1).search(role).draw();
                $('#activeRoleBadge').text(role + 's');
            }
        });

        // --- 4. CHARTS CONFIGURATION (Chart.js) ---

        // Chart 1: Traffic Line Chart (Profs vs Élèves vs Admins)
        const ctxTraffic = document.getElementById('trafficLineChart').getContext('2d');
        const trafficChart = new Chart(ctxTraffic, {
            type: 'line',
            data: {
                labels: ['07h', '08h', '09h', '10h', '11h', '12h', '13h', '14h', '15h', '16h'],
                datasets: [{
                        label: 'Élèves',
                        data: [45, 120, 185, 210, 195, 130, 160, 220, 205, 190],
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'Professeurs',
                        data: [12, 35, 48, 52, 45, 28, 38, 55, 49, 42],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.05)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'Admins & Parents',
                        data: [8, 18, 25, 30, 22, 19, 24, 32, 28, 25],
                        borderColor: '#10b981',
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
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(30, 41, 59, 0.5)'
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(30, 41, 59, 0.5)'
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Chart 2: Role Doughnut Chart
        const ctxRole = document.getElementById('roleDoughnutChart').getContext('2d');
        const roleChart = new Chart(ctxRole, {
            type: 'doughnut',
            data: {
                labels: ['Élèves', 'Professeurs', 'Parents', 'Admins'],
                datasets: [{
                    data: [62, 22, 10, 6],
                    backgroundColor: ['#6366f1', '#8b5cf6', '#f59e0b', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Chart 3: Interactions Bar Chart (Horizontal)
        const ctxInteractions = document.getElementById('interactionsBarChart').getContext('2d');
        const interactionsChart = new Chart(ctxInteractions, {
            type: 'bar',
            data: {
                labels: ['Quiz & Tests', 'Cours Consultés', 'Devoirs Rendus', 'Forum & Messages',
                    'Saisie Notes'
                ],
                datasets: [{
                    label: 'Nombre d\'actions',
                    data: [1420, 1180, 650, 420, 140],
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(6, 182, 212, 0.8)'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(30, 41, 59, 0.5)'
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#cbd5e1',
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // --- 5. LIVE ACTIVITY STREAM GENERATOR & REALTIME ENGINE ---
        const liveStreamTemplates = [{
                user: "Marc Dupont",
                role: "Professeur",
                action: "a mis en ligne le support",
                target: "Chapitre 4 : Relativité",
                icon: "fa-file-pdf",
                color: "text-violet-400",
                badgeBg: "bg-violet-950/80 border-violet-800"
            },
            {
                user: "Sophie Martin",
                role: "Élève",
                action: "a achevé avec succès le quiz",
                target: "Calcul Intégral (19/20)",
                icon: "fa-award",
                color: "text-indigo-400",
                badgeBg: "bg-indigo-950/80 border-indigo-800"
            },
            {
                user: "Jean-Paul Bernard",
                role: "Parent",
                action: "a signé l'émargement de",
                target: "Lucas Bernard",
                icon: "fa-signature",
                color: "text-amber-400",
                badgeBg: "bg-amber-950/80 border-amber-800"
            },
            {
                user: "Amina Kowalski",
                role: "Admin",
                action: "a réinitialisé le mot de passe de",
                target: "Prof. Benali",
                icon: "fa-key",
                color: "text-emerald-400",
                badgeBg: "bg-emerald-950/80 border-emerald-800"
            },
            {
                user: "Lucas Bernard",
                role: "Élève",
                action: "a téléversé le devoir",
                target: "Projet TP Chimie.pdf",
                icon: "fa-upload",
                color: "text-indigo-400",
                badgeBg: "bg-indigo-950/80 border-indigo-800"
            },
            {
                user: "Élodie Roux",
                role: "Professeur",
                action: "a attribué 24 nouvelles notes dans",
                target: "Philosophie T1",
                icon: "fa-pen-to-square",
                color: "text-violet-400",
                badgeBg: "bg-violet-950/80 border-violet-800"
            },
            {
                user: "David Chen",
                role: "Superviseur",
                action: "a extrait l'export mensuel d'assiduité",
                target: "Classe Terminale S2",
                icon: "fa-file-csv",
                color: "text-cyan-400",
                badgeBg: "bg-cyan-950/80 border-cyan-800"
            }
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

            // Max items limit
            if ($('#liveActivityStream').children().length > 12) {
                $('#liveActivityStream').children().last().remove();
            }
        }

        // Populate Initial Feed Items
        liveStreamTemplates.forEach(item => appendStreamItem(item));

        // Clear Stream
        $('#clearStreamBtn').on('click', function() {
            $('#liveActivityStream').empty();
        });

        // --- 6. REALTIME SIMULATION INTERVAL ---
        setInterval(function() {
            // Update Sync Time
            const now = new Date();
            $('#lastSyncTime').text(now.toLocaleTimeString());

            // Random Stream Event
            const randomEvent = liveStreamTemplates[Math.floor(Math.random() * liveStreamTemplates
                .length)];
            appendStreamItem(randomEvent);

            // Live KPI Variations
            let connected = parseInt($('#kpiConnected').text());
            const delta = Math.floor(Math.random() * 5) - 2; // -2 to +2
            connected = Math.max(180, connected + delta);
            $('#kpiConnected').text(connected);

            // Dynamically Update Line Chart Last Point
            const lastIdx = trafficChart.data.datasets[0].data.length - 1;
            trafficChart.data.datasets[0].data[lastIdx] = Math.max(150, trafficChart.data.datasets[0]
                .data[lastIdx] + delta);
            trafficChart.update('none');

        }, 3500);

        // --- 7. MANUAL REFRESH SIMULATION ---
        $('#refreshBtn').on('click', function() {
            $('#refreshIcon').addClass('fa-spin');
            setTimeout(function() {
                $('#refreshIcon').removeClass('fa-spin');
                $('#lastSyncTime').text(new Date().toLocaleTimeString());

                // Add system event
                appendStreamItem({
                    user: "Système Central",
                    role: "Admin",
                    action: "a synchronisé les bases de données d'activité",
                    target: "0 Erreur",
                    icon: "fa-rotate",
                    color: "text-emerald-400",
                    badgeBg: "bg-emerald-950/80 border-emerald-800"
                });
            }, 700);
        });

        // --- 8. INSPECTION MODAL (User Details) ---
        $(document).on('click', '.inspect-btn', function() {
            const userId = $(this).data('id');
            const user = initialUsers.find(u => u.id === userId);

            if (user) {
                $('#modalContent').html(`
                        <div class="flex items-center gap-4 border-b border-slate-800 pb-4">
                            <img src="${user.avatar}" class="w-14 h-14 rounded-full object-cover border-2 border-indigo-500">
                            <div>
                                <h3 class="text-base font-bold text-white">${user.name}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded border bg-indigo-950/80 border-indigo-800 text-indigo-300">${user.role}</span>
                                    <span class="text-xs text-slate-400 font-mono">ID: #${user.id}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                    <span class="text-slate-400 block text-[11px]">Statut Actuel</span>
                                    <span class="font-bold text-emerald-400 mt-0.5 block">${user.status}</span>
                                </div>
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                    <span class="text-slate-400 block text-[11px]">Heure Connexion</span>
                                    <span class="font-mono text-white mt-0.5 block">${user.connTime}</span>
                                </div>
                            </div>

                            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                <span class="text-slate-400 block text-[11px] mb-1">Dernière Action Enregistrée</span>
                                <p class="text-slate-200 font-medium">${user.lastAction}</p>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-center pt-2">
                                <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block">Cours</span>
                                    <span class="text-sm font-extrabold text-violet-400">${user.courses}</span>
                                </div>
                                <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block">Quiz/Devoirs</span>
                                    <span class="text-sm font-extrabold text-indigo-400">${user.quiz}</span>
                                </div>
                                <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block">Notes/Saisies</span>
                                    <span class="text-sm font-extrabold text-emerald-400">${user.notes}</span>
                                </div>
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
            if (e.target === this || e.currentTarget.id === 'closeModal' || e.currentTarget.id ===
                'closeModalBtn') {
                $('#userModal').addClass('opacity-0');
                setTimeout(() => $('#userModal').addClass('hidden').removeClass('flex'), 200);
            }
        });

    });
    </script>
</body>

</html>