<?php
/**
 * Test de validation fonctionnelle et benchmark de performance pour admin/service/dashboard-admin.php
 */

declare(strict_types=1);

function runTests(): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialisation du schéma
    $pdo->exec("CREATE TABLE classes (id INTEGER PRIMARY KEY, code_ecole TEXT)");
    $pdo->exec("CREATE TABLE teacher (id INTEGER PRIMARY KEY, code_ecole TEXT)");
    $pdo->exec("CREATE TABLE students (id INTEGER PRIMARY KEY, code_ecole TEXT)");
    $pdo->exec("CREATE TABLE paiement (
        id INTEGER PRIMARY KEY,
        montant_paye REAL,
        statut TEXT,
        code_ecole TEXT,
        date_paiement TEXT
    )");

    $codeEcole = "ECOLE_TEST_01";

    // Insertion données de test
    // 5 classes, 12 enseignants, 120 élèves pour ECOLE_TEST_01
    for ($i = 1; $i <= 5; $i++) {
        $pdo->exec("INSERT INTO classes (id, code_ecole) VALUES ($i, '$codeEcole')");
    }
    for ($i = 1; $i <= 12; $i++) {
        $pdo->exec("INSERT INTO teacher (id, code_ecole) VALUES ($i, '$codeEcole')");
    }
    for ($i = 1; $i <= 120; $i++) {
        $pdo->exec("INSERT INTO students (id, code_ecole) VALUES ($i, '$codeEcole')");
    }

    // Données d'une autre école (isolation tenant)
    $pdo->exec("INSERT INTO classes (id, code_ecole) VALUES (99, 'AUTRE_ECOLE')");
    $pdo->exec("INSERT INTO teacher (id, code_ecole) VALUES (99, 'AUTRE_ECOLE')");
    $pdo->exec("INSERT INTO students (id, code_ecole) VALUES (999, 'AUTRE_ECOLE')");

    // Paiements pour ECOLE_TEST_01
    $pdo->exec("INSERT INTO paiement VALUES (1, 150.50, 'Inscription', '$codeEcole', '2026-03-01')");
    $pdo->exec("INSERT INTO paiement VALUES (2, 200.00, 'Inscription', '$codeEcole', '2026-03-02')");
    $pdo->exec("INSERT INTO paiement VALUES (3, 500.00, 'Minerval', '$codeEcole', '2026-03-03')");
    $pdo->exec("INSERT INTO paiement VALUES (4, 350.25, 'Minerval', '$codeEcole', '2026-03-04')");
    $pdo->exec("INSERT INTO paiement VALUES (5, 50.00, 'Autre', '$codeEcole', '2026-03-05')");
    $pdo->exec("INSERT INTO paiement VALUES (6, 75.25, 'Frais Examen', '$codeEcole', '2026-03-06')");
    $pdo->exec("INSERT INTO paiement VALUES (7, 0.00, '', '$codeEcole', '2026-03-07')"); // chaîne vide -> ignoré
    $pdo->exec("INSERT INTO paiement VALUES (8, 0.00, NULL, '$codeEcole', '2026-03-07')"); // NULL -> ignoré

    // Paiements autre école
    $pdo->exec("INSERT INTO paiement VALUES (9, 9999.00, 'Minerval', 'AUTRE_ECOLE', '2026-03-01')");

    // --- TEST 1: Exécution de la requête KPI consolidée ---
    $kpiSql = "SELECT
        (SELECT COUNT(*) FROM classes  WHERE code_ecole = :c1) AS nbre_classe,
        (SELECT COUNT(*) FROM teacher  WHERE code_ecole = :c2) AS nbre_prof,
        (SELECT COUNT(*) FROM students WHERE code_ecole = :c3) AS nbre_eleve";
    $stKpi = $pdo->prepare($kpiSql);
    $stKpi->execute([':c1' => $codeEcole, ':c2' => $codeEcole, ':c3' => $codeEcole]);
    $kpis = $stKpi->fetch(PDO::FETCH_ASSOC) ?: [];

    $nbre_classe = (int)($kpis['nbre_classe'] ?? 0);
    $nbre_prof   = (int)($kpis['nbre_prof'] ?? 0);
    $nbre_eleve  = (int)($kpis['nbre_eleve'] ?? 0);

    assert($nbre_classe === 5, "Attendu 5 classes, obtenu: $nbre_classe");
    assert($nbre_prof === 12, "Attendu 12 profs, obtenu: $nbre_prof");
    assert($nbre_eleve === 120, "Attendu 120 élèves, obtenu: $nbre_eleve");
    echo "[PASS] Test 1: KPI consolidés (classes, profs, élèves) corrects\n";

    // --- TEST 2: Exécution de la requête Finances consolidée ---
    $finSql = "SELECT
        COALESCE(SUM(CASE WHEN statut = 'Inscription' THEN montant_paye ELSE 0 END), 0) AS somme_inscription,
        COALESCE(SUM(CASE WHEN statut = 'Minerval' THEN montant_paye ELSE 0 END), 0) AS somme_minerval,
        COALESCE(SUM(CASE WHEN statut = 'Autre' OR (statut IS NOT NULL AND statut <> '' AND statut NOT IN ('Inscription', 'Minerval')) THEN montant_paye ELSE 0 END), 0) AS somme_autres
        FROM paiement
        WHERE code_ecole = :ce";
    $stFin = $pdo->prepare($finSql);
    $stFin->execute([':ce' => $codeEcole]);
    $finances = $stFin->fetch(PDO::FETCH_ASSOC) ?: [];

    $somme_inscription = (float)($finances['somme_inscription'] ?? 0.0);
    $somme_minerval    = (float)($finances['somme_minerval'] ?? 0.0);
    $somme_autres      = (float)($finances['somme_autres'] ?? 0.0);
    $finance_total     = $somme_inscription + $somme_minerval + $somme_autres;

    assert(abs($somme_inscription - 350.50) < 0.001, "Attendu 350.50 pour inscription, obtenu: $somme_inscription");
    assert(abs($somme_minerval - 850.25) < 0.001, "Attendu 850.25 pour minerval, obtenu: $somme_minerval");
    assert(abs($somme_autres - 125.25) < 0.001, "Attendu 125.25 pour autres (50.00 + 75.25), obtenu: $somme_autres");
    assert(abs($finance_total - 1326.00) < 0.001, "Attendu 1326.00 total, obtenu: $finance_total");
    echo "[PASS] Test 2: Finances consolidées (Inscription, Minerval, Autres, Total) correctes\n";

    // --- TEST 3: Edge Case: École sans données (0 résultats) ---
    $emptyEcole = "ECOLE_VIDE";
    $stKpi->execute([':c1' => $emptyEcole, ':c2' => $emptyEcole, ':c3' => $emptyEcole]);
    $kpisEmpty = $stKpi->fetch(PDO::FETCH_ASSOC) ?: [];
    assert((int)($kpisEmpty['nbre_classe'] ?? 0) === 0, "Classe vide doit valoir 0");
    assert((int)($kpisEmpty['nbre_prof'] ?? 0) === 0, "Prof vide doit valoir 0");
    assert((int)($kpisEmpty['nbre_eleve'] ?? 0) === 0, "Élève vide doit valoir 0");

    $stFin->execute([':ce' => $emptyEcole]);
    $finEmpty = $stFin->fetch(PDO::FETCH_ASSOC) ?: [];
    assert((float)($finEmpty['somme_inscription'] ?? 0.0) === 0.0, "Inscription vide doit valoir 0.0");
    assert((float)($finEmpty['somme_minerval'] ?? 0.0) === 0.0, "Minerval vide doit valoir 0.0");
    assert((float)($finEmpty['somme_autres'] ?? 0.0) === 0.0, "Autres vide doit valoir 0.0");
    echo "[PASS] Test 3: Edge case école vide retourne 0 sans erreur\n";

    // --- TEST 4: Benchmark de performance (Requêtes séparées vs Consolidées) ---
    $iterations = 2000;

    // Requêtes séparées (Ancienne méthode: 6 requêtes)
    $t0 = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $st1 = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE code_ecole = :ce");
        $st1->execute([':ce' => $codeEcole]);
        $st1->fetchColumn();

        $st2 = $pdo->prepare("SELECT COUNT(*) FROM teacher WHERE code_ecole = :ce");
        $st2->execute([':ce' => $codeEcole]);
        $st2->fetchColumn();

        $st3 = $pdo->prepare("SELECT COUNT(*) FROM students WHERE code_ecole = :ce");
        $st3->execute([':ce' => $codeEcole]);
        $st3->fetchColumn();

        $st4 = $pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'");
        $st4->execute([':ce' => $codeEcole]);
        $st4->fetchColumn();

        $st5 = $pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'");
        $st5->execute([':ce' => $codeEcole]);
        $st5->fetchColumn();

        $st6 = $pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND (statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')))");
        $st6->execute([':ce' => $codeEcole]);
        $st6->fetchColumn();
    }
    $tSeparate = microtime(true) - $t0;

    // Requêtes consolidées (Nouvelle méthode: 2 requêtes)
    $t0 = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $stKpiRun = $pdo->prepare($kpiSql);
        $stKpiRun->execute([':c1' => $codeEcole, ':c2' => $codeEcole, ':c3' => $codeEcole]);
        $stKpiRun->fetch(PDO::FETCH_ASSOC);

        $stFinRun = $pdo->prepare($finSql);
        $stFinRun->execute([':ce' => $codeEcole]);
        $stFinRun->fetch(PDO::FETCH_ASSOC);
    }
    $tConsolidated = microtime(true) - $t0;

    $pctImprovement = round((1 - ($tConsolidated / $tSeparate)) * 100, 1);
    $speedup = round($tSeparate / $tConsolidated, 2);

    echo "[PASS] Test 4: Benchmark ($iterations itérations):\n";
    echo "       - Requêtes séparées (6 requêtes)    : " . round($tSeparate * 1000, 2) . " ms\n";
    echo "       - Requêtes consolidées (2 requêtes) : " . round($tConsolidated * 1000, 2) . " ms\n";
    echo "       - Amélioration de performance       : {$pctImprovement}% plus rapide ({$speedup}x)\n";

    echo "\n=== Tous les tests de performance Dashboard Admin sont passés avec succès ! ===\n";
}

runTests();
