<?php
declare(strict_types=1);

/**
 * Unit & Benchmark Test for Admin Dashboard Financial Aggregations
 * Verifies that the consolidated CASE WHEN query matches the legacy multi-query calculations
 * across multiple edge cases (standard categories, custom categories, empty status, NULL, no records).
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE classes (id INTEGER PRIMARY KEY, code_ecole TEXT)");
$pdo->exec("CREATE TABLE teacher (id INTEGER PRIMARY KEY, code_ecole TEXT)");
$pdo->exec("CREATE TABLE students (id INTEGER PRIMARY KEY, code_ecole TEXT)");
$pdo->exec("CREATE TABLE paiement (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code_ecole TEXT,
    statut TEXT,
    montant_paye REAL,
    date_paiement TEXT
)");
$pdo->exec("CREATE INDEX idx_paiement_ce ON paiement(code_ecole)");

function fetch_scalar(PDO $pdo, string $sql, array $params = [], $def = 0) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $def : $v;
}

// 1. Edge Case: Empty Table
$codeEcole = 'ECOLE_EMPTY';
$finStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN statut = 'Inscription' THEN montant_paye ELSE 0 END), 0) AS somme_inscription,
        COALESCE(SUM(CASE WHEN statut = 'Minerval' THEN montant_paye ELSE 0 END), 0) AS somme_minerval,
        COALESCE(SUM(CASE WHEN (statut = 'Autre' OR (statut IS NOT NULL AND statut <> '' AND statut NOT IN ('Inscription', 'Minerval'))) THEN montant_paye ELSE 0 END), 0) AS somme_autres
    FROM paiement
    WHERE code_ecole = :ce
");
$finStmt->execute([':ce' => $codeEcole]);
$finRow = $finStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$somme_inscription = (float)($finRow['somme_inscription'] ?? 0.0);
$somme_minerval    = (float)($finRow['somme_minerval'] ?? 0.0);
$somme_autres      = (float)($finRow['somme_autres'] ?? 0.0);
$finance_total     = $somme_inscription + $somme_minerval + $somme_autres;

assert($somme_inscription === 0.0, 'Empty DB: Inscription should be 0.0');
assert($somme_minerval === 0.0, 'Empty DB: Minerval should be 0.0');
assert($somme_autres === 0.0, 'Empty DB: Autres should be 0.0');
assert($finance_total === 0.0, 'Empty DB: Total should be 0.0');
echo "[PASS] Test empty dataset aggregation\n";

// 2. Edge Case: Mixed categories, custom categories, empty string, and NULL status
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES
('ECOLE_1', 'Inscription', 150.50, '2025-05-01'),
('ECOLE_1', 'Inscription', 50.00, '2025-05-02'),
('ECOLE_1', 'Minerval', 300.25, '2025-05-03'),
('ECOLE_1', 'Autre', 75.00, '2025-05-04'),
('ECOLE_1', 'Cantine Scolaire', 120.00, '2025-05-05'),
('ECOLE_1', 'Uniforme', 80.00, '2025-05-06'),
('ECOLE_1', '', 999.00, '2025-05-07'),
('ECOLE_1', NULL, 888.00, '2025-05-08'),
('ECOLE_2', 'Inscription', 500.00, '2025-05-01')
");

$codeEcole = 'ECOLE_1';

// Legacy multi-query logic
$legacy_inscription = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'",
    [':ce' => $codeEcole], 0.0
);
$legacy_minerval = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'",
    [':ce' => $codeEcole], 0.0
);
$legacy_autres = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0)
       FROM paiement
      WHERE code_ecole=:ce
        AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )",
    [':ce' => $codeEcole], 0.0
);
$legacy_total = $legacy_inscription + $legacy_minerval + $legacy_autres;

// Optimized consolidated query
$finStmt->execute([':ce' => $codeEcole]);
$finRow = $finStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$opt_inscription = (float)($finRow['somme_inscription'] ?? 0.0);
$opt_minerval    = (float)($finRow['somme_minerval'] ?? 0.0);
$opt_autres      = (float)($finRow['somme_autres'] ?? 0.0);
$opt_total       = $opt_inscription + $opt_minerval + $opt_autres;

assert($legacy_inscription === $opt_inscription && $opt_inscription === 200.50, 'Inscription amount mismatch');
assert($legacy_minerval === $opt_minerval && $opt_minerval === 300.25, 'Minerval amount mismatch');
assert($legacy_autres === $opt_autres && $opt_autres === 275.00, 'Autres amount mismatch');
assert($legacy_total === $opt_total && $opt_total === 775.75, 'Total finance amount mismatch');
echo "[PASS] Test exact parity with legacy calculations across complex statuses\n";

// 3. Performance Benchmark with 20,000 rows
$statuses = ['Inscription', 'Minerval', 'Autre', 'Cantine', 'Transport', 'Examen', '', null];
$pdo->beginTransaction();
for ($i = 0; $i < 20000; $i++) {
    $st = $statuses[$i % count($statuses)];
    $ce = 'BENCH_' . ($i % 4);
    $mt = ($i % 50) + 1.25;
    $date = '2025-0' . (($i % 9) + 1) . '-10';
    $stInsert = $pdo->prepare("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES (?, ?, ?, ?)");
    $stInsert->execute([$ce, $st, $mt, $date]);
}
$pdo->commit();

$benchSchool = 'BENCH_1';
$iterations = 50;

// Benchmark Legacy
$t0 = microtime(true);
for ($it = 0; $it < $iterations; $it++) {
    $s_ins = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'", [':ce' => $benchSchool], 0.0);
    $s_min = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'", [':ce' => $benchSchool], 0.0);
    $s_aut = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )", [':ce' => $benchSchool], 0.0);
    $t_tot = $s_ins + $s_min + $s_aut;
}
$duration_legacy = microtime(true) - $t0;

// Benchmark Optimized
$t0 = microtime(true);
for ($it = 0; $it < $iterations; $it++) {
    $finStmt->execute([':ce' => $benchSchool]);
    $row = $finStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $s_ins = (float)($row['somme_inscription'] ?? 0.0);
    $s_min = (float)($row['somme_minerval'] ?? 0.0);
    $s_aut = (float)($row['somme_autres'] ?? 0.0);
    $t_tot = $s_ins + $s_min + $s_aut;
}
$duration_opt = microtime(true) - $t0;

$speedup = $duration_legacy / $duration_opt;
$reduction = (1 - ($duration_opt / $duration_legacy)) * 100;
printf("[PASS] Benchmark: Legacy 3x queries = %.4fs | Optimized 1x query = %.4fs (%.2fx speedup, %.1f%% reduction)\n", $duration_legacy, $duration_opt, $speedup, $reduction);

assert($speedup > 1.2, 'Optimized query should be measurably faster (>1.2x)');
echo "=== All Dashboard Admin Query Tests & Benchmarks Passed! ===\n";
