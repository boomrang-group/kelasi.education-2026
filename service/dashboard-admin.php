<?php
require 'db_connect.php';

$sql = "SELECT COUNT(id) AS total_classes FROM classes";
$stmt = $pdo->query($sql);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$nbre_classe = $result['total_classes'];

$sql = "SELECT COUNT(id) AS total_prof FROM teacher";
$stmt = $pdo->query($sql);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$nbre_prof = $result['total_prof'];

$sql = "SELECT COUNT(id) AS total_eleve FROM students";
$stmt = $pdo->query($sql);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$nbre_eleve = $result['total_eleve'];


?>
