<?php
require 'db_connect.php';

// Consolidated 3 separate SELECT COUNT queries into 1 subquery SELECT to reduce round-trips to MySQL DB.
$sql = "SELECT
            (SELECT COUNT(id) FROM classes) AS total_classes,
            (SELECT COUNT(id) FROM teacher) AS total_prof,
            (SELECT COUNT(id) FROM students) AS total_eleve";
$stmt = $pdo->query($sql);
$result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$nbre_classe = $result['total_classes'] ?? 0;
$nbre_prof   = $result['total_prof'] ?? 0;
$nbre_eleve  = $result['total_eleve'] ?? 0;


?>
