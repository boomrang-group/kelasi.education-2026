<?php
// tests/test_register_csrf.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../service/security_helpers.php';

echo "=== Démarrage des tests CSRF Inscription Promoteur (V05) ===\n";

function assert_test(bool $condition, string $message): void {
    if (!$condition) {
        echo "[FAIL] $message\n";
        exit(1);
    }
    echo "[PASS] $message\n";
}

$_SESSION = [];

// 1. Vérification que verify_csrf_token retourne faux si token absent
assert_test(verify_csrf_token('') === false, "Rejet si token CSRF vide");
assert_test(verify_csrf_token('invalid_token_123') === false, "Rejet si token CSRF invalide");

// 2. Génération du token
$token = generate_csrf_token();
assert_test(!empty($token) && strlen($token) === 64, "Génération d'un token CSRF 64 caractères hex");
assert_test($_SESSION['csrf_token'] === $token, "Token stocké en session sous 'csrf_token'");

// 3. Validation avec le bon token
assert_test(verify_csrf_token($token) === true, "Validation avec le token CSRF correspondant");

// 4. Rejet si tentative avec faux token ou token altéré
assert_test(verify_csrf_token($token . 'x') === false, "Rejet de token altéré");
assert_test(verify_csrf_token('fake_csrf_attack_token') === false, "Rejet de token attaquant");

// 5. Test de l'injection HTML dans le formulaire (csrf_input)
ob_start();
csrf_input();
$htmlOutput = ob_get_clean();
assert_test(
    strpos($htmlOutput, '<input type="hidden" name="csrf_token" value="' . $token . '">') !== false,
    "csrf_input génère bien l'input HTML avec le token attendu"
);

echo "=== Tous les tests CSRF Inscription Promoteur ont réussi avec succès ! ===\n";
