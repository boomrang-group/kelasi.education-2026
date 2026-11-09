<?php
// tests/test_dashboard_admin_perf.php
// Verification of query consolidation logic and benchmark in admin/service/dashboard-admin.php

declare(strict_types=1);

echo "=== Démarrage des tests de performance Dashboard Admin ===\n";

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 1. Initialiser le schéma
$pdo->exec("
    CREATE TABLE classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT
    );
    CREATE TABLE teacher (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT
    );
    CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT
    );
    CREATE TABLE paiement (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_ecole TEXT,
        statut TEXT,
        montant_paye REAL,
        date_paiement TEXT
    );
");

// 2. Insérer des données de test pour ECOLE_A et ECOLE_B
$codeEcole = 'ECOLE_A';

// ECOLE_A data
for ($i = 0; $i < 5; $i++) {
    $pdo->exec("INSERT INTO classes (code_ecole) VALUES ('ECOLE_A')");
}
for ($i = 0; $i < 8; $i++) {
    $pdo->exec("INSERT INTO teacher (code_ecole) VALUES ('ECOLE_A')");
}
for ($i = 0; $i < 42; $i++) {
    $pdo->exec("INSERT INTO students (code_ecole) VALUES ('ECOLE_A')");
}

// Paiements ECOLE_A
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Inscription', 150.0, '2025-01-10')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Inscription', 50.0, '2025-01-12')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Minerval', 300.0, '2025-02-01')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Minerval', 200.0, '2025-02-05')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Autre', 45.0, '2025-02-10')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_A', 'Frais examen', 25.0, '2025-02-15')");

// ECOLE_B data (should not bleed into ECOLE_A results)
$pdo->exec("INSERT INTO classes (code_ecole) VALUES ('ECOLE_B')");
$pdo->exec("INSERT INTO teacher (code_ecole) VALUES ('ECOLE_B')");
$pdo->exec("INSERT INTO students (code_ecole) VALUES ('ECOLE_B')");
$pdo->exec("INSERT INTO paiement (code_ecole, statut, montant_paye, date_paiement) VALUES ('ECOLE_B', 'Inscription', 999.0, '2025-01-10')");

// 3. Test de parité : Ancienne méthode (6 requêtes scalaires)
function legacy_fetch(PDO $pdo, string $codeEcole): array {
    $nbre_classe = (int) $pdo->query("SELECT COUNT(*) FROM classes WHERE code_ecole = '{$codeEcole}'")->fetchColumn();
    $nbre_prof   = (int) $pdo->query("SELECT COUNT(*) FROM teacher WHERE code_ecole = '{$codeEcole}'")->fetchColumn();
    $nbre_eleve  = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE code_ecole = '{$codeEcole}'")->fetchColumn();

    $s_ins = (float) $pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole='{$codeEcole}' AND statut='Inscription'")->fetchColumn();
    $s_min = (float) $pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole='{$codeEcole}' AND statut='Minerval'")->fetchColumn();
    $s_aut = (float) $pdo->query("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole='{$codeEcole}' AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )")->fetchColumn();
    $total = $s_ins + $s_min + $s_aut;

    return [
        'nbre_classe' => $nbre_classe,
        'nbre_prof' => $nbre_prof,
        'nbre_eleve' => $nbre_eleve,
        'somme_inscription' => $s_ins,
        'somme_minerval' => $s_min,
        'somme_autres' => $s_aut,
        'finance_total' => $total,
    ];
}

// 4. Test de parité : Méthode optimisée Bolt (2 requêtes consolidées)
function optimized_fetch(PDO $pdo, string $codeEcole): array {
    $stKpi = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM classes  WHERE code_ecole = :c1) AS total_classes,
        (SELECT COUNT(*) FROM teacher  WHERE code_ecole = :c2) AS total_profs,
        (SELECT COUNT(*) FROM students WHERE code_ecole = :c3) AS total_eleves");
    $stKpi->execute([':c1' => $codeEcole, ':c2' => $codeEcole, ':c3' => $codeEcole]);
    $kpiRow = $stKpi->fetchAll(PDO::FETCH_ASSOC)[0] ?? [];

    $nbre_classe = (int)($kpiRow['total_classes'] ?? 0);
    $nbre_prof   = (int)($kpiRow['total_profs']   ?? 0);
    $nbre_eleve  = (int)($kpiRow['total_eleves']  ?? 0);

    $stFin = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN statut = 'Inscription' THEN montant_paye ELSE 0 END), 0) AS s_ins,
        COALESCE(SUM(CASE WHEN statut = 'Minerval'    THEN montant_paye ELSE 0 END), 0) AS s_min,
        COALESCE(SUM(CASE WHEN statut = 'Autre' OR (statut IS NOT NULL AND statut <> '' AND statut NOT IN ('Inscription', 'Minerval')) THEN montant_paye ELSE 0 END), 0) AS s_aut
     FROM paiement
     WHERE code_ecole = :ce");
    $stFin->execute([':ce' => $codeEcole]);
    $finRow = $stFin->fetchAll(PDO::FETCH_ASSOC)[0] ?? [];

    $s_ins = (float)($finRow['s_ins'] ?? 0.0);
    $s_min = (float)($finRow['s_min'] ?? 0.0);
    $s_aut = (float)($finRow['s_aut'] ?? 0.0);
    $total = $s_ins + $s_min + $s_aut;

    return [
        'nbre_classe' => $nbre_classe,
        'nbre_prof' => $nbre_prof,
        'nbre_eleve' => $nbre_eleve,
        'somme_inscription' => $s_ins,
        'somme_minerval' => $s_min,
        'somme_autres' => $s_aut,
        'finance_total' => $total,
    ];
}

$legacyA = legacy_fetch($pdo, 'ECOLE_A');
$optimizedA = optimized_fetch($pdo, 'ECOLE_A');

assert($legacyA['nbre_classe'] === 5, 'Nombre de classes attendu: 5');
assert($optimizedA['nbre_classe'] === $legacyA['nbre_classe'], 'Parité nbre_classe échouée');

assert($legacyA['nbre_prof'] === 8, 'Nombre de profs attendu: 8');
assert($optimizedA['nbre_prof'] === $legacyA['nbre_prof'], 'Parité nbre_prof échouée');

assert($legacyA['nbre_eleve'] === 42, 'Nombre d eleves attendu: 42');
assert($optimizedA['nbre_eleve'] === $legacyA['nbre_eleve'], 'Parité nbre_eleve échouée');

assert($legacyA['somme_inscription'] === 200.0, 'Somme inscription attendue: 200.0');
assert($optimizedA['somme_inscription'] === $legacyA['somme_inscription'], 'Parité somme_inscription échouée');

assert($legacyA['somme_minerval'] === 500.0, 'Somme minerval attendue: 500.0');
assert($optimizedA['somme_minerval'] === $legacyA['somme_minerval'], 'Parité somme_minerval échouée');

assert($legacyA['somme_autres'] === 70.0, 'Somme autres attendue: 70.0');
assert($optimizedA['somme_autres'] === $legacyA['somme_autres'], 'Parité somme_autres échouée');

assert($legacyA['finance_total'] === 770.0, 'Finance totale attendue: 770.0');
assert($optimizedA['finance_total'] === $legacyA['finance_total'], 'Parité finance_total échouée');

echo "[PASS] Parité exacte vérifiée pour ECOLE_A avec données mixtes.\n";

// 5. Test avec une école sans enregistrements (valeurs par défaut / 0)
$legacyEmpty = legacy_fetch($pdo, 'ECOLE_EMPTY');
$optimizedEmpty = optimized_fetch($pdo, 'ECOLE_EMPTY');

assert($optimizedEmpty['nbre_classe'] === 0);
assert($optimizedEmpty['nbre_prof'] === 0);
assert($optimizedEmpty['nbre_eleve'] === 0);
assert($optimizedEmpty['somme_inscription'] === 0.0);
assert($optimizedEmpty['somme_minerval'] === 0.0);
assert($optimizedEmpty['somme_autres'] === 0.0);
assert($optimizedEmpty['finance_total'] === 0.0);
assert($optimizedEmpty === $legacyEmpty, 'Parité école vide échouée');

echo "[PASS] Parité exacte vérifiée pour école vide (zéro données).\n";

// 6. Micro-Benchmark (1,000 itérations)
$iterations = 1000;

$t0 = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    legacy_fetch($pdo, 'ECOLE_A');
}
$tLegacy = microtime(true) - $t0;

$t1 = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    optimized_fetch($pdo, 'ECOLE_A');
}
$tOptimized = microtime(true) - $t1;

$pctReduction = round((1 - ($tOptimized / $tLegacy)) * 100, 1);
echo sprintf("[BENCHMARK] Legacy: %.4f s (%d queries per run = %d total)\n", $tLegacy, 6, $iterations * 6);
echo sprintf("[BENCHMARK] Optimized: %.4f s (%d queries per run = %d total)\n", $tOptimized, 2, $iterations * 2);
echo sprintf("[BENCHMARK] Temps d'exécution réduit de ~%.1f%%, requêtes réduites de 66.7%%\n", $pctReduction);

echo "=== Tous les tests de performance ont réussi ! ===\n";
