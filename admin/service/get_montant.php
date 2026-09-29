<?php
if (session_status() !== PHP_SESSION_ACTIVE) { 
    session_start(); 
}

header('Content-Type: application/json');

// Inclusion dynamique du fichier DB pour éviter les erreurs de chemin relatif
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(["montant_total" => 0, "total_paye" => 0, "solde_restant" => 0, "error" => "Connexion DB impossible"]);
    exit;
}

$codeEcole = $_SESSION['code_ecole'] ?? '';
$statut    = trim($_GET['statut'] ?? '');
$eleveId   = (int)($_GET['eleve'] ?? 0);

$response = [
    "montant_total" => 0,
    "total_paye"    => 0,
    "solde_restant" => 0
];

if (empty($codeEcole) || empty($statut) || $eleveId <= 0 || $statut === '#') {
    echo json_encode($response);
    exit;
}

try {
    // 1. Sélection de la table tarifaire
    if ($statut === 'Minerval') {
        $tarifTable = 'minerval';
    } elseif ($statut === 'Inscription') {
        $tarifTable = 'frais_d_inscription';
    } else {
        $tarifTable = 'autres_frais';
    }

    // 2. Recherche du montant basé sur la classe de l'élève
    // Tentative 1 : Correspondance directe par classe ID + Niveau + Section + Option
    $sql = "
        SELECT t.montant 
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN {$tarifTable} t ON t.code_ecole = s.code_ecole
        WHERE s.id = ? 
          AND s.code_ecole = ?
          AND (
              t.classe = c.id
              OR (
                  t.niveau = c.niveau 
                  AND t.section = IFNULL(c.section, 0) 
                  AND t.OPTION = IFNULL(c.options, 0)
              )
          )
        ORDER BY (t.classe = c.id) DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eleveId, $codeEcole]);
    $montantTotal = $stmt->fetchColumn();

    // Tentative 2 (Fallback) : Si pas de correspondance stricte, filtrer uniquement par niveau/section/option
    if ($montantTotal === false) {
        $sqlFallback = "
            SELECT t.montant 
            FROM students s
            JOIN classes c ON s.class_id = c.id
            JOIN {$tarifTable} t ON t.code_ecole = s.code_ecole AND t.niveau = c.niveau
            WHERE s.id = ? 
              AND s.code_ecole = ?
            LIMIT 1
        ";
        $stmtFallback = $pdo->prepare($sqlFallback);
        $stmtFallback->execute([$eleveId, $codeEcole]);
        $montantTotal = $stmtFallback->fetchColumn();
    }

    $response["montant_total"] = (float)($montantTotal ?: 0);

    // 3. Cumul déjà payé par l'élève pour ce statut
    $stmtPaye = $pdo->prepare("
        SELECT IFNULL(SUM(montant_paye), 0)
        FROM paiement
        WHERE eleve = ? 
          AND statut = ? 
          AND code_ecole = ?
    ");
    $stmtPaye->execute([$eleveId, $statut, $codeEcole]);
    $response["total_paye"] = (float)$stmtPaye->fetchColumn();

    // 4. Calcul du solde
    $solde = $response["montant_total"] - $response["total_paye"];
    $response["solde_restant"] = $solde > 0 ? $solde : 0;

} catch (PDOException $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);