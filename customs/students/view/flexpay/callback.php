<?php
// customs/students/view/flexpay/callback.php
// Webhook FlexPay appelé de manière asynchrone pour notifier du statut de transaction

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../database/db_connect.php';
require_once __DIR__ . '/../../../../service/FlexPayIntegration.php';

$flexPay = new FlexPayIntegration();

try {
    $data = $flexPay->parseWebhookData();

    // Journalisation sécurisée de l'événement (pour audit et traçabilité)
    error_log("[FlexPay Webhook] Réception: " . json_encode($data));

    // Structure attendue: code (0 = succès), reference, provider_reference, orderNumber
    $code              = $data['code'] ?? null;
    $reference         = $data['reference'] ?? null;
    $orderNumber       = $data['orderNumber'] ?? null;
    $providerReference = $data['provider_reference'] ?? null;

    if (empty($reference)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Reference manquante']);
        exit;
    }

    $isSuccess = $flexPay->isSuccessCode($code);

    // Vérification supplémentaire avec l'endpoint de check si orderNumber disponible
    if ($isSuccess && !empty($orderNumber)) {
        try {
            $check = $flexPay->checkTransactionStatus($orderNumber);
            $txStatus = $check['transaction']['status'] ?? null;
            // 0 = transaction traitée avec succès
            if ($txStatus !== 0 && $txStatus !== '0') {
                $isSuccess = false;
                error_log("[FlexPay Webhook Warning] CheckTransactionStatus status non-succès: " . json_encode($check));
            }
        } catch (Throwable $e) {
            // Loguer sans bloquer si le serveur de check est temporairement injoignable
            error_log("[FlexPay Webhook Check Warning] " . $e->getMessage());
        }
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        if ($isSuccess) {
            // Transaction validée : marquer le paiement comme approuvé / validé de manière résiliente
            try {
                $st = $pdo->prepare("UPDATE paiement SET is_validated = 1, approved = 1 WHERE reference = :ref");
                $st->execute([':ref' => $reference]);
            } catch (Throwable $e) {
                try {
                    $st = $pdo->prepare("UPDATE paiement SET is_validated = 1 WHERE reference = :ref");
                    $st->execute([':ref' => $reference]);
                } catch (Throwable $e2) {
                    $st = $pdo->prepare("UPDATE paiement SET approved = 1 WHERE reference = :ref");
                    $st->execute([':ref' => $reference]);
                }
            }
        } else {
            error_log("[FlexPay Webhook] Paiement non approuvé pour référence: $reference (code: $code)");
        }
    }

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => 'Notification traitée avec succès',
        'ref'     => $reference
    ]);
} catch (Throwable $e) {
    error_log("[FlexPay Webhook Error] " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
