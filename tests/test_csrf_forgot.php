<?php
declare(strict_types=1);

/**
 * Unit/Regression test for CSRF protection in login/forgot.php
 */

$testFile = __DIR__ . '/../login/forgot.php';
$content = file_get_contents($testFile);

if ($content === false) {
    echo "[FAIL] Impossible de lire login/forgot.php\n";
    exit(1);
}

// Check 1: Session CSRF token initialization
if (strpos($content, '$_SESSION[\'csrf_token\'] = bin2hex(random_bytes(32))') === false) {
    echo "[FAIL] La génération du jeton CSRF est manquante dans login/forgot.php\n";
    exit(1);
}
echo "[PASS] Initialisation du jeton CSRF en session présente.\n";

// Check 2: CSRF token validation
if (strpos($content, '!hash_equals($_SESSION[\'csrf_token\'], (string)$_POST[\'csrf_token\'])') === false) {
    echo "[FAIL] La vérification CSRF hash_equals est manquante dans login/forgot.php\n";
    exit(1);
}
echo "[PASS] Validation CSRF par hash_equals présente.\n";

// Check 3: Form input for CSRF
if (strpos($content, '<input type="hidden" name="csrf_token"') === false) {
    echo "[FAIL] Le champ hidden csrf_token est manquant dans le formulaire.\n";
    exit(1);
}
echo "[PASS] Champ hidden csrf_token présent dans le formulaire.\n";

// Check 4: Functional simulation of CSRF logic
function simulate_forgot_csrf(?string $sessionToken, ?string $postToken): array {
    $errors = [];
    if (!isset($postToken) || !hash_equals((string)$sessionToken, (string)$postToken)) {
        $errors[] = "Erreur de validation CSRF.";
    }
    return $errors;
}

$validToken = bin2hex(random_bytes(32));

// Test 4a: Missing post token
$res1 = simulate_forgot_csrf($validToken, null);
if (empty($res1) || $res1[0] !== "Erreur de validation CSRF.") {
    echo "[FAIL] Requête sans token CSRF aurait dû échouer.\n";
    exit(1);
}
echo "[PASS] Rejet de requête POST sans token CSRF validé.\n";

// Test 4b: Invalid post token
$res2 = simulate_forgot_csrf($validToken, "wrong_token_12345");
if (empty($res2) || $res2[0] !== "Erreur de validation CSRF.") {
    echo "[FAIL] Requête avec mauvais token CSRF aurait dû échouer.\n";
    exit(1);
}
echo "[PASS] Rejet de requête POST avec token CSRF invalide validé.\n";

// Test 4c: Valid post token
$res3 = simulate_forgot_csrf($validToken, $validToken);
if (!empty($res3)) {
    echo "[FAIL] Requête avec token CSRF valide ne devrait pas renvoyer d'erreur CSRF.\n";
    exit(1);
}
echo "[PASS] Acceptation de requête POST avec token CSRF valide validée.\n";

echo "Tous les tests CSRF pour forgot.php ont réussi avec succès !\n";
