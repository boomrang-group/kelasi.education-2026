<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../database/db_connect.php';

$codeEcole = $_SESSION['code_ecole'] ?? '';

if (!$codeEcole) {
    echo json_encode([
        'montant_total' => 0,
        'deja_paye' => 0,
        'reste' => 0
    ]);
    exit;
}

$eleveId = (int)($_GET['eleve'] ?? 0);
$statut  = trim($_GET['statut'] ?? '');

if (!$eleveId || !$statut) {
    echo json_encode([
        'montant_total' => 0,
        'deja_paye' => 0,
        'reste' => 0
    ]);
    exit;
}

/* CLASSE DE L'ELEVE */

$stmt = $pdo->prepare("
SELECT class_id
FROM students
WHERE id = ?
LIMIT 1
");

$stmt->execute([$eleveId]);

$classeId = (int)$stmt->fetchColumn();

if (!$classeId) {
    echo json_encode([
        'montant_total' => 0,
        'deja_paye' => 0,
        'reste' => 0
    ]);
    exit;
}

/* MONTANT TOTAL */

$montantTotal = 0;

if ($statut === 'Inscription') {

    $stmt = $pdo->prepare("
    SELECT montant
    FROM frais_d_inscription
    WHERE classe = ?
    AND code_ecole = ?
    LIMIT 1
    ");

    $stmt->execute([$classeId, $codeEcole]);

    $montantTotal = (float)($stmt->fetchColumn() ?: 0);

} elseif ($statut === 'Minerval') {

    $stmt = $pdo->prepare("
    SELECT montant
    FROM minerval
    WHERE classe = ?
    AND code_ecole = ?
    LIMIT 1
    ");

    $stmt->execute([$classeId, $codeEcole]);

    $montantTotal = (float)($stmt->fetchColumn() ?: 0);

} elseif ($statut === 'Autre') {

    $stmt = $pdo->prepare("
    SELECT montant
    FROM autres_frais
    WHERE classe = ?
    AND code_ecole = ?
    LIMIT 1
    ");

    $stmt->execute([$classeId, $codeEcole]);

    $montantTotal = (float)($stmt->fetchColumn() ?: 0);
}

/* TOTAL PAYE */

$stmt = $pdo->prepare("
SELECT COALESCE(SUM(montant_paye),0)
FROM paiement
WHERE eleve = ?
AND statut = ?
AND code_ecole = ?
");

$stmt->execute([
    $eleveId,
    $statut,
    $codeEcole
]);

$dejaPaye = (float)$stmt->fetchColumn();

$reste = $montantTotal - $dejaPaye;

if ($reste < 0) {
    $reste = 0;
}

echo json_encode([
    'montant_total' => $montantTotal,
    'deja_paye'     => $dejaPaye,
    'reste'         => $reste
]);
exit;