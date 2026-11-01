<?php
// admin/service/reports-statique-service.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur','promoteur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

$userId    = (int)($_SESSION['user_id'] ?? 0);
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');

if ($codeEcole === '' && $userId > 0) {
    try {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $userId]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) { $_SESSION['code_ecole'] = $codeEcole; }
    } catch (Throwable $e) {}
}

// Variables globales et KPIs par défaut
$nbre_classe = $nbre_prof = $nbre_eleve = 0;
$finance_total = $somme_inscription = $somme_minerval = $somme_autres = 0.0;
$total_depenses = 0.0;
$solde_net = 0.0;
$eleves_invalides = 0;
$paiements_en_attente = 0;
$presence_stats = ['present' => 0, 'absent' => 0, 'retard' => 0];

$total_quizzes = 0;
$total_submissions = 0;
$total_cours = 0;

$users_list = [];
$recent_activities = [];

$chart_labels = $chart_inscription = $chart_minerval = $chart_autres = $chart_depenses = [];

function fetch_scalar(PDO $pdo, string $sql, array $params=[], $def=0) {
    $st = $pdo->prepare($sql); $st->execute($params); $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $def : $v;
}

function fetch_all(PDO $pdo, string $sql, array $params=[]): array {
    $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($codeEcole !== '') {
    try {
        // 1. Compteurs principaux
        $nbre_classe      = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM classes WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $nbre_prof        = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM teacher WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $nbre_eleve       = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM students WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $eleves_invalides = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM students WHERE code_ecole = :ce AND statut='invalide'", [':ce'=>$codeEcole], 0);
        
        // 2. Quiz, Cours et Devoirs
        $total_quizzes     = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM quizzes WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $total_submissions = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM quiz_submissions WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);
        $total_cours       = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM cours WHERE code_ecole = :ce", [':ce'=>$codeEcole], 0);

        // 3. Finances
        $somme_inscription = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'", [':ce'=>$codeEcole], 0.0);
        $somme_minerval    = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'", [':ce'=>$codeEcole], 0.0);
        $somme_autres      = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND (statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')))", [':ce'=>$codeEcole], 0.0);
        $finance_total     = $somme_inscription + $somme_minerval + $somme_autres;
        
        $total_depenses       = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant),0) FROM depenses WHERE code_ecole=:ce", [':ce'=>$codeEcole], 0.0);
        $solde_net            = $finance_total - $total_depenses;
        $paiements_en_attente = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM paiement WHERE code_ecole=:ce AND is_validated=0", [':ce'=>$codeEcole], 0);

        // 4. Présences
        $rows_pres = fetch_all($pdo, "SELECT p.statut, COUNT(*) as cnt FROM presence_eleve p JOIN students s ON p.eleve_id = s.id WHERE s.code_ecole = :ce GROUP BY p.statut", [':ce'=>$codeEcole]);
        foreach($rows_pres as $rp) {
            if (isset($presence_stats[$rp['statut']])) {
                $presence_stats[$rp['statut']] = (int)$rp['cnt'];
            }
        }

        // 5. Historique Financier sur 6 mois
        $rows6 = fetch_all($pdo, "SELECT DATE_FORMAT(date_paiement,'%Y-%m') AS ym,
                SUM(CASE WHEN statut='Inscription' THEN montant_paye ELSE 0 END) AS s_ins,
                SUM(CASE WHEN statut='Minerval'    THEN montant_paye ELSE 0 END) AS s_min,
                SUM(CASE WHEN statut NOT IN('Inscription','Minerval') AND statut<>'' THEN montant_paye ELSE 0 END) AS s_aut
           FROM paiement
          WHERE code_ecole = :ce
            AND date_paiement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
          GROUP BY ym ORDER BY ym ASC", [':ce'=>$codeEcole]);

        $rows_dep6 = fetch_all($pdo, "SELECT DATE_FORMAT(date_depense,'%Y-%m') AS ym, SUM(montant) as s_dep FROM depenses WHERE code_ecole = :ce AND date_depense >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') GROUP BY ym", [':ce'=>$codeEcole]);
        $map_dep = []; foreach($rows_dep6 as $rd) { $map_dep[$rd['ym']] = (float)$rd['s_dep']; }

        $period = []; $now = new DateTime('first day of this month');
        for ($i=5;$i>=0;$i--) { $period[] = (clone $now)->modify("-$i month")->format('Y-m'); }
        $map = []; foreach($rows6 as $r){ $map[$r['ym']] = $r; }

        foreach($period as $ym){
            $chart_labels[]      = $ym;
            $chart_inscription[] = isset($map[$ym]) ? (float)$map[$ym]['s_ins'] : 0.0;
            $chart_minerval[]    = isset($map[$ym]) ? (float)$map[$ym]['s_min'] : 0.0;
            $chart_autres[]      = isset($map[$ym]) ? (float)$map[$ym]['s_aut'] : 0.0;
            $chart_depenses[]    = $map_dep[$ym] ?? 0.0;
        }

        // 6. Liste dynamique des Utilisateurs (Table users)
        $users_list = fetch_all($pdo, "SELECT id, username, first_name, last_name, role, email, DATE_FORMAT(created_at, '%H:%i:%s') as connTime FROM users WHERE code_ecole = :ce ORDER BY id DESC LIMIT 50", [':ce'=>$codeEcole]);

        // 7. Derniers messages du chat (Flux d'activités en direct)
        $recent_activities = fetch_all($pdo, "SELECT sender_name as user, sender_role as role, message as action, DATE_FORMAT(created_at, '%H:%i:%s') as time FROM chat_messages WHERE code_ecole = :ce ORDER BY id DESC LIMIT 10", [':ce'=>$codeEcole]);

    } catch (Throwable $e) {
        error_log('Dashboard admin service error: '.$e->getMessage());
    }
}

// Variables globales transmises à la vue HTML
$GLOBALS['nbre_classe']          = $nbre_classe;
$GLOBALS['nbre_prof']            = $nbre_prof;
$GLOBALS['nbre_eleve']           = $nbre_eleve;
$GLOBALS['eleves_invalides']     = $eleves_invalides;
$GLOBALS['total_quizzes']        = $total_quizzes;
$GLOBALS['total_submissions']    = $total_submissions;
$GLOBALS['total_cours']          = $total_cours;
$GLOBALS['finance_total']        = $finance_total;
$GLOBALS['total_depenses']       = $total_depenses;
$GLOBALS['solde_net']            = $solde_net;
$GLOBALS['paiements_en_attente'] = $paiements_en_attente;
$GLOBALS['presence_stats']       = $presence_stats;
$GLOBALS['users_list']           = $users_list;
$GLOBALS['recent_activities']    = $recent_activities;

$GLOBALS['somme_inscription']    = $somme_inscription;
$GLOBALS['somme_minerval']       = $somme_minerval;
$GLOBALS['somme_autres']         = $somme_autres;

$GLOBALS['chart_labels']         = $chart_labels;
$GLOBALS['chart_inscription']    = $chart_inscription;
$GLOBALS['chart_minerval']       = $chart_minerval;
$GLOBALS['chart_autres']         = $chart_autres;
$GLOBALS['chart_depenses']       = $chart_depenses;