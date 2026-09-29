<?php
declare(strict_types=1);
require_once __DIR__ . '/../../database/db_connect.php';

try {
    $stmt = $pdo->query("SELECT * FROM evaluee ORDER BY date_eval DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
?>