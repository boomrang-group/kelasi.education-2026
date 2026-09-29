<?php
// customs/students/view/maxicash/notify.php
// This script handles the asynchronous notification from MaxiCash.

require_once '../../../../database/db_connect.php';

// Log the incoming request for debugging
$log = date('Y-m-d H:i:s') . " - Notify received: " . json_encode($_POST) . "\n";
file_put_contents('maxicash_notify.log', $log, FILE_APPEND);

// Expected parameters from MaxiCash:
// Reference, Amount, Status, etc. (Check documentation for exact field names returned)

$reference = $_POST['Reference'] ?? null;
$amount    = $_POST['Amount'] ?? 0; // Amount in cents
$status    = $_POST['Status'] ?? '';

if ($reference && $status === 'success') {
    // 1. Validate the transaction (optional: check with MaxiCash API)

    // 2. Update the database
    // Note: Since this is an asynchronous notification, we should ensure
    // we don't record the same transaction twice.

    try {
        $pdo->beginTransaction();

        // Check if already processed
        $st = $pdo->prepare("SELECT id FROM paiement WHERE reference = :ref");
        $st->execute([':ref' => $reference]);
        if (!$st->fetch()) {
            // Need to retrieve original payment intent data (studentId, type, code_ecole)
            // This usually requires either storing intent in a separate table or
            // including metadata in the reference.

            // For this implementation, we assume the reference can be parsed or
            // the notification is just for confirmation.

            // Log that we need more info or have a system to match references
            file_put_contents('maxicash_notify.log', "Reference $reference needs processing logic.\n", FILE_APPEND);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        file_put_contents('maxicash_notify.log', "Error processing $reference: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Always respond with 200 OK to MaxiCash
http_response_code(200);
echo "OK";
