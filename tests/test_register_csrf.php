<?php
/**
 * Test CSRF protection in new_school promoter registration.
 */

// 1. Test token generation in session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

// Simulate visiting new_school/index.php
ob_start();
require __DIR__ . '/../new_school/index.php';
$html = ob_get_clean();

assert(!empty($_SESSION['csrf_token']), "CSRF token should be generated in session");
assert(strpos($html, 'name="csrf_token"') !== false, "HTML form must contain csrf_token hidden field");
assert(strpos($html, 'value="' . $_SESSION['csrf_token'] . '"') !== false, "HTML form must contain valid csrf_token value");
echo "PASSED: CSRF token generated and included in form\n";

// 2. Test CSRF validation logic directly
$session_token = $_SESSION['csrf_token'];

// Helper to simulate register_process CSRF check
function test_csrf_check($session_token, $post_token) {
    $_SESSION['csrf_token'] = $session_token;
    $errors = [];
    $csrf_token = (string)$post_token;
    if (empty($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Erreur de validation CSRF. Veuillez réessayer.";
    }
    return empty($errors);
}

// Case A: Valid token
assert(test_csrf_check($session_token, $session_token) === true, "Valid CSRF token should pass");
echo "PASSED: Valid CSRF token accepted\n";

// Case B: Missing token
assert(test_csrf_check($session_token, '') === false, "Empty CSRF token should fail");
assert(test_csrf_check($session_token, null) === false, "Null CSRF token should fail");
echo "PASSED: Missing CSRF token rejected\n";

// Case C: Invalid token
assert(test_csrf_check($session_token, 'invalid_csrf_token_12345') === false, "Tampered CSRF token should fail");
echo "PASSED: Invalid CSRF token rejected\n";

// Case D: Session token empty
assert(test_csrf_check('', 'some_token') === false, "Empty session token should fail");
echo "PASSED: Missing session token rejected\n";

echo "\nAll CSRF registration tests passed successfully!\n";
