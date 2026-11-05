<?php
// customs/students/view/service/initier_paiement_flexpay.php

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../../database/db_connect.php';
require_once '../../../../service/FlexPayIntegration.php';

/* ===== SÉCURITÉ ===== */
if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$studentId  = $_SESSION['student_id'] ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

if (!$studentId || !$code_ecole) {
    echo json_encode(['success' => false, 'message' => 'Session élève invalide']);
    exit;
}

$type      = trim($_POST['type'] ?? '');
$montant   = (float)($_POST['montant'] ?? 0);
$method    = trim($_POST['method'] ?? 'mobile_money');
$telephone = trim($_POST['telephone'] ?? '');

$allowedTypes = ['Inscription', 'Minerval', 'Autre'];
if (!in_array($type, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Type de paiement invalide']);
    exit;
}

if ($montant <= 0) {
    echo json_encode(['success' => false, 'message' => 'Montant invalide']);
    exit;
}

$reference = time() . rand(100, 999);
$devise    = 'USD';

$scheme      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$callbackUrl = "$scheme://$host/customs/students/view/flexpay/callback.php";

$modeDb = ($method === 'card') ? 'FlexPay Carte' : 'FlexPay Mobile Money';

// 1. Enregistrer la transaction en attente dans la table paiement
try {
    $st = $pdo->prepare("
        INSERT INTO paiement
        (reference, statut, eleve, montant_paye, solde, mode, date_paiement, code_ecole, is_validated, approved)
        VALUES
        (:ref, :statut, :eleve, :montant, 0, :mode, NOW(), :ecole, 0, 0)
    ");
    $st->execute([
        ':ref'     => $reference,
        ':statut'  => $type,
        ':eleve'   => $studentId,
        ':montant' => $montant,
        ':mode'    => $modeDb,
        ':ecole'   => $code_ecole
    ]);
} catch (Throwable $e) {
    // Si la colonne approved n'existe pas, fallback sans approved
    try {
        $st = $pdo->prepare("
            INSERT INTO paiement
            (reference, statut, eleve, montant_paye, solde, mode, date_paiement, code_ecole, is_validated)
            VALUES
            (:ref, :statut, :eleve, :montant, 0, :mode, NOW(), :ecole, 0)
        ");
        $st->execute([
            ':ref'     => $reference,
            ':statut'  => $type,
            ':eleve'   => $studentId,
            ':montant' => $montant,
            ':mode'    => $modeDb,
            ':ecole'   => $code_ecole
        ]);
    } catch (Throwable $e2) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données : ' . $e2->getMessage()]);
        exit;
    }
}

// 2. Appel à FlexPayIntegration
try {
    $flexPay = new FlexPayIntegration();

    if ($method === 'card') {
        $approveUrl  = "$scheme://$host/customs/students/view/finances.php?msg=card_success";
        $cancelUrl   = "$scheme://$host/customs/students/view/finances.php?msg=card_canceled";
        $declineUrl  = "$scheme://$host/customs/students/view/finances.php?msg=card_declined";
        $description = "Paiement $type - Élève #$studentId";

        $res = $flexPay->initiateCardPayment(
            (string)$reference,
            $montant,
            $devise,
            $description,
            $callbackUrl,
            $approveUrl,
            $cancelUrl,
            $declineUrl
        );

        if ($flexPay->isSuccessCode($res['code'] ?? null) && !empty($res['url'])) {
            echo json_encode([
                'success'      => true,
                'redirect_url' => $res['url'],
                'message'      => 'Redirection vers la passerelle sécurisée FlexPay...'
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'message' => $res['message'] ?? 'Erreur lors de l\'initiation de la carte FlexPay'
            ]);
            exit;
        }
    } else {
        // Mobile Money
        if (empty($telephone)) {
            echo json_encode(['success' => false, 'message' => 'Le numéro de téléphone est obligatoire']);
            exit;
        }

        $res = $flexPay->initiateMobileMoneyPayment(
            (string)$reference,
            $montant,
            $devise,
            $telephone,
            $callbackUrl
        );

        if ($flexPay->isSuccessCode($res['code'] ?? null)) {
            $orderNum = $res['orderNumber'] ?? '';
            echo json_encode([
                'success'     => true,
                'orderNumber' => $orderNum,
                'message'     => 'Demande de paiement envoyée avec succès sur votre téléphone. Veuillez valider avec votre code PIN secret. (Commande #' . htmlspecialchars($orderNum) . ')'
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'message' => $res['message'] ?? 'Erreur lors de la requête Mobile Money FlexPay'
            ]);
            exit;
        }
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
