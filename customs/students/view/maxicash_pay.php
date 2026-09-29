<?php
// customs/students/view/maxicash_pay.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paiement.php');
    exit;
}

$type     = $_POST['type'] ?? '';
$amount   = (float)($_POST['montant'] ?? 0);
$email    = $_SESSION['email'] ?? '';
$phone    = $_POST['telephone'] ?? ''; // Might be added to the form if needed

if ($amount <= 0 || empty($type)) {
    die("Paramètres de paiement invalides.");
}

// Convert amount to cents for MaxiCash
$amountCents = intval($amount * 100);

// Placeholders for credentials
$merchantId   = "424757bf7b0e421a892a7f3cd09a60ac";
$merchantPwd  = "1c46944bc40d442bbd2eb7e9be7bb4a4";

// URLs
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// Adjust base path if necessary. Assuming it's in /customs/students/view/
$currentPath = dirname($_SERVER['PHP_SELF']);

$acceptUrl  = $baseUrl . $currentPath . "/maxicash/success.php";
$cancelUrl  = $baseUrl . $currentPath . "/maxicash/cancel.php";
$declineUrl = $baseUrl . $currentPath . "/maxicash/failure.php";
$notifyUrl  = $baseUrl . $currentPath . "/maxicash/notify.php";

$reference = "KLS-" . strtoupper($type[0]) . "-" . time() . "-" . rand(1000, 9999);

// Store in session for verification on return
$_SESSION['pending_ref']    = $reference;
$_SESSION['pending_amount'] = $amount;
$_SESSION['pending_type']   = $type;

// Choose endpoint (Sandbox for testing, Live for production)
$gatewayUrl = "https://api-testbed.maxicashapp.com/PayEntryPost";
// $gatewayUrl = "https://api.maxicashapp.com/PayEntryPost";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Redirection vers MaxiCash...</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #f4f6f9; }
        .loader { border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 2s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="loader"></div>
    <p>Vous allez être redirigé vers le portail de paiement sécurisé MaxiCash...</p>

    <form id="maxicashForm" action="<?= $gatewayUrl ?>" method="POST">
        <input type="hidden" name="PayType" value="MaxiCash">
        <input type="hidden" name="Amount" value="<?= $amountCents ?>">
        <input type="hidden" name="Currency" value="USD">
        <input type="hidden" name="Telephone" value="<?= htmlspecialchars($phone) ?>">
        <input type="hidden" name="Email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="MerchantID" value="<?= $merchantId ?>">
        <input type="hidden" name="MerchantPassword" value="<?= $merchantPwd ?>">
        <input type="hidden" name="Language" value="fr">
        <input type="hidden" name="Reference" value="<?= $reference ?>">
        <input type="hidden" name="accepturl" value="<?= $acceptUrl ?>">
        <input type="hidden" name="cancelurl" value="<?= $cancelUrl ?>">
        <input type="hidden" name="declineurl" value="<?= $declineUrl ?>">
        <input type="hidden" name="notifyurl" value="<?= $notifyUrl ?>">
    </form>

    <script type="text/javascript">
        window.onload = function() {
            document.getElementById('maxicashForm').submit();
        };
    </script>
</body>
</html>
