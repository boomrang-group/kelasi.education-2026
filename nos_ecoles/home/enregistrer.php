<?php
require '../../database/db_connect.php';

// Récupération des données JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validation des données
if (!isset($data['url_ecole'], $data['evaluateur'], $data['total_note'], $data['note_sur_5'], $data['mention'])) {
    http_response_code(400);
    echo "Données manquantes.";
    exit;
}

// Sécurisation et transformation des données
$url_ecole = $data['url_ecole'];
$evaluateur = htmlspecialchars(trim($data['evaluateur']));
$total_note = floatval($data['total_note']);
$note_sur_5 = floatval($data['note_sur_5']);
$mention = htmlspecialchars(trim($data['mention']));

try {
    // Préparation de la requête SQL
    $stmt = $pdo->prepare("INSERT INTO evaluee (url_ecole, evaluateur, total_note, note_sur_5, mention) VALUES (?, ?, ?, ?, ?)");
    
    // Exécution de la requête
    $stmt->execute([$url_ecole, $evaluateur, $total_note, $note_sur_5, $mention]);

    echo "OK";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur lors de l'enregistrement : " . $e->getMessage();
}
?>
