<?php
// mykelasi/admin/delete_student.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Connexion DB ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: all-students.php?msg=db_error'); exit;
}

// --- Contexte école & droits (simple) ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
$studentId = (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) ? (int)$_GET['id'] : 0;
if ($studentId <= 0) { header('Location: all-students.php?msg=invalid'); exit; }

// Vérifier existence et appartenance à l'école
$sql = "SELECT id FROM students WHERE id = :id";
$params = [':id'=>$studentId];
if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce']=$codeEcole; }
$sql .= " LIMIT 1";

$st = $pdo->prepare($sql);
$st->execute($params);
if (!$st->fetchColumn()) {
    header('Location: all-students.php?msg=not_found'); exit;
}

try {
    $pdo->beginTransaction();

    // Nettoyage facultatif : quiz_submissions
    try {
        $st = $pdo->prepare("DELETE FROM quiz_submissions WHERE student_id = :sid");
        $st->execute([':sid'=>$studentId]);
    } catch (Throwable $e) { /* ignore */ }

    // Nettoyage facultatif : paiements
    try {
        $st = $pdo->prepare("DELETE FROM paiement WHERE eleve = :sid");
        $st->execute([':sid'=>$studentId]);
    } catch (Throwable $e) { /* ignore */ }

    // Nettoyage facultatif : messages envoyés par l'élève
    try {
        $st = $pdo->prepare("DELETE FROM messages WHERE sender_type='eleve' AND sender_id = :sid");
        $st->execute([':sid'=>$studentId]);
    } catch (Throwable $e) { /* ignore */ }

    // Suppression élève
    $sqlDel = "DELETE FROM students WHERE id = :sid";
    $paramsDel = [':sid'=>$studentId];
    if ($codeEcole !== '') { $sqlDel .= " AND code_ecole = :ce"; $paramsDel[':ce']=$codeEcole; }

    $st = $pdo->prepare($sqlDel);
    $st->execute($paramsDel);

    $pdo->commit();
    header('Location: all-students.php?msg=deleted'); exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Si contrainte FK bloque, renvoyer un message explicite
    header('Location: all-students.php?msg=delete_failed'); exit;
}
