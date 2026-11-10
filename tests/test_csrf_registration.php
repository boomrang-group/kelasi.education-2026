<?php
// tests/test_csrf_registration.php
// Test unitaire de validation CSRF pour le flux d'enregistrement

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../service/security_helpers.php';

function run_csrf_tests() {
    echo "=== Démarrage des tests CSRF Promoter Registration ===\n";

    // 1. Initialisation / Régénération du token CSRF
    $_SESSION['csrf_token'] = null;

    $token = generate_csrf_token();
    assert(!empty($token), "Le token CSRF doit être généré");
    assert(strlen($token) === 64, "Le token CSRF doit comporter 64 caractères hexadécimaux");
    assert($_SESSION['csrf_token'] === $token, "Le token CSRF doit être stocké dans \$_SESSION['csrf_token']");
    echo "[PASS] Test génération du token CSRF réussi\n";

    // 2. Vérification d'un token valide
    assert(verify_csrf_token($token) === true, "La vérification d'un token valide doit retourner true");
    echo "[PASS] Test vérification token valide réussi\n";

    // 3. Rejet d'un token vide ou absent
    assert(verify_csrf_token('') === false, "Un token vide doit être rejeté");
    assert(verify_csrf_token(null) === false, "Un token null doit être rejeté");
    echo "[PASS] Test rejet token vide réussi\n";

    // 4. Rejet d'un token invalide
    $invalidToken = bin2hex(random_bytes(32));
    assert(verify_csrf_token($invalidToken) === false, "Un faux token doit être rejeté");
    echo "[PASS] Test rejet faux token réussi\n";

    // 5. Test du helper de formulaire csrf_input()
    ob_start();
    csrf_input();
    $inputHtml = ob_get_clean();
    assert(strpos($inputHtml, 'name="csrf_token"') !== false, "csrf_input() doit générer un champ avec le nom 'csrf_token'");
    assert(strpos($inputHtml, 'value="' . $token . '"') !== false, "csrf_input() doit inclure la valeur du token actif");
    echo "[PASS] Test rendu du champ HTML csrf_input() réussi\n";

    // 6. Test de simulation de la logique de register_process.php
    $_POST['csrf_token'] = 'token_frauduleux_csrf';
    $errors = [];
    $csrf_token = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = "Session expirée ou requête invalide (erreur CSRF). Veuillez réessayer.";
    }
    assert(count($errors) === 1, "Une tentative de soumission sans token valide doit ajouter une erreur CSRF");
    assert(strpos($errors[0], 'erreur CSRF') !== false, "L'erreur CSRF doit être explicitement signalée");
    echo "[PASS] Test simulation blocage CSRF dans register_process réussi\n";

    // 7. Simulation avec token légitime
    $_POST['csrf_token'] = $token;
    $errors = [];
    $csrf_token = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = "Session expirée ou requête invalide (erreur CSRF). Veuillez réessayer.";
    }
    assert(empty($errors), "Une soumission avec un token légitime ne doit pas déclencher d'erreur CSRF");
    echo "[PASS] Test simulation validation CSRF légitime dans register_process réussi\n";

    echo "=== Tous les tests CSRF Promoter Registration ont réussi avec succès ! ===\n";
}

run_csrf_tests();
