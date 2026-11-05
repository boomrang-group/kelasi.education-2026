<?php
/**
 * Test unitaire et de validation pour FlexPayIntegration
 */

require_once __DIR__ . '/../service/FlexPayIntegration.php';

function assert_true($condition, $message) {
    if (!$condition) {
        echo "FAILED: $message\n";
        exit(1);
    }
    echo "PASSED: $message\n";
}

// 1. Test Instanciation avec valeurs par défaut et token passé en argument
$fp = new FlexPayIntegration('BOOMRANGGROUP', 'test_secret_token');
assert_true($fp->getMerchant() === 'BOOMRANGGROUP', "Marchand par défaut correct");
assert_true(strpos($fp->getToken(), 'Bearer ') === 0, "Token d'accès contient le préfixe Bearer");

// 2. Test Instanciation avec paramètres personnalisés sans Bearer
$customFp = new FlexPayIntegration('TEST_MERCHANT', 'mon_token_brut');
assert_true($customFp->getMerchant() === 'TEST_MERCHANT', "Marchand personnalisé pris en compte");
assert_true($customFp->getToken() === 'Bearer mon_token_brut', "Ajout automatique du préfixe Bearer");

// 2.1 Test avec variable d'environnement
putenv('FLEXPAY_TOKEN=Bearer token_from_env');
$envFp = new FlexPayIntegration();
assert_true($envFp->getToken() === 'Bearer token_from_env', "Token lu depuis getenv('FLEXPAY_TOKEN')");
putenv('FLEXPAY_TOKEN');

// 3. Test de validation des exceptions
$exceptionCaught = false;
try {
    $fp->initiateMobileMoneyPayment('REF1', 0, 'USD', '243891234567', 'https://example.com/callback');
} catch (InvalidArgumentException $e) {
    $exceptionCaught = true;
}
assert_true($exceptionCaught, "Exception levée pour montant <= 0");

$exceptionCaught = false;
try {
    $fp->initiateMobileMoneyPayment('REF1', 10, 'EUR', '243891234567', 'https://example.com/callback');
} catch (InvalidArgumentException $e) {
    $exceptionCaught = true;
}
assert_true($exceptionCaught, "Exception levée pour devise non supportée (EUR)");

$exceptionCaught = false;
try {
    $fp->checkTransactionStatus('');
} catch (InvalidArgumentException $e) {
    $exceptionCaught = true;
}
assert_true($exceptionCaught, "Exception levée pour orderNumber vide");

// 4. Test Mock Request pour Mobile Money, Card et Check
class TestableFlexPayIntegration extends FlexPayIntegration {
    public ?string $lastMethod = null;
    public ?string $lastUrl = null;
    public ?array $lastBody = null;
    public array $lastHeaders = [];
    public array $mockResponse = [];

    protected function sendRequest(string $method, string $url, ?array $body = null, array $headers = []): array {
        $this->lastMethod  = $method;
        $this->lastUrl     = $url;
        $this->lastBody    = $body;
        $this->lastHeaders = $headers;
        return $this->mockResponse;
    }
}

$mockFp = new TestableFlexPayIntegration('BOOMRANGGROUP', 'token123');

// 4.1 Mock Mobile Money
$mockFp->mockResponse = [
    'code' => '0',
    'message' => 'Transaction initiated',
    'orderNumber' => 'ORD-999'
];
$resMM = $mockFp->initiateMobileMoneyPayment('REF-TEST-MM', 15.5, 'USD', '+243 (89) 123-4567', 'https://mykelasi.education/callback');
assert_true($resMM['code'] === '0', "Mobile Money retourne code 0");
assert_true($mockFp->lastBody['phone'] === '243891234567', "Numéro de téléphone nettoyé des caractères non-numériques");
assert_true($mockFp->lastBody['type'] === '1', "Type Mobile Money est bien '1'");
assert_true($mockFp->lastBody['amount'] === '15.5', "Montant sérialisé correctement");
assert_true(in_array('Authorization: Bearer token123', $mockFp->lastHeaders, true), "Header Authorization présent pour Mobile Money");

// 4.2 Mock Card Payment
$mockFp->mockResponse = [
    'code' => '0',
    'message' => 'Success',
    'orderNumber' => 'ORD-CARD-888',
    'url' => 'https://cardpayment.flexpay.cd/v1.1/pay/secure-link'
];
$resCard = $mockFp->initiateCardPayment(
    'REF-TEST-CARD',
    50,
    'USD',
    'Minerval Q1',
    'https://mykelasi.education/callback',
    'https://mykelasi.education/approve',
    'https://mykelasi.education/cancel',
    'https://mykelasi.education/decline'
);
assert_true($resCard['url'] === 'https://cardpayment.flexpay.cd/v1.1/pay/secure-link', "URL de paiement par carte reçue");
assert_true($mockFp->lastBody['authorization'] === 'Bearer token123', "Clé authorization présente dans le payload JSON pour Card");
assert_true($mockFp->lastBody['approve_url'] === 'https://mykelasi.education/approve', "approve_url correct");

// 4.3 Mock Check Transaction
$mockFp->mockResponse = [
    'code' => '0',
    'message' => 'Found',
    'transaction' => [
        'status' => '0',
        'amount' => '50.00',
        'currency' => 'USD'
    ]
];
$resCheck = $mockFp->checkTransactionStatus('ORD-CARD-888');
assert_true($mockFp->lastUrl === 'https://apicheck.flexpaie.com/api/rest/v1/check/ORD-CARD-888', "Check URL correctement formée avec flexpaie.com");
assert_true($mockFp->lastMethod === 'GET', "Check exécuté en méthode GET");
assert_true($resCheck['transaction']['status'] === '0', "Statut de transaction récupéré");

// 5. Test Webhook parsing
$webhookJson = json_encode([
    'code' => '0',
    'reference' => 'REF-TEST-MM',
    'provider_reference' => 'PROV-1234',
    'orderNumber' => 'ORD-999'
]);
$webhookData = $mockFp->parseWebhookData($webhookJson);
assert_true($webhookData['code'] === '0', "Webhook parse code 0");
assert_true($webhookData['reference'] === 'REF-TEST-MM', "Webhook parse reference");
assert_true($mockFp->isSuccessCode($webhookData['code']), "isSuccessCode vérifie 0 ou '0'");

echo "\nTous les tests unitaires FlexPayIntegration sont passés avec succès !\n";
