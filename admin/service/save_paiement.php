<?php
session_start();
require_once __DIR__.'/../../database/db_connect.php';

$codeEcole = $_SESSION['code_ecole'] ?? '';

$statut          = $_POST['statut'] ?? '';
$eleve           = (int)($_POST['eleve'] ?? 0);
$montant_a_payer = (int)($_POST['solde_restant'] ?? 0);
$montant_paye    = (float)($_POST['montant_paye'] ?? 0);
$solde           = (float)($_POST['solde'] ?? 0);

if (empty($statut) || empty($eleve)) {
    die("Données invalides");
}

/**
 * Génération d'une référence unique
 */
function generateReference(PDO $pdo): int
{
    do {
        $reference = random_int(10000000, 99999999);

        $check = $pdo->prepare("
            SELECT id
            FROM paiement
            WHERE reference = ?
            LIMIT 1
        ");
        $check->execute([$reference]);

    } while ($check->fetch());

    return $reference;
}

$reference = generateReference($pdo);

$sql = "
    INSERT INTO paiement
    (
        reference,
        statut,
        eleve, 
        montant_a_payer,
        montant_paye,
        solde,
        mode,
        date_paiement,
        code_ecole,
        is_validated
    )
    VALUES
    (
        :reference,
        :statut,
        :eleve, 
        :montant_a_payer,
        :montant_paye,
        :solde,
        'Paiement en présentiel',
        NOW(),
        :code_ecole,
        1
    )
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':reference'       => $reference,
    ':statut'          => $statut,
    ':eleve'           => $eleve, 
    ':montant_a_payer' => $montant_a_payer, 
    ':montant_paye'    => $montant_paye,
    ':solde'           => $solde,
    ':code_ecole'      => $codeEcole
]);

header("Location: ../paiement.php");
exit;