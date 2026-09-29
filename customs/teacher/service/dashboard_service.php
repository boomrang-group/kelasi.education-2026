<?php
// customs/teacher/service/dashboard_service.php
// Prépare les données du tableau de bord prof.

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

// --- Garde d'accès : uniquement PROF ---
if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php');
    exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$teacherId  = $_SESSION['teacher_id'] ?? null; // rempli à la connexion prof
$username   = $_SESSION['username']   ?? null;

$classId = null;

// 1) chercher l'affectation par teacher_user_id
if ($teacherId) {
    $sql = "SELECT class_id
            FROM class_subject_teacher
            WHERE teacher_user_id = :tid " . ($code_ecole ? "AND code_ecole = :ce " : "") . "
            ORDER BY id DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $params = [':tid' => $teacherId];
    if ($code_ecole) $params[':ce'] = $code_ecole;
    $st->execute($params);
    $classId = $st->fetchColumn() ?: null;
}

// 2) fallback par username si pas trouvé
if (!$classId && $username) {
    $sql = "SELECT class_id
            FROM class_subject_teacher
            WHERE username = :u " . ($code_ecole ? "AND code_ecole = :ce " : "") . "
            ORDER BY id DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $params = [':u' => $username];
    if ($code_ecole) $params[':ce'] = $code_ecole;
    $st->execute($params);
    $classId = $st->fetchColumn() ?: null;
}

$classeData     = null;
$students       = [];
$nombreEleves   = 0;
$nombreTravaux  = 0;

if ($classId) {
    // Détails de la classe
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.classe,
            c.description,
            n.description  AS niveau,
            s.description  AS section,
            o.description  AS options
        FROM classes c
        LEFT JOIN niveau  n ON c.niveau  = n.id
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN options o ON c.options = o.id
        WHERE c.id = :cid " . ($code_ecole ? "AND c.code_ecole = :ce" : "") . "
        LIMIT 1
    ");
    $params = [':cid' => $classId];
    if ($code_ecole) $params[':ce'] = $code_ecole;
    $stmt->execute($params);
    $classeData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Liste des élèves
    $stmt = $pdo->prepare("
        SELECT
            st.id AS students_id,
            st.first_name,
            st.last_name,
            st.gender,
            st.phone,
            st.email,
            st.date_of_birth
        FROM students st
        WHERE st.class_id = :cid " . ($code_ecole ? "AND st.code_ecole = :ce" : "") . "
        ORDER BY st.last_name, st.first_name
    ");
    $params = [':cid' => $classId];
    if ($code_ecole) $params[':ce'] = $code_ecole;
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compteurs
    $nombreEleves = count($students);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assignments
        WHERE classe = :cid " . ($code_ecole ? "AND code_ecole = :ce" : "") . "
    ");
    $params = [':cid' => $classId];
    if ($code_ecole) $params[':ce'] = $code_ecole;
    $stmt->execute($params);
    $nombreTravaux = (int)$stmt->fetchColumn();
}

// Ces variables sont utilisées par la vue dashboard.php :
// $classeData, $students, $nombreEleves, $nombreTravaux
