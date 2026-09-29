<?php
// admin/service/dashboard-admin.php
// Compteurs/scopes du dashboard admin (par école), + séries pour graphiques

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
    } catch (Throwable $e) {
        // non bloquant, on gérera plus bas
    }
}

// Valeurs par défaut si pas d’école
$nbre_classe = 0;
$nbre_prof   = 0;
$nbre_eleve  = 0;
$finance_total = 0.0;

$somme_inscription = 0.0;
$somme_minerval    = 0.0;
$somme_autres      = 0.0;

// Graphs
$chart_labels       = [];
$chart_inscription  = [];
$chart_minerval     = [];
$chart_autres       = [];

// Helpers
function fetch_scalar(PDO $pdo, string $sql, array $params=[], $def=0) {
    $st=$pdo->prepare($sql); $st->execute($params); $v=$st->fetchColumn();
    return ($v===false||$v===null)?$def:$v;
}
function fetch_all(PDO $pdo, string $sql, array $params=[]): array {
    $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($codeEcole === '') {
    // aucune école associée => on laisse les chiffres à 0
    return;
}

try {
    // KPIs
    $nbre_classe = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM classes  WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
    $nbre_prof   = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM teacher  WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
    $nbre_eleve  = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM students WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);

    // Finances — ventilation
    $somme_inscription = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'",
        [':ce'=>$codeEcole], 0.0
    );
    $somme_minerval = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'",
        [':ce'=>$codeEcole], 0.0
    );
    $somme_autres = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(montant_paye),0)
           FROM paiement
          WHERE code_ecole=:ce
            AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )",
        [':ce'=>$codeEcole], 0.0
    );
    $finance_total = $somme_inscription + $somme_minerval + $somme_autres;

    // Séries 6 derniers mois
    $rows6 = fetch_all(
        $pdo,
        "SELECT DATE_FORMAT(date_paiement,'%Y-%m') AS ym,
                SUM(CASE WHEN statut='Inscription' THEN montant_paye ELSE 0 END) AS s_ins,
                SUM(CASE WHEN statut='Minerval'    THEN montant_paye ELSE 0 END) AS s_min,
                SUM(CASE WHEN statut NOT IN('Inscription','Minerval') AND statut<>'' THEN montant_paye ELSE 0 END) AS s_aut
           FROM paiement
          WHERE code_ecole = :ce
            AND date_paiement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
          GROUP BY ym
          ORDER BY ym ASC",
        [':ce'=>$codeEcole]
    );

    // Construire la période complète (même si des mois sont vides)
    $period=[]; $now = new DateTime('first day of this month');
    for ($i=5;$i>=0;$i--) { $period[] = (clone $now)->modify("-$i month")->format('Y-m'); }
    $map=[]; foreach($rows6 as $r){ $map[$r['ym']] = $r; }

    foreach($period as $ym){
        $chart_labels[]      = $ym;
        $chart_inscription[] = isset($map[$ym]) ? (float)$map[$ym]['s_ins'] : 0.0;
        $chart_minerval[]    = isset($map[$ym]) ? (float)$map[$ym]['s_min'] : 0.0;
        $chart_autres[]      = isset($map[$ym]) ? (float)$map[$ym]['s_aut'] : 0.0;
    }
} catch (Throwable $e) {
    // On loggue et on garde des valeurs neutres
    error_log('Dashboard admin service error: '.$e->getMessage());
    $nbre_classe = $nbre_prof = $nbre_eleve = 0;
    $somme_inscription = $somme_minerval = $somme_autres = 0.0;
    $finance_total = 0.0;
    $chart_labels = $chart_inscription = $chart_minerval = $chart_autres = [];
}

// Expose en global (utilisés par dashboard.php)
$GLOBALS['nbre_classe']        = $nbre_classe;
$GLOBALS['nbre_prof']          = $nbre_prof;
$GLOBALS['nbre_eleve']         = $nbre_eleve;
$GLOBALS['finance_total']      = $finance_total;

$GLOBALS['somme_inscription']  = $somme_inscription;
$GLOBALS['somme_minerval']     = $somme_minerval;
$GLOBALS['somme_autres']       = $somme_autres;

$GLOBALS['chart_labels']       = $chart_labels;
$GLOBALS['chart_inscription']  = $chart_inscription;
$GLOBALS['chart_minerval']     = $chart_minerval;
$GLOBALS['chart_autres']       = $chart_autres;
