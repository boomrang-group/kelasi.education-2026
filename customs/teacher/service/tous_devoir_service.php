<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../database/db_connect.php';

$sql = "SELECT * FROM assignments";
$stmt = $pdo->query($sql);
$listDesDevoirEnvoyes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>