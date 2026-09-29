<?php
// my_school/service/dashboard.service.php

// ========= MODE DEV : afficher les erreurs (à enlever en prod) =========
 // display_errors handled in db_connect.php
 // display_startup_errors handled in db_connect.php
 // error_reporting handled in db_connect.php

// ---------- Session ----------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------- Détection utilisateur en session ----------
$sessionUser = null;

if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $sessionUser = $_SESSION['user'];
} elseif (!empty($_SESSION['user_id'])) {
    $sessionUser = [
        'id'         => (int) ($_SESSION['user_id'] ?? 0),
        'role'       => $_SESSION['role']       ?? null,
        'email'      => $_SESSION['email']      ?? null,
        'first_name' => $_SESSION['first_name'] ?? null,
    ];
}

// ---------- Si pas connecté, redirige correctement ----------
if (empty($sessionUser['id'])) {
    // IMPORTANT: chemin correct depuis /my_school/service/ vers /login/index.php
    $loginUrl = '../login/logout.php';
    if (!headers_sent()) {
        header('Location: ' . $loginUrl);
        exit;
    } else {
        echo '<p>Redirection : <a href="'.htmlspecialchars($loginUrl).'">Aller au login</a></p>';
        exit;
    }
}

$PROMO_ID = (int)$sessionUser['id'];
$ROLE     = $sessionUser['role'] ?? null;

// ---------- Garde d’accès : uniquement promoteur ----------
if (strtolower((string)$ROLE) !== 'promoteur') {
    $loginUrl = '../login/logout.php';
    if (!headers_sent()) {
        header('Location: ' . $loginUrl);
        exit;
    } else {
        echo '<p>Accès refusé. <a href="'.htmlspecialchars($loginUrl).'">Se reconnecter</a></p>';
        exit;
    }
}

// ---------- Connexion BDD ----------
require_once __DIR__ . '/../../database/db_connect.php'; // Doit définir $pdo (PDO)

// Valeurs par défaut
$eleves = $profs = $classes = $ecoles = 0;
$somme_inscription = $somme_minerval = $somme_autres = 0.0;
$total_general = 0.0;

// Données pour graphiques
$chart_labels = [];          // ex: ["2025-06","2025-07", ...]
$chart_inscription = [];
$chart_minerval = [];
$chart_autres = [];

$top_ecoles_labels = [];     // ex: ["ELMA SOMBE", "CSEL", ...]
$top_ecoles_values = [];     // ex: [1234.50, 875.20, ...]
$recent_paiements   = [];    // liste des 10 derniers paiements

// Helper scalaire
function fetch_scalar(PDO $pdo, string $sql, array $params = [], $default = 0) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $val = $st->fetchColumn();
    return ($val === false || $val === null) ? $default : $val;
}

// Helper liste
function fetch_all(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

try {
    // 1) Nombre d’écoles du promoteur
    $ecoles = (int) fetch_scalar(
        $pdo,
        "SELECT COUNT(*) FROM ecoles WHERE id_promoteur = :id",
        [':id' => $PROMO_ID],
        0
    );

    // 2) Élèves (toutes écoles du promoteur)
    $eleves = (int) fetch_scalar(
        $pdo,
        "SELECT COUNT(*)
           FROM students s
           JOIN ecoles e ON e.code_ecole = s.code_ecole
          WHERE e.id_promoteur = :id",
        [':id' => $PROMO_ID],
        0
    );

    // 3) Enseignants
    $profs = (int) fetch_scalar(
        $pdo,
        "SELECT COUNT(*)
           FROM teacher t
           JOIN ecoles e ON e.code_ecole = t.code_ecole
          WHERE e.id_promoteur = :id",
        [':id' => $PROMO_ID],
        0
    );

    // 4) Classes
    $classes = (int) fetch_scalar(
        $pdo,
        "SELECT COUNT(*)
           FROM classes c
           JOIN ecoles e ON e.code_ecole = c.code_ecole
          WHERE e.id_promoteur = :id",
        [':id' => $PROMO_ID],
        0
    );

    // 5a) Inscription
    $somme_inscription = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(p.montant_paye), 0)
           FROM paiement p
           JOIN ecoles  e ON e.code_ecole = p.code_ecole
          WHERE e.id_promoteur = :id
            AND p.statut = 'Inscription'",
        [':id' => $PROMO_ID],
        0.0
    );

    // 5b) Minerval
    $somme_minerval = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(p.montant_paye), 0)
           FROM paiement p
           JOIN ecoles  e ON e.code_ecole = p.code_ecole
          WHERE e.id_promoteur = :id
            AND p.statut = 'Minerval'",
        [':id' => $PROMO_ID],
        0.0
    );

    // 5c) Autres frais
    $somme_autres = (float) fetch_scalar(
        $pdo,
        "SELECT COALESCE(SUM(p.montant_paye), 0)
           FROM paiement p
           JOIN ecoles  e ON e.code_ecole = p.code_ecole
          WHERE e.id_promoteur = :id
            AND (
                  p.statut = 'Autre'
               OR (p.statut IS NOT NULL AND p.statut <> '' AND p.statut NOT IN ('Inscription','Minerval'))
            )",
        [':id' => $PROMO_ID],
        0.0
    );

    // 5d) Total cohérent
    $total_general = $somme_inscription + $somme_minerval + $somme_autres;

    // =========================
    //   GRAPHIQUES - ANALYTIQUE
    // =========================

    // A) Encaissements des 6 derniers mois (par statut)
    // On prend YYYY-MM depuis NOW() -5 mois jusqu’à NOW()
    $sql6 = "
      SELECT DATE_FORMAT(p.date_paiement,'%Y-%m') AS ym,
             SUM(CASE WHEN p.statut='Inscription' THEN p.montant_paye ELSE 0 END) AS s_ins,
             SUM(CASE WHEN p.statut='Minerval'    THEN p.montant_paye ELSE 0 END) AS s_min,
             SUM(CASE WHEN p.statut NOT IN ('Inscription','Minerval') AND p.statut<>'' THEN p.montant_paye ELSE 0 END) AS s_aut
        FROM paiement p
        JOIN ecoles e ON e.code_ecole = p.code_ecole
       WHERE e.id_promoteur = :id
         AND p.date_paiement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
       GROUP BY ym
       ORDER BY ym ASC
    ";
    $rows6 = fetch_all($pdo, $sql6, [':id'=>$PROMO_ID]);

    // Préparer tableaux complets pour 6 mois (remplir 0 si mois manquant)
    $period = [];
    $dt = new DateTime('first day of this month');
    for ($i=5; $i>=0; $i--) {
        $p = (clone $dt)->modify("-$i month")->format('Y-m');
        $period[] = $p;
    }
    $map6 = [];
    foreach ($rows6 as $r) {
        $map6[$r['ym']] = $r;
    }
    foreach ($period as $ym) {
        $chart_labels[]    = $ym;
        $chart_inscription[] = isset($map6[$ym]) ? (float)$map6[$ym]['s_ins'] : 0.0;
        $chart_minerval[]    = isset($map6[$ym]) ? (float)$map6[$ym]['s_min'] : 0.0;
        $chart_autres[]      = isset($map6[$ym]) ? (float)$map6[$ym]['s_aut'] : 0.0;
    }

    // B) Top 5 écoles par encaissements (tous statuts)
    $sqlTop = "
      SELECT e.nom_ecole, e.code_ecole, ROUND(SUM(p.montant_paye),2) AS total_ecole
        FROM paiement p
        JOIN ecoles e ON e.code_ecole = p.code_ecole
       WHERE e.id_promoteur = :id
       GROUP BY e.code_ecole
       ORDER BY total_ecole DESC
       LIMIT 5
    ";
    $rowsTop = fetch_all($pdo, $sqlTop, [':id'=>$PROMO_ID]);
    foreach ($rowsTop as $r) {
        $top_ecoles_labels[] = $r['nom_ecole'] ?: $r['code_ecole'];
        $top_ecoles_values[] = (float)$r['total_ecole'];
    }

    // C) 10 derniers paiements
    $sqlLast = "
      SELECT p.id,
             p.date_paiement,
             p.statut,
             p.montant_paye,
             e.nom_ecole,
             e.code_ecole
        FROM paiement p
        JOIN ecoles e ON e.code_ecole = p.code_ecole
       WHERE e.id_promoteur = :id
       ORDER BY p.date_paiement DESC, p.id DESC
       LIMIT 10
    ";
    $recent_paiements = fetch_all($pdo, $sqlLast, [':id'=>$PROMO_ID]);

} catch (Throwable $e) {
    // En cas d’erreur, on garde la page fonctionnelle et on log
    error_log('Dashboard service error: '.$e->getMessage());
    // Valeurs safe
    $somme_inscription = $somme_minerval = $somme_autres = 0.0;
    $total_general = 0.0;
    $chart_labels = []; $chart_inscription = []; $chart_minerval = []; $chart_autres = [];
    $top_ecoles_labels = []; $top_ecoles_values = [];
    $recent_paiements = [];
}
