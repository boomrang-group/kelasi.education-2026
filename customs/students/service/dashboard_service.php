<?php
// customs/students/service/dashboard_service.php
// Prépare les données du tableau de bord élève : classe et enseignants.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../database/db_connect.php';

// --- Sécurité minimale : l'utilisateur doit être connecté en tant qu'élève
if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    // on ne redirige pas automatiquement (tu as dit "pas besoin de session_check"),
    // mais on définit des valeurs vides pour que la vue affiche un message doux.
    $classeData = null;
    $teachers   = [];
    return;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$studentId  = $_SESSION['student_id'] ?? null;
$classId    = $_SESSION['class_id']   ?? null;

// --- Si la classe n'est pas déjà en session, on la retrouve via la table students
try {
    if (!$classId && $studentId) {
        $st = $pdo->prepare("SELECT class_id FROM students WHERE id = :sid LIMIT 1");
        $st->execute([':sid' => $studentId]);
        $classId = $st->fetchColumn() ?: null;
        if ($classId) {
            $_SESSION['class_id'] = (int)$classId; // on la met en session pour les prochaines pages
        }
    }
} catch (Throwable $e) {
    // on laisse $classId = null; la vue affichera un message
}

// --- Récup infos classe (niveau / section / options)
$classeData = null;
if ($classId) {
    try {
        // NB: ta table classes a la colonne `OPTIONS` (majuscules). On l'utilise bien ici.
        $sql = "
            SELECT c.id,
                   c.classe,
                   c.description,
                   n.description AS niveau,
                   s.description AS section,
                   o.description AS options
            FROM classes c
            LEFT JOIN niveau  n ON c.niveau  = n.id
            LEFT JOIN section s ON c.section = s.id
            LEFT JOIN options o ON c.OPTIONS = o.id
            WHERE c.id = :cid
        ";
        // Si tu veux filtrer par école, décommenter les 2 lignes suivantes:
        // if ($code_ecole) { $sql .= " AND c.code_ecole = :ce"; }

        $st = $pdo->prepare($sql);
        $st->bindValue(':cid', $classId, PDO::PARAM_INT);
        // if ($code_ecole) { $st->bindValue(':ce', $code_ecole); }
        $st->execute();
        $classeData = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $classeData = null;
    }
}

// --- Récup "mes enseignants" à partir de l'affectation de classe
// Meilleur lien: class_subject_teacher (class_id) -> teacher(id)
$teachers = [];
if ($classId) {
    try {
        $sql = "
            SELECT 
                t.id          AS teacher_id,
                t.first_name,
                t.last_name,
                t.gender,
                t.phone,
                t.email,
                t.adress
            FROM class_subject_teacher cst
            INNER JOIN teacher t ON t.id = cst.teacher_user_id
            WHERE cst.class_id = :cid
        ";
        // Si tu veux restreindre à l’école, décommente:
        // if ($code_ecole) { $sql .= " AND cst.code_ecole = :ce"; }

        $st = $pdo->prepare($sql);
        $st->bindValue(':cid', $classId, PDO::PARAM_INT);
        // if ($code_ecole) { $st->bindValue(':ce', $code_ecole); }
        $st->execute();
        $teachers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $teachers = [];
    }
}
