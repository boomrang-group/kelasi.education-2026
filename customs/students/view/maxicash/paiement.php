<?php
// customs/students/view/maxicash/paiement.php
// Redirection fluide vers la page de paiement principale de l'élève

$type    = $_GET['type'] ?? '';
$montant = $_GET['montant'] ?? '';

$params = [];
if (!empty($type)) {
    $params['type'] = $type;
}
if (!empty($montant)) {
    $params['montant'] = $montant;
}

$queryString = !empty($params) ? '?' . http_build_query($params) : '';

header('Location: ../paiement.php' . $queryString);
exit;
