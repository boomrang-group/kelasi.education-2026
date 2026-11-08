<?php
// tests/test_dashboard_optimization.php
// Verification test for Bolt optimization: Single query conditional aggregation vs separate queries

declare(strict_types=1);

echo "=== Démarrage du test d'optimisation dashboard admin ===" . PHP_EOL;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE paiement (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT NOT NULL,
        statut TEXT,
        montant_paye REAL NOT NULL,
        date_paiement TEXT NOT NULL
    )
");

$stmt = $pdo->prepare("
    INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement)
    VALUES (:code_ecole, :statut, :montant_paye, :date_paiement)
");

$testData = [
    ['ECOLE_TEST', 'Inscription', 150.0, '2025-05-01'],
    ['ECOLE_TEST', 'Inscription', 50.0, '2025-05-02'],
    ['ECOLE_TEST', 'Minerval', 200.0, '2025-05-03'],
    ['ECOLE_TEST', 'Minerval', 100.5, '2025-05-04'],
    ['ECOLE_TEST', 'Autre', 30.0, '2025-05-05'],
    ['ECOLE_TEST', 'Frais_Examen', 45.0, '2025-05-06'],
    ['ECOLE_TEST', '', 10.0, '2025-05-07'], // empty status shouldn't be counted in s_aut
    ['AUTRE_ECOLE', 'Inscription', 999.0, '2025-05-01'], // different school
];

foreach ($testData as $row) {
    $stmt->execute([
        ':code_ecole'    => $row[0],
        ':statut'        => $row[1],
        ':montant_paye'  => $row[2],
        ':date_paiement' => $row[3],
    ]);
}

$codeEcole = 'ECOLE_TEST';

// 1. Separate queries approach (legacy)
function fetch_scalar(PDO $pdo, string $sql, array $params = [], $def = 0) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $def : $v;
}

$legacy_ins = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'",
    [':ce' => $codeEcole], 0.0
);
$legacy_min = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'",
    [':ce' => $codeEcole], 0.0
);
$legacy_aut = (float) fetch_scalar(
    $pdo,
    "SELECT COALESCE(SUM(montant_paye),0)
       FROM paiement
      WHERE code_ecole=:ce
        AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )",
    [':ce' => $codeEcole], 0.0
);
$legacy_tot = $legacy_ins + $legacy_min + $legacy_aut;

// 2. Optimized single query approach
function fetch_all(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$finances = fetch_all(
    $pdo,
    "SELECT
        COALESCE(SUM(CASE WHEN statut='Inscription' THEN montant_paye ELSE 0 END), 0) AS s_ins,
        COALESCE(SUM(CASE WHEN statut='Minerval' THEN montant_paye ELSE 0 END), 0) AS s_min,
        COALESCE(SUM(CASE WHEN (statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval'))) THEN montant_paye ELSE 0 END), 0) AS s_aut
       FROM paiement
      WHERE code_ecole = :ce",
    [':ce' => $codeEcole]
);
$finRow = $finances[0] ?? [];
$opt_ins = (float)($finRow['s_ins'] ?? 0.0);
$opt_min = (float)($finRow['s_min'] ?? 0.0);
$opt_aut = (float)($finRow['s_aut'] ?? 0.0);
$opt_tot = $opt_ins + $opt_min + $opt_aut;

// Assertions
assert($opt_ins === 200.0, "s_ins attendu 200.0, reçu $opt_ins");
assert($opt_min === 300.5, "s_min attendu 300.5, reçu $opt_min");
assert($opt_aut === 75.0, "s_aut attendu 75.0, reçu $opt_aut");
assert($opt_tot === 575.5, "s_tot attendu 575.5, reçu $opt_tot");

assert($opt_ins === $legacy_ins, "L'optimisation doit produire le même résultat pour Inscription");
assert($opt_min === $legacy_min, "L'optimisation doit produire le même résultat pour Minerval");
assert($opt_aut === $legacy_aut, "L'optimisation doit produire le même résultat pour Autres");
assert($opt_tot === $legacy_tot, "L'optimisation doit produire le même total");

// Edge case: Empty school with no records
$emptyFinances = fetch_all(
    $pdo,
    "SELECT
        COALESCE(SUM(CASE WHEN statut='Inscription' THEN montant_paye ELSE 0 END), 0) AS s_ins,
        COALESCE(SUM(CASE WHEN statut='Minerval' THEN montant_paye ELSE 0 END), 0) AS s_min,
        COALESCE(SUM(CASE WHEN (statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval'))) THEN montant_paye ELSE 0 END), 0) AS s_aut
       FROM paiement
      WHERE code_ecole = :ce",
    [':ce' => 'NON_EXISTENT']
);
$emptyRow = $emptyFinances[0] ?? [];
assert((float)($emptyRow['s_ins'] ?? 0.0) === 0.0);
assert((float)($emptyRow['s_min'] ?? 0.0) === 0.0);
assert((float)($emptyRow['s_aut'] ?? 0.0) === 0.0);

echo "[PASS] Tous les calculs financiers optimisés correspondent exactement à l'ancienne logique !" . PHP_EOL;
echo "=== Test d'optimisation réussi avec succès ! ===" . PHP_EOL;
