<?php
require_once 'service/reports-statique-service.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'Administrateur';
$userRole = $_SESSION['role'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50 text-slate-800">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack 360 - Dashboard Supervision Global Live</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
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

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .dataTables_wrapper { color: #475569 !important; padding: 0.75rem 0; font-size: 0.8125rem; }
        .dataTables_length select, .dataTables_filter input {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #1e293b !important;
            border-radius: 0.5rem !important;
            padding: 0.4rem 0.75rem !important;
            outline: none;
        }
        .dataTables_length select:focus, .dataTables_filter input:focus { border-color: #6366f1 !important; }
        table.dataTable { border-collapse: collapse !important; width: 100% !important; border-bottom: 1px solid #e2e8f0 !important; }
        table.dataTable thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 12px 14px !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        table.dataTable tbody td { border-bottom: 1px solid #e2e8f0 !important; padding: 10px 14px !important; vertical-align: middle; }
        table.dataTable tbody tr { background-color: #ffffff !important; transition: background-color 0.15s ease; }
        table.dataTable tbody tr:hover { background-color: #f1f5f9 !important; }
        .dataTables_info, .dataTables_paginate { color: #64748b !important; margin-top: 0.75rem !important; }
        .paginate_button { color: #334155 !important; border-radius: 0.375rem !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; }
        .paginate_button.current, .paginate_button.current:hover { background: #4f46e5 !important; color: white !important; border-color: #4f46e5 !important; }

        @keyframes livePulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .live-dot { animation: livePulse 2s infinite; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-stream-item { animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col justify-between transition-all duration-300 z-30 fixed lg:relative h-full -translate-x-full lg:translate-x-0 shadow-sm">
        <div>
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-indigo-500/20">
                        <i class="fa-solid fa-radar"></i>
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-wide text-slate-900 block leading-none">EduTrack <span class="text-indigo-600">360</span></span>
                        <span class="text-[10px] text-slate-500 font-medium tracking-wider uppercase">Supervision Live</span>
                    </div>
                </div>
                <button id="closeSidebarMobile" class="lg:hidden text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <nav class="p-4 space-y-1.5">
                <a href="#" class="flex items-center gap-3 px-3.5 py-2.5 text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chart-line text-base w-5 text-center"></i>
                    <span>Vue Globale Live</span>
                </a>
                <a href="teacher.php" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-chalkboard-user text-base w-5 text-center"></i>
                    <span>Professeurs</span>
                </a>
                <a href="student.php" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-graduate text-base w-5 text-center"></i>
                    <span>Élèves</span>
                </a>
                <a href="parents.php" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-people-roof text-base w-5 text-center"></i>
                    <span>Parents</span>
                </a>
                <a href="settings.php" class="flex items-center gap-3 px-3.5 py-2.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl font-medium text-sm transition">
                    <i class="fa-solid fa-user-shield text-base w-5 text-center"></i>
                    <span>Admins & Config</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-center gap-3">
                <div class="relative">
                    <div class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                        <?= strtoupper(substr($userName, 0, 1)) ?>
                    </div>
                    <span class="w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full absolute -bottom-0.5 -right-0.5"></span>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-xs font-semibold text-slate-900 truncate"><?= h($userName) ?></h4>
                    <p class="text-[11px] text-slate-500 truncate"><?= h($userRole) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        
        <header class="h-16 bg-white/80 backdrop-blur border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 z-20">
            <div class="flex items-center gap-4">
                <button id="toggleSidebar" class="text-slate-600 hover:text-slate-900 p-2 rounded-lg bg-slate-100 border border-slate-200 lg:hidden">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>
                
                <div class="flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 live-dot"></span>
                    <span class="text-xs font-bold text-emerald-700 tracking-wider uppercase">Flux Direct</span>
                    <span class="text-[10px] text-emerald-600/80 hidden sm:inline">| <?= h($codeEcole) ?></span>
                </div>
            </div>

            <div class="flex items-center gap-3 lg:gap-4">
                <div class="hidden md:flex items-center bg-slate-100 border border-slate-200 rounded-xl p-1 text-xs">
                    <span class="text-slate-500 px-2 font-medium">Vue :</span>
                    <button class="role-btn-global active bg-white text-indigo-600 font-semibold shadow-sm px-2.5 py-1 rounded-lg transition" data-role="ALL">Tous</button>
                    <button class="role-btn-global text-slate-600 hover:text-indigo-600 font-medium px-2.5 py-1 rounded-lg transition" data-role="Professeur">Profs</button>
                    <button class="role-btn-global text-slate-600 hover:text-indigo-600 font-medium px-2.5 py-1 rounded-lg transition" data-role="Élève">Élèves</button>
                    <button class="role-btn-global text-slate-600 hover:text-indigo-600 font-medium px-2.5 py-1 rounded-lg transition" data-role="Admin">Admins</button>
                </div>

                <button id="refreshBtn" class="flex items-center gap-2 text-xs font-medium bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-3 py-2 rounded-xl transition shadow-sm active:scale-95">
                    <i class="fa-solid fa-arrows-rotate text-indigo-600" id="refreshIcon"></i>
                    <span class="hidden sm:inline">Sync Live</span>
                </button>

                <button class="relative p-2 text-slate-500 hover:text-slate-700 rounded-xl bg-white border border-slate-200 shadow-sm transition">
                    <i class="fa-regular fa-bell text-base"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-indigo-500"></span>
                </button>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div>
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Supervision Multi-Utilisateurs</span>
                        <span id="activeRoleBadge" class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-200 px-2.5 py-0.5 rounded-full font-semibold">
                            Tous les Rôles
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">Suivi nominatif et quantitatif des actions des Professeurs, Élèves et Admins.</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-600 bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl">
                    <i class="fa-regular fa-clock text-indigo-600"></i>
                    <span>Dernière activité : <strong id="lastSyncTime" class="text-slate-800 font-mono"><?= date('H:i:s') ?></strong></span>
                </div>
            </div>

            <!-- KPIS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <div class="bg-white border border-slate-200 p-4 rounded-2xl relative overflow-hidden shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Élèves Actifs</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= $nbre_eleve ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center">
                            <i class="fa-solid fa-user-graduate text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-emerald-600 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-check"></i> En ordre de statut
                        </span>
                        <span class="text-slate-400 text-[11px]"><?= $eleves_invalides ?> en attente</span>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-2xl relative overflow-hidden shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Cours & Quiz Publiés</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= ($total_cours + $total_quizzes) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-violet-50 border border-violet-200 text-violet-600 flex items-center justify-center">
                            <i class="fa-solid fa-book-bookmark text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span class="text-violet-600 font-semibold"><?= $total_cours ?> Cours</span>
                        <span class="text-slate-300">•</span>
                        <span class="text-indigo-600 font-semibold"><?= $total_quizzes ?> Quiz</span>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-2xl relative overflow-hidden shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Soumissions / Devoirs</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= $total_submissions ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center">
                            <i class="fa-solid fa-file-signature text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-indigo-600">
                        <i class="fa-solid fa-check-double"></i>
                        <span class="font-medium">Soumissions enregistrées</span>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 p-4 rounded-2xl relative overflow-hidden shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Solde Net Caisse</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1">$ <?= number_format($solde_net, 2, '.', ' ') ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center">
                            <i class="fa-solid fa-wallet text-base"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span class="font-medium"><?= $paiements_en_attente ?> à valider</span>
                    </div>
                </div>

            </div>

            <!-- CHARTS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Flux Financier (6 Derniers Mois)</h2>
                            <p class="text-xs text-slate-500">Comparatif des encaissements par catégorie et des dépenses</p>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="trafficLineChart"></canvas>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Présences Élèves</h2>
                        <p class="text-xs text-slate-500">Répartition des présences</p>
                    </div>
                    <div class="h-48 relative my-3">
                        <canvas id="roleDoughnutChart"></canvas>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs pt-3 border-t border-slate-100 text-center">
                        <div class="bg-slate-50 p-2 rounded-lg">
                            <span class="text-slate-500 block text-[10px]">Présents</span>
                            <span class="font-bold text-emerald-600"><?= $presence_stats['present'] ?? 0 ?></span>
                        </div>
                        <div class="bg-slate-50 p-2 rounded-lg">
                            <span class="text-slate-500 block text-[10px]">Absents</span>
                            <span class="font-bold text-rose-600"><?= $presence_stats['absent'] ?? 0 ?></span>
                        </div>
                        <div class="bg-slate-50 p-2 rounded-lg">
                            <span class="text-slate-500 block text-[10px]">Retards</span>
                            <span class="font-bold text-amber-600"><?= $presence_stats['retard'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- LIVE ACTIVITY STREAM & DATATABLE -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-3 bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-indigo-600"></i>
                                Journal des Activités Récentes (Chat & Messages)
                            </h2>
                            <p class="text-xs text-slate-500">Flux d'interactions de votre établissement</p>
                        </div>
                    </div>

                    <div id="liveActivityStream" class="space-y-2.5 max-h-[300px] overflow-y-auto pr-1">
                        <?php if (empty($recent_activities)): ?>
                            <p class="text-xs text-slate-400 py-4 text-center">Aucune activité récente enregistrée.</p>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $act): ?>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-comment text-xs"></i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <p class="text-xs text-slate-700 leading-snug truncate">
                                                <strong class="text-slate-900"><?= h($act['user']) ?></strong> 
                                                <span class="px-1.5 py-0.2 text-[9px] font-semibold border rounded bg-indigo-50 border-indigo-200 text-indigo-600 inline-block"><?= h($act['role']) ?></span>
                                                : <?= h($act['action']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono shrink-0"><?= h($act['time']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                            Tableau des Utilisateurs Enregistrés
                        </h2>
                        <p class="text-xs text-slate-500">Liste des profils rattachés à l'école</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="userSessionsTable" class="w-full text-left">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Créé à</th>
                                <th>Inspecter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Contenu injecté via JavaScript avec la variable $users_list -->
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODALE INSPECTION -->
    <div id="userModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl relative">
            <button id="closeModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div id="modalContent" class="space-y-5">
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {

            $('#toggleSidebar').on('click', function() { $('#sidebar').removeClass('-translate-x-full'); });
            $('#closeSidebarMobile').on('click', function() { $('#sidebar').addClass('-translate-x-full'); });

            // Injection des utilisateurs MySQL dans DataTables
            const usersData = <?= json_encode($users_list, JSON_UNESCAPED_UNICODE) ?>;

            const dataTable = $('#userSessionsTable').DataTable({
                data: usersData,
                columns: [
                    { 
                        data: null,
                        render: function(data) {
                            const name = (data.first_name || '') + ' ' + (data.last_name || data.username);
                            return `
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-600 font-bold flex items-center justify-center text-xs">
                                        ${name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block text-xs">${name}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">ID: #${data.id}</span>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    { data: 'email', render: d => `<span class="text-slate-600 text-xs font-mono">${d}</span>` },
                    { 
                        data: 'role',
                        render: function(data) {
                            let badgeClass = "bg-slate-100 text-slate-600 border-slate-200";
                            if(data === "prof") badgeClass = "bg-violet-50 text-violet-600 border-violet-200";
                            if(data === "eleve") badgeClass = "bg-indigo-50 text-indigo-600 border-indigo-200";
                            if(data === "admin") badgeClass = "bg-emerald-50 text-emerald-600 border-emerald-200";
                            return `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold border ${badgeClass}">${data}</span>`;
                        }
                    },
                    { data: 'connTime', render: d => `<span class="font-mono text-slate-600 text-xs">${d}</span>` },
                    { 
                        data: null,
                        orderable: false,
                        render: function(data) {
                            return `
                                <button class="inspect-btn p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" data-id="${data.id}">
                                    <i class="fa-solid fa-circle-info text-sm"></i>
                                </button>
                            `;
                        }
                    }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Rechercher...",
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

            // Graphique des Finances (6 mois)
            const ctxTraffic = document.getElementById('trafficLineChart').getContext('2d');
            new Chart(ctxTraffic, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels ?? []) ?>,
                    datasets: [
                        {
                            label: 'Inscriptions',
                            data: <?= json_encode($chart_inscription ?? []) ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            fill: true, tension: 0.3
                        },
                        {
                            label: 'Minerval',
                            data: <?= json_encode($chart_minerval ?? []) ?>,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.05)',
                            fill: true, tension: 0.3
                        },
                        {
                            label: 'Dépenses',
                            data: <?= json_encode($chart_depenses ?? []) ?>,
                            borderColor: '#ef4444',
                            borderDash: [5, 5],
                            fill: false, tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
                        y: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b', callback: v => '$ ' + v } }
                    }
                }
            });

            // Graphique Donut Présences
            const ctxRole = document.getElementById('roleDoughnutChart').getContext('2d');
            const presenceData = <?= json_encode($presence_stats ?? ['present'=>0, 'absent'=>0, 'retard'=>0]) ?>;
            new Chart(ctxRole, {
                type: 'doughnut',
                data: {
                    labels: ['Présents', 'Absents', 'Retards'],
                    datasets: [{
                        data: [presenceData.present, presenceData.absent, presenceData.retard],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { display: false } }
                }
            });

            // Bouton de rafraîchissement
            $('#refreshBtn').on('click', function() {
                $('#refreshIcon').addClass('fa-spin');
                setTimeout(() => { location.reload(); }, 600);
            });

            // Modale d'inspection
            $(document).on('click', '.inspect-btn', function() {
                const userId = $(this).data('id');
                const u = usersData.find(item => item.id == userId);

                if (u) {
                    const name = (u.first_name || '') + ' ' + (u.last_name || u.username);
                    $('#modalContent').html(`
                        <div class="flex items-center gap-4 border-b border-slate-100 pb-4">
                            <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 font-bold flex items-center justify-center text-lg">
                                ${name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">${name}</h3>
                                <p class="text-xs text-slate-500 font-mono">${u.email}</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block text-[11px]">Rôle</span>
                                    <span class="font-bold text-indigo-600 uppercase">${u.role}</span>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block text-[11px]">Heure Création</span>
                                    <span class="font-mono text-slate-800">${u.connTime}</span>
                                </div>
                            </div>
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button id="closeModalBtn" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-4 py-2 rounded-xl transition">
                                Fermer
                            </button>
                        </div>
                    `);

                    $('#userModal').removeClass('hidden').addClass('flex');
                    setTimeout(() => $('#userModal').removeClass('opacity-0'), 10);
                }
            });

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