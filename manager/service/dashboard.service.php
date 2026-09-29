<?php
// my_school/service/dashboard.service.php

 // display_errors handled in db_connect.php
 // display_startup_errors handled in db_connect.php
 // error_reporting handled in db_connect.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------- USER SESSION ----------
$sessionUser = $_SESSION['user'] ?? null;

if (empty($sessionUser) && empty($_SESSION['user_id'])) {
    header('Location: ../../login/index.php?msg=login_required');
    exit;
}

// ---------- ROLE CHECK ----------
$ROLE = strtolower($sessionUser['role'] ?? $_SESSION['role'] ?? '');
if ($ROLE !== 'manager') { // pour super admin
    header('Location: ../login/logout.php');
    exit;
}

// ---------- DB ----------
require_once __DIR__ . '/../../database/db_connect.php';

// ---------- HELPERS ----------
function fetch_scalar(PDO $pdo, string $sql, array $params = [], $default = 0) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $val = $st->fetchColumn();
    return ($val === false || $val === null) ? $default : $val;
}

function fetch_all(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// ---------- VARIABLES ----------
$eleves = $profs = $classes = $ecoles = 0;
$somme_inscription = $somme_minerval = $somme_autres = 0;
$total_general = 0;

$ecoles_par_statut = [
    'approuver' => 0,
    'en attente' => 0,
    'non approuver' => 0
];
$percent_statut = [];

$chart_labels = [];
$chart_inscription = [];
$chart_minerval = [];
$chart_autres = [];

$top_ecoles_labels = [];
$top_ecoles_values = [];

$recent_paiements = [];

try {

    // =============================
    // ⚡ STATS GLOBALES
    // =============================
    $sqlStats = "
        SELECT 
            (SELECT COUNT(*) FROM ecoles) AS total_ecoles,
            (SELECT COUNT(*) FROM students s JOIN ecoles e ON e.code_ecole = s.code_ecole) AS total_eleves,
            (SELECT COUNT(*) FROM teacher t JOIN ecoles e ON e.code_ecole = t.code_ecole) AS total_profs,
            (SELECT COUNT(*) FROM classes c JOIN ecoles e ON e.code_ecole = c.code_ecole) AS total_classes
    ";

    $stats = fetch_all($pdo, $sqlStats)[0] ?? [];

    $ecoles  = (int)($stats['total_ecoles'] ?? 0);
    $eleves  = (int)($stats['total_eleves'] ?? 0);
    $profs   = (int)($stats['total_profs'] ?? 0);
    $classes = (int)($stats['total_classes'] ?? 0);

    // =============================
    // 🏫 ECOLES PAR STATUT
    // =============================
    $sqlStatus = "SELECT statut, COUNT(*) as total FROM ecoles GROUP BY statut";
    $rowsStatus = fetch_all($pdo, $sqlStatus);

    // reset pour sécurité
    $ecoles_par_statut = [
        'approuver' => 0,
        'en attente' => 0,
        'non approuver' => 0
    ];

    foreach ($rowsStatus as $row) {
        $statut = strtolower(trim($row['statut']));

        if (strpos($statut, 'appr') !== false) {
            $ecoles_par_statut['approuver'] += (int)$row['total'];
        } elseif (strpos($statut, 'attente') !== false) {
            $ecoles_par_statut['en attente'] += (int)$row['total'];
        } else {
            $ecoles_par_statut['non approuver'] += (int)$row['total'];
        }
    }

    // pourcentage
    $total_statut = array_sum($ecoles_par_statut);
    foreach ($ecoles_par_statut as $k => $v) {
        $percent_statut[$k] = $total_statut > 0 ? round(($v / $total_statut) * 100, 2) : 0;
    }

    // =============================
    // 💰 REVENUS
    // =============================
    $sql = "
        SELECT 
            SUM(CASE WHEN p.statut='Inscription' THEN p.montant_paye ELSE 0 END) AS inscription,
            SUM(CASE WHEN p.statut='Minerval' THEN p.montant_paye ELSE 0 END) AS minerval,
            SUM(CASE WHEN p.statut NOT IN ('Inscription','Minerval') AND p.statut <> '' THEN p.montant_paye ELSE 0 END) AS autres
        FROM paiement p
        JOIN ecoles e ON e.code_ecole = p.code_ecole
    ";

    $row = fetch_all($pdo, $sql)[0] ?? [];
    $somme_inscription = (float)($row['inscription'] ?? 0);
    $somme_minerval    = (float)($row['minerval'] ?? 0);
    $somme_autres      = (float)($row['autres'] ?? 0);
    $total_general     = $somme_inscription + $somme_minerval + $somme_autres;

    // =============================
    // 📈 GRAPHIQUE 6 MOIS
    // =============================
    $sql6 = "
        SELECT DATE_FORMAT(p.date_paiement,'%Y-%m') AS ym,
               SUM(CASE WHEN p.statut='Inscription' THEN p.montant_paye ELSE 0 END) AS s_ins,
               SUM(CASE WHEN p.statut='Minerval' THEN p.montant_paye ELSE 0 END) AS s_min,
               SUM(CASE WHEN p.statut NOT IN ('Inscription','Minerval') AND p.statut<>'' THEN p.montant_paye ELSE 0 END) AS s_aut
        FROM paiement p
        JOIN ecoles e ON e.code_ecole = p.code_ecole
        WHERE p.date_paiement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
        GROUP BY ym
        ORDER BY ym ASC
    ";

    $rows6 = fetch_all($pdo, $sql6);

    $dt = new DateTime('first day of this month');
    $period = [];
    for ($i = 5; $i >= 0; $i--) {
        $period[] = (clone $dt)->modify("-$i month")->format('Y-m');
    }

    $map = [];
    foreach ($rows6 as $r) $map[$r['ym']] = $r;

    foreach ($period as $ym) {
        $chart_labels[]      = $ym;
        $chart_inscription[] = $map[$ym]['s_ins'] ?? 0;
        $chart_minerval[]    = $map[$ym]['s_min'] ?? 0;
        $chart_autres[]      = $map[$ym]['s_aut'] ?? 0;
    }

    // =============================
    // 🏆 TOP ECOLES
    // =============================
    $sqlTop = "SELECT e.nom_ecole, SUM(p.montant_paye) AS total
               FROM paiement p
               JOIN ecoles e ON e.code_ecole = p.code_ecole
               GROUP BY e.code_ecole
               ORDER BY total DESC
               LIMIT 5";

    $rowsTop = fetch_all($pdo, $sqlTop);
    foreach ($rowsTop as $r) {
        $top_ecoles_labels[] = $r['nom_ecole'];
        $top_ecoles_values[] = (float)$r['total'];
    }

    // =============================
    // 🧾 DERNIERS PAIEMENTS
    // =============================
    $sqlLast = "SELECT p.id, p.date_paiement, p.statut, p.montant_paye, e.nom_ecole
                FROM paiement p
                JOIN ecoles e ON e.code_ecole = p.code_ecole
                ORDER BY p.date_paiement DESC
                LIMIT 10";
    $recent_paiements = fetch_all($pdo, $sqlLast);

} catch (Throwable $e) {
    error_log("Dashboard error: " . $e->getMessage());
}