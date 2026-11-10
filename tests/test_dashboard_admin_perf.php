<?php
/**
 * tests/test_dashboard_admin_perf.php
 * Tests that consolidated conditional aggregation query in admin dashboard service
 * returns exact identical financial totals as separate queries.
 */

declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup schema
$pdo->exec("
    CREATE TABLE paiement (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT NOT NULL,
        montant_paye REAL NOT NULL,
        statut TEXT NOT NULL,
        date_paiement TEXT NOT NULL
    );
");

// Insert sample data
$sampleData = [
    ['ECOLE1', 100.0, 'Inscription', '2025-01-10'],
    ['ECOLE1', 50.0, 'Inscription', '2025-01-15'],
    ['ECOLE1', 200.0, 'Minerval', '2025-01-20'],
    ['ECOLE1', 75.0, 'Minerval', '2025-02-01'],
    ['ECOLE1', 30.0, 'Autre', '2025-02-05'],
    ['ECOLE1', 45.0, 'Frais Divers', '2025-02-10'],
    ['ECOLE2', 999.0, 'Inscription', '2025-01-10'], // Diff school
];

$stmt = $pdo->prepare("INSERT INTO paiement (code_ecole, montant_paye, statut, date_paiement) VALUES (?, ?, ?, ?)");
foreach ($sampleData as $row) {
    $stmt->execute($row);
}

// 1. Separate queries (baseline)
$codeEcole = 'ECOLE1';

function fetch_scalar(PDO $pdo, string $sql, array $params=[], $def=0) {
    $st=$pdo->prepare($sql); $st->execute($params); $v=$st->fetchColumn();
    return ($v===false||$v===null)?$def:$v;
}

$old_inscription = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'",
    [':ce'=>$codeEcole], 0.0
);
$old_minerval = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'",
    [':ce'=>$codeEcole], 0.0
);
$old_autres = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0)
       FROM paiement
      WHERE code_ecole=:ce
        AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )",
    [':ce'=>$codeEcole], 0.0
);
$old_total = $old_inscription + $old_minerval + $old_autres;

// 2. Consolidated query (optimized)
$stFinance = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN statut = 'Inscription' THEN montant_paye ELSE 0 END), 0) AS somme_inscription,
        COALESCE(SUM(CASE WHEN statut = 'Minerval' THEN montant_paye ELSE 0 END), 0) AS somme_minerval,
        COALESCE(SUM(CASE WHEN (statut = 'Autre' OR (statut IS NOT NULL AND statut <> '' AND statut NOT IN ('Inscription', 'Minerval'))) THEN montant_paye ELSE 0 END), 0) AS somme_autres
    FROM paiement
    WHERE code_ecole = :ce
");
$stFinance->execute([':ce' => $codeEcole]);
$financeRow = $stFinance->fetch(PDO::FETCH_ASSOC) ?: [];

$new_inscription = (float)($financeRow['somme_inscription'] ?? 0.0);
$new_minerval    = (float)($financeRow['somme_minerval'] ?? 0.0);
$new_autres      = (float)($financeRow['somme_autres'] ?? 0.0);
$new_total       = $new_inscription + $new_minerval + $new_autres;

// Assertions
assert($old_inscription === 150.0, "Expected old_inscription 150, got $old_inscription");
assert($new_inscription === 150.0, "Expected new_inscription 150, got $new_inscription");
assert($old_minerval === 275.0, "Expected old_minerval 275, got $old_minerval");
assert($new_minerval === 275.0, "Expected new_minerval 275, got $new_minerval");
assert($old_autres === 75.0, "Expected old_autres 75, got $old_autres");
assert($new_autres === 75.0, "Expected new_autres 75, got $new_autres");
assert($old_total === 500.0, "Expected old_total 500, got $old_total");
assert($new_total === 500.0, "Expected new_total 500, got $new_total");

// Test with empty school
$stFinance->execute([':ce' => 'NON_EXISTENT']);
$emptyRow = $stFinance->fetch(PDO::FETCH_ASSOC) ?: [];
assert((float)($emptyRow['somme_inscription'] ?? 0.0) === 0.0);
assert((float)($emptyRow['somme_minerval'] ?? 0.0) === 0.0);
assert((float)($emptyRow['somme_autres'] ?? 0.0) === 0.0);

echo "[PASS] Dashboard admin financial query optimization verified with exact matching outputs.\n";
