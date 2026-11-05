<?php
/**
 * FlexPayIntegration - Passerelle de paiement FlexPay pour PHP.
 *
 * Gère le paiement Mobile Money, le paiement par Carte Bancaire,
 * et la vérification de transaction.
 */
class FlexPayIntegration
{
    private string $merchant;
    private string $token;
    private int $timeout;

    // Endpoints officiels FlexPay
    public const MOBILE_MONEY_URL = 'https://backend.flexpay.cd/api/rest/v1/paymentService';
    public const CARD_PAYMENT_URL  = 'https://cardpayment.flexpay.cd/v1.1/pay';
    public const CHECK_ORDER_URL   = 'https://apicheck.flexpaie.com/api/rest/v1/check/';

    // Valeur marchand par défaut
    private const DEFAULT_MERCHANT = 'BOOMRANGGROUP';

    /**
     * Constructeur de la classe FlexPayIntegration.
     *
     * @param string|null $merchant Code marchand (ou récupéré via getenv('FLEXPAY_MERCHANT'))
     * @param string|null $token    Token d'accès Bearer (ou récupéré via getenv('FLEXPAY_TOKEN'))
     * @param int $timeout          Timeout des requêtes cURL en secondes (défaut: 45)
     */
    public function __construct(?string $merchant = null, ?string $token = null, int $timeout = 45)
    {
        $this->merchant = $merchant
            ?: (getenv('FLEXPAY_MERCHANT') ?: self::DEFAULT_MERCHANT);

        $rawToken = $token ?: getenv('FLEXPAY_TOKEN');

        if (empty($rawToken)) {
            // Token par défaut si non spécifié (peut être défini via constructeur ou variables d'environnement)
            $rawToken = '';
        }

        // Assurer le préfixe Bearer si token non vide
        $trimmed = trim((string)$rawToken);
        if ($trimmed !== '' && !preg_match('/^Bearer\s+/i', $trimmed)) {
            $this->token = 'Bearer ' . $trimmed;
        } else {
            $this->token = $trimmed;
        }

        $this->timeout = $timeout;
    }

    /**
     * Initialise un paiement Mobile Money (M-Pesa, Orange Money, Airtel Money).
     *
     * @param string $reference   Référence unique de commande interne
     * @param float|int $amount   Montant de la transaction
     * @param string $currency    Devise ("CDF" ou "USD")
     * @param string $phone       Numéro du client (ex: "243891234567")
     * @param string $callbackUrl URL de webhook notifiée à la fin du paiement
     * @return array              Réponse décodée sous forme de tableau associatif
     * @throws InvalidArgumentException En cas de paramètre manquant ou invalide
     * @throws RuntimeException         En cas d'erreur réseau cURL ou réponse invalide
     */
    public function initiateMobileMoneyPayment(
        string $reference,
        $amount,
        string $currency,
        string $phone,
        string $callbackUrl
    ): array {
        $currency = strtoupper(trim($currency));
        if (!in_array($currency, ['CDF', 'USD'], true)) {
            throw new InvalidArgumentException("Devise invalide : '$currency'. Les devises autorisées sont 'CDF' ou 'USD'.");
        }

        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException("Le montant doit être supérieur à zéro.");
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (empty($cleanPhone)) {
            throw new InvalidArgumentException("Le numéro de téléphone du client est obligatoire.");
        }

        if (empty($reference)) {
            throw new InvalidArgumentException("La référence de la transaction est obligatoire.");
        }

        if (empty($callbackUrl) || !filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("L'URL de callback est invalide.");
        }

        $payload = [
            'merchant'    => $this->merchant,
            'type'        => '1', // Type 1 pour Mobile Money
            'reference'   => $reference,
            'phone'       => $cleanPhone,
            'amount'      => (string)$amount,
            'currency'    => $currency,
            'callbackUrl' => $callbackUrl,
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->token,
        ];

        return $this->sendRequest('POST', self::MOBILE_MONEY_URL, $payload, $headers);
    }

    /**
     * Initialise un paiement par Carte Bancaire (Visa, Mastercard).
     *
     * @param string $reference   Référence unique de commande interne
     * @param float|int $amount   Montant de la transaction
     * @param string $currency    Devise ("CDF" ou "USD")
     * @param string $description Description de l'achat
     * @param string $callbackUrl URL de webhook pour mise à jour asynchrone
     * @param string $approveUrl  URL de redirection client après paiement validé
     * @param string $cancelUrl   URL de redirection si client annule
     * @param string $declineUrl  URL de redirection si le paiement est rejeté
     * @return array              Réponse décodée contenant notamment l'URL de paiement
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function initiateCardPayment(
        string $reference,
        $amount,
        string $currency,
        string $description,
        string $callbackUrl,
        string $approveUrl,
        string $cancelUrl,
        string $declineUrl
    ): array {
        $currency = strtoupper(trim($currency));
        if (!in_array($currency, ['CDF', 'USD'], true)) {
            throw new InvalidArgumentException("Devise invalide : '$currency'. Les devises autorisées sont 'CDF' ou 'USD'.");
        }

        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException("Le montant doit être supérieur à zéro.");
        }

        if (empty($reference)) {
            throw new InvalidArgumentException("La référence est obligatoire.");
        }

        $urls = [
            'callback_url' => $callbackUrl,
            'approve_url'  => $approveUrl,
            'cancel_url'   => $cancelUrl,
            'decline_url'  => $declineUrl,
        ];
        foreach ($urls as $name => $u) {
            if (empty($u) || !filter_var($u, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("L'URL '$name' est invalide.");
            }
        }

        // Pour la carte, le token d'autorisation est transmis dans le payload JSON
        $payload = [
            'authorization' => $this->token,
            'merchant'      => $this->merchant,
            'reference'     => $reference,
            'amount'        => (string)$amount,
            'currency'      => $currency,
            'description'   => $description ?: 'Paiement scolaire',
            'callback_url'  => $callbackUrl,
            'approve_url'   => $approveUrl,
            'cancel_url'    => $cancelUrl,
            'decline_url'   => $declineUrl,
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->sendRequest('POST', self::CARD_PAYMENT_URL, $payload, $headers);
    }

    /**
     * Vérifie le statut d'une transaction FlexPay via son orderNumber.
     *
     * @param string $orderNumber Numéro d'ordre retourné par FlexPay
     * @return array              Détails de la transaction
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function checkTransactionStatus(string $orderNumber): array
    {
        $orderNumber = trim($orderNumber);
        if (empty($orderNumber)) {
            throw new InvalidArgumentException("Le numéro d'ordre (orderNumber) est obligatoire pour la vérification.");
        }

        $url = self::CHECK_ORDER_URL . rawurlencode($orderNumber);

        $headers = [
            'Accept: application/json',
            'Authorization: ' . $this->token,
        ];

        return $this->sendRequest('GET', $url, null, $headers);
    }

    /**
     * Traite et valide les données reçues sur l'URL de Callback / Webhook.
     *
     * @param string|null $rawPayload Flux d'entrée brut (si null, lit php://input)
     * @return array                  Données webhook décodées
     * @throws RuntimeException       En cas de payload vide ou JSON invalide
     */
    public function parseWebhookData(?string $rawPayload = null): array
    {
        if ($rawPayload === null) {
            $rawPayload = file_get_contents('php://input');
        }

        if (empty($rawPayload)) {
            throw new RuntimeException("Payload webhook vide reçu.");
        }

        $data = json_decode($rawPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException("Format JSON webhook invalide : " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Vérifie si un code de retour FlexPay indique un succès (code "0" ou 0).
     *
     * @param mixed $code
     * @return bool
     */
    public function isSuccessCode($code): bool
    {
        return $code === 0 || $code === '0';
    }

    /**
     * Exécute une requête HTTP avec cURL.
     *
     * @param string $method     'GET' ou 'POST'
     * @param string $url        URL cible
     * @param array|null $body   Données à sérialiser en JSON
     * @param array $headers     Headers HTTP
     * @return array             Données décodées
     * @throws RuntimeException
     */
    protected function sendRequest(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init();

        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if (strtoupper($method) === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $curlOptions[CURLOPT_POSTFIELDS] = $jsonBody;
        } elseif (strtoupper($method) === 'GET') {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Erreur de communication cURL avec FlexPay ($url) : $curlError");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Réponse invalide de l'API FlexPay (HTTP $httpCode) : non-JSON reçu ($response)");
        }

        return $decoded;
    }

    public function getMerchant(): string
    {
        return $this->merchant;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
