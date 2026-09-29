<?php
// customs/students/view/service/enregistrer_paiement_cash.php

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../../database/db_connect.php';

/* ===== SÉCURITÉ ===== */
if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    echo json_encode([
        'success' => false,
        'message' => 'Accès refusé'
    ]);
    exit;
}

$studentId  = $_SESSION['student_id'] ?? null;
$code_ecole = $_SESSION['code_ecole'] ?? null;

$type    = $_POST['type']    ?? null;
$montant = $_POST['montant'] ?? null;
$mode    = $_POST['mode']    ?? 'cash';

/* ===== VALIDATIONS ===== */
$typesAutorises = ['Inscription','Minerval','Autre'];

if (!$studentId || !$code_ecole) {
    echo json_encode(['success'=>false,'message'=>'Session invalide']);
    exit;
}

if (!in_array($type, $typesAutorises, true)) {
    echo json_encode(['success'=>false,'message'=>'Type de paiement invalide']);
    exit;
}

if (!is_numeric($montant) || $montant <= 0) {
    echo json_encode(['success'=>false,'message'=>'Montant invalide']);
    exit;
}

$montant = (float)$montant;

/* ===== CALCUL SOLDE ===== */
try {
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(montant_paye),0)
        FROM paiement
        WHERE eleve = :e AND statut = :s AND is_validated = 1
    ");
    $st->execute([
        ':e' => $studentId,
        ':s' => $type
    ]);
    $dejaPaye = (float)$st->fetchColumn();
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Erreur calcul solde']);
    exit;
}

$solde = 0; // sera recalculé après validation admin

/* ===== INSERTION ===== */
try {
    $st = $pdo->prepare("
        INSERT INTO paiement
        (reference, statut, eleve, montant_paye, solde, mode, date_paiement, code_ecole, is_validated)
        VALUES
        (:ref, :statut, :eleve, :montant, :solde, :mode, NOW(), :ecole, 0)
    ");

    $st->execute([
        ':ref'     => time(), // référence simple unique
        ':statut'  => $type,
        ':eleve'   => $studentId,
        ':montant' => $montant,
        ':solde'   => $solde,
        ':mode'    => $mode,
        ':ecole'   => $code_ecole
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Paiement cash enregistré'
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'debug'   => $e->getMessage() // à enlever en prod
    ]);
    exit;
}
