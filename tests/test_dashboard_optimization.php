<?php
// tests/test_dashboard_optimization.php
// Test equivalence and performance behavior of consolidated paiement aggregation query

declare(strict_types=1);

function runTests(): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE paiement (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code_ecole TEXT NOT NULL,
            statut TEXT,
            montant_paye REAL NOT NULL DEFAULT 0.0,
            date_paiement TEXT
        )
    ");

    function fetch_scalar(PDO $pdo, string $sql, array $params = [], $def = 0) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $def : $v;
    }

    function fetch_all(PDO $pdo, string $sql, array $params = []): array {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    function calculateLegacy(PDO $pdo, string $codeEcole): array {
        $somme_inscription = (float) fetch_scalar(
            $pdo,
            "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Inscription'",
            [':ce' => $codeEcole], 0.0
        );
        $somme_minerval = (float) fetch_scalar(
            $pdo,
            "SELECT COALESCE(SUM(montant_paye),0) FROM paiement WHERE code_ecole=:ce AND statut='Minerval'",
            [':ce' => $codeEcole], 0.0
        );
        $somme_autres = (float) fetch_scalar(
            $pdo,
            "SELECT COALESCE(SUM(montant_paye),0)
               FROM paiement
              WHERE code_ecole=:ce
                AND ( statut='Autre' OR (statut IS NOT NULL AND statut<>'' AND statut NOT IN ('Inscription','Minerval')) )",
            [':ce' => $codeEcole], 0.0
        );
        $finance_total = $somme_inscription + $somme_minerval + $somme_autres;

        return [
            'ins'   => $somme_inscription,
            'min'   => $somme_minerval,
            'aut'   => $somme_autres,
            'total' => $finance_total
        ];
    }

    function calculateOptimized(PDO $pdo, string $codeEcole): array {
        $finances = fetch_all(
            $pdo,
            "SELECT
                COALESCE(SUM(CASE WHEN statut = 'Inscription' THEN montant_paye ELSE 0 END), 0) AS s_ins,
                COALESCE(SUM(CASE WHEN statut = 'Minerval' THEN montant_paye ELSE 0 END), 0) AS s_min,
                COALESCE(SUM(CASE WHEN (statut = 'Autre' OR (statut IS NOT NULL AND statut <> '' AND statut NOT IN ('Inscription', 'Minerval'))) THEN montant_paye ELSE 0 END), 0) AS s_aut
               FROM paiement
              WHERE code_ecole = :ce",
            [':ce' => $codeEcole]
        );
        $somme_inscription = (float)($finances[0]['s_ins'] ?? 0.0);
        $somme_minerval    = (float)($finances[0]['s_min'] ?? 0.0);
        $somme_autres      = (float)($finances[0]['s_aut'] ?? 0.0);
        $finance_total     = $somme_inscription + $somme_minerval + $somme_autres;

        return [
            'ins'   => $somme_inscription,
            'min'   => $somme_minerval,
            'aut'   => $somme_autres,
            'total' => $finance_total
        ];
    }

    // Test 1: Empty table / No payments for school
    $resLegacy = calculateLegacy($pdo, 'SCH01');
    $resOpt    = calculateOptimized($pdo, 'SCH01');
    assert($resLegacy === $resOpt, "Test 1 Failed: empty table mismatch");
    assert($resOpt['total'] === 0.0, "Test 1 Failed: non-zero total on empty table");
    echo "PASSED: Test 1 - Table vide ou école sans paiement\n";

    // Test 2: Standard payments
    $pdo->exec("
        INSERT INTO paiement (code_ecole, statut, montant_paye) VALUES
        ('SCH01', 'Inscription', 150.0),
        ('SCH01', 'Minerval', 200.0),
        ('SCH01', 'Minerval', 50.0),
        ('SCH01', 'Autre', 35.0),
        ('SCH02', 'Inscription', 999.0)
    ");
    $resLegacy = calculateLegacy($pdo, 'SCH01');
    $resOpt    = calculateOptimized($pdo, 'SCH01');
    assert($resLegacy === $resOpt, "Test 2 Failed: standard payments mismatch");
    assert($resOpt['ins'] === 150.0 && $resOpt['min'] === 250.0 && $resOpt['aut'] === 35.0 && $resOpt['total'] === 435.0, "Test 2 Failed: incorrect sums");
    echo "PASSED: Test 2 - Paiements standards (Inscription, Minerval, Autre)\n";

    // Test 3: Edge cases (custom statuts, NULL statut, empty string statut)
    $pdo->exec("
        INSERT INTO paiement (code_ecole, statut, montant_paye) VALUES
        ('SCH01', 'Frais examen', 40.0),
        ('SCH01', 'Transport', 60.0),
        ('SCH01', NULL, 100.0),
        ('SCH01', '', 80.0)
    ");
    $resLegacy = calculateLegacy($pdo, 'SCH01');
    $resOpt    = calculateOptimized($pdo, 'SCH01');
    assert($resLegacy === $resOpt, "Test 3 Failed: edge cases mismatch");
    // Frais examen (40) and Transport (60) should be included in 'aut' (35 + 40 + 60 = 135)
    // NULL and '' should NOT be counted in 'aut'
    assert($resOpt['aut'] === 135.0, "Test 3 Failed: aut expected 135.0, got " . $resOpt['aut']);
    assert($resOpt['total'] === (150.0 + 250.0 + 135.0), "Test 3 Failed: total sum incorrect");
    echo "PASSED: Test 3 - Cas limites (statuts personnalisés, NULL, chaînes vides ignorés)\n";

    // Test 4: School isolation
    $resSCH02 = calculateOptimized($pdo, 'SCH02');
    assert($resSCH02['ins'] === 999.0 && $resSCH02['total'] === 999.0, "Test 4 Failed: school isolation");
    echo "PASSED: Test 4 - Isolation multi-école\n";

    echo "\nTous les tests unitaires pour l'optimisation du dashboard sont passés avec succès !\n";
}

runTests();
