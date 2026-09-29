<?php

declare(strict_types=1);

session_start();

require_once '../database/db_connect.php';

header('Content-Type: application/json');

$class_id   = (int)($_GET['class_id'] ?? 0);
$code_ecole = $_SESSION['code_ecole'] ?? null;

if (!$class_id || !$code_ecole) {
    echo json_encode([]);
    exit;
}

try {

    $sql = "
        SELECT 
            u.id,
            CONCAT(
                IFNULL(u.first_name,''), 
                ' ', 
                IFNULL(u.last_name,'')
            ) AS professeur

        FROM class_subject_teacher cst

        INNER JOIN users u
            ON u.id = cst.teacher_user_id

        WHERE cst.class_id = :class_id
        AND cst.code_ecole = :code_ecole

        GROUP BY u.id
        ORDER BY professeur ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':class_id'   => $class_id,
        ':code_ecole' => $code_ecole
    ]);

    echo json_encode(
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

} catch(Throwable $e) {

    echo json_encode([]);
}