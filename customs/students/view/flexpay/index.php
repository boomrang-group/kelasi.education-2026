<?php
/**
 * Exemple complet d'utilisation de la classe FlexPayIntegration.
 *
 * Ce fichier montre concrètement comment :
 * 1. Instancier la classe FlexPayIntegration
 * 2. Initier un paiement Mobile Money (M-Pesa, Orange, Airtel)
 * 3. Initier un paiement par Carte Bancaire (Visa, Mastercard)
 * 4. Vérifier l'état d'une transaction via checkTransactionStatus
 * 5. Traiter les données reçues sur le Webhook / Callback
 */

require_once __DIR__ . '/../../../../service/FlexPayIntegration.php';

// 1. INSTANCIATION
// Vous pouvez instancier sans argument (utilise .env ou valeurs par défaut) :
$flexPay = new FlexPayIntegration();

// Ou en passant explicitement merchant et token :
// $flexPay = new FlexPayIntegration('BOOMRANGGROUP', 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...');

$action = $_GET['action'] ?? 'home';
$result = null;
$error  = null;

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$currentDir = dirname($_SERVER['PHP_SELF'] ?? '/');
$callbackUrl = $baseUrl . $currentDir . '/callback.php';

try {
    // 2. EXEMPLE : PAIEMENT MOBILE MONEY
    if ($action === 'mobile_money' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $reference = 'REF-MM-' . time();
        $amount    = $_POST['amount'] ?? 10.0;
        $currency  = $_POST['currency'] ?? 'USD';
        $phone     = $_POST['phone'] ?? '243891234567';

        $result = $flexPay->initiateMobileMoneyPayment(
            $reference,
            $amount,
            $currency,
            $phone,
            $callbackUrl
        );
    }

    // 3. EXEMPLE : PAIEMENT PAR CARTE BANCAIRE
    if ($action === 'card' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $reference   = 'REF-CARD-' . time();
        $amount      = $_POST['amount'] ?? 25.0;
        $currency    = $_POST['currency'] ?? 'USD';
        $description = $_POST['description'] ?? 'Paiement Minerval Élève';
        $approveUrl  = $baseUrl . $currentDir . '/index.php?action=success';
        $cancelUrl   = $baseUrl . $currentDir . '/index.php?action=cancel';
        $declineUrl  = $baseUrl . $currentDir . '/index.php?action=decline';

        $result = $flexPay->initiateCardPayment(
            $reference,
            $amount,
            $currency,
            $description,
            $callbackUrl,
            $approveUrl,
            $cancelUrl,
            $declineUrl
        );

        // Si l'API retourne l'URL de paiement, on peut rediriger le client :
        // if (!empty($result['url'])) { header("Location: " . $result['url']); exit; }
    }

    // 4. EXEMPLE : VÉRIFICATION DU STATUT DE TRANSACTION
    if ($action === 'check' && !empty($_GET['order_number'])) {
        $orderNumber = $_GET['order_number'];
        $result = $flexPay->checkTransactionStatus($orderNumber);
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Exemples d'intégration FlexPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; padding: 40px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 24px; }
        pre { background: #1e293b; color: #38bdf8; padding: 15px; border-radius: 8px; max-height: 400px; overflow: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="mb-4 text-center">
        <h1 class="h3 fw-bold text-primary">Démonstration & Exemples - Passerelle FlexPay</h1>
        <p class="text-muted">Utilisation de la classe <code>FlexPayIntegration</code> pour Mobile Money, Cartes et Vérification</p>
        <div class="badge bg-secondary p-2">Marchand : <?= htmlspecialchars($flexPay->getMerchant()) ?></div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm">
            <strong>Erreur :</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="card p-4">
            <h5 class="fw-bold text-success mb-3">Réponse reçue de FlexPay :</h5>
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php if (!empty($result['url'])): ?>
                <div class="mt-2">
                    <a href="<?= htmlspecialchars($result['url']) ?>" target="_blank" class="btn btn-success">
                        Ouvrir la page de paiement par Carte <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- 1. Formulaire Mobile Money -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="text-primary">1.</i> Mobile Money</h5>
                <p class="small text-muted">Initie un paiement Mobile Money (M-Pesa, Airtel, Orange) via l'API FlexPay.</p>
                <form method="POST" action="index.php?action=mobile_money">
                    <div class="mb-3">
                        <label class="form-label small">Montant</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Devise</label>
                        <select name="currency" class="form-select">
                            <option value="USD">USD</option>
                            <option value="CDF">CDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Téléphone client (ex: 243891234567)</label>
                        <input type="text" name="phone" class="form-control" value="243891234567" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Initier Mobile Money</button>
                </form>
            </div>
        </div>

        <!-- 2. Formulaire Carte Bancaire -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="text-primary">2.</i> Carte Bancaire</h5>
                <p class="small text-muted">Initie un paiement par Carte (Visa, Mastercard) et génère l'URL de paiement.</p>
                <form method="POST" action="index.php?action=card">
                    <div class="mb-3">
                        <label class="form-label small">Montant</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="25" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Devise</label>
                        <select name="currency" class="form-select">
                            <option value="USD">USD</option>
                            <option value="CDF">CDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description</label>
                        <input type="text" name="description" class="form-control" value="Frais scolaires trimestriels">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Initier Paiement Carte</button>
                </form>
            </div>
        </div>

        <!-- 3. Formulaire Vérification Statut -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="text-primary">3.</i> Vérification Statut</h5>
                <p class="small text-muted">Interroge l'endpoint <code>check/{ORDER_NUMBER}</code> pour obtenir les détails et le statut.</p>
                <form method="GET" action="index.php">
                    <input type="hidden" name="action" value="check">
                    <div class="mb-3">
                        <label class="form-label small">Numéro de commande (orderNumber)</label>
                        <input type="text" name="order_number" class="form-control" placeholder="ex: ORD-123456" required>
                    </div>
                    <button type="submit" class="btn btn-info text-white w-100">Vérifier la Transaction</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. Webhook Info -->
    <div class="card p-4 mt-3">
        <h5 class="fw-bold mb-2"><i class="text-primary">4.</i> URL de Callback / Webhook configurée :</h5>
        <code class="p-2 bg-light rounded d-block mb-3"><?= htmlspecialchars($callbackUrl) ?></code>
        <p class="small text-muted mb-0">Lorsqu'un paiement est finalisé, FlexPay envoie une requête POST JSON vers cette adresse pour notifier du statut (avec <code>code: 0</code> en cas de succès, <code>reference</code>, <code>orderNumber</code>, etc.).</p>
    </div>
</div>
</body>
</html>
