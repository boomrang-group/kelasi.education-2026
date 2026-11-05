<?php
// repetitorats/teachers/services/quiz-details-services.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$dbPaths = array(
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
);

foreach ($dbPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        break;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : base de données indisponible.');
}

$userId = (int) $_SESSION['user_id'];
$codeEcole = trim((string) ($_SESSION['code_ecole'] ?? ''));
$quizId = (int) ($_GET['id'] ?? $_POST['quiz_id'] ?? 0);

$nomComplet = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
if ($nomComplet === '') {
    $nomComplet = (string) ($_SESSION['username'] ?? 'Répétiteur');
}
$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

$successMsg = '';
$errorMsg = '';
$quiz = null;
$questionsList = array();

// Vérifier que le quiz appartient bien au répétiteur
if ($quizId > 0) {
    try {
        $stQ = $pdo->prepare(
            'SELECT q.*, c.nom AS cours_nom 
             FROM quiz_repetitorat q 
             LEFT JOIN cours_repetitorat c ON q.cours_id = c.id 
             WHERE q.id = :qid AND q.teacher_user_id = :uid AND q.code_ecole = :ce LIMIT 1'
        );
        $stQ->execute(array(':qid' => $quizId, ':uid' => $userId, ':ce' => $codeEcole));
        $quiz = $stQ->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            header('Location: ../mes_quiz.php');
            exit;
        }
    } catch (Throwable $e) {
        header('Location: ../mes_quiz.php');
        exit;
    }
} else {
    header('Location: ../mes_quiz.php');
    exit;
}

// =====================================================
// TRAITEMENT DE L'AJOUT D'UNE QUESTION / ASSERTIONS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'add_question') {
        $questionTexte = trim((string) ($_POST['question_texte'] ?? ''));
        $typeQuestion = strtolower(trim((string) ($_POST['type_question'] ?? 'qcm')));
        $points = (int) ($_POST['points'] ?? 1);
        $options = $_POST['options'] ?? array();
        $bonneOptionIndex = (int) ($_POST['bonne_option'] ?? 0);

        if ($typeQuestion !== 'qcm' && $typeQuestion !== 'qr') {
            $typeQuestion = 'qcm';
        }

        if ($questionTexte === '') {
            $errorMsg = 'Le libellé de la question est obligatoire.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insérer la question
                $stInsQ = $pdo->prepare(
                    'INSERT INTO quiz_questions_repetitorat (quiz_id, question_texte, type_question, points) VALUES (:qid, :texte, :type, :pts)'
                );
                $stInsQ->execute(array(
                    ':qid'   => $quizId,
                    ':texte' => $questionTexte,
                    ':type'  => $typeQuestion,
                    ':pts'   => $points,
                ));
                $questionId = (int) $pdo->lastInsertId();

                // 2. Si c'est un QCM, insérer les options / assertions
                if ($typeQuestion === 'qcm' && is_array($options)) {
                    $stInsOpt = $pdo->prepare(
                        'INSERT INTO quiz_options_repetitorat (question_id, option_texte, est_correcte) VALUES (:q_id, :opt_texte, :correct)'
                    );

                    foreach ($options as $index => $optTexte) {
                        $optTexteClean = trim((string) $optTexte);
                        if ($optTexteClean !== '') {
                            $estCorrecte = ($index === $bonneOptionIndex) ? 1 : 0;
                            $stInsOpt->execute(array(
                                ':q_id'      => $questionId,
                                ':opt_texte' => $optTexteClean,
                                ':correct'   => $estCorrecte,
                            ));
                        }
                    }
                }

                $pdo->commit();
                $successMsg = 'Question enregistrée avec succès.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                $errorMsg = 'Erreur lors de l’enregistrement de la question : ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_question') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        if ($questionId > 0) {
            try {
                $stDel = $pdo->prepare('DELETE FROM quiz_questions_repetitorat WHERE id = :id AND quiz_id = :qid');
                $stDel->execute(array(':id' => $questionId, ':qid' => $quizId));
                $successMsg = 'Question supprimée avec succès.';
            } catch (Throwable $e) {
                $errorMsg = 'Impossible de supprimer cette question.';
            }
        }
    }
}

// =====================================================
// RÉCUPÉRATION DES QUESTIONS ET DE LEURS OPTIONS
// =====================================================
try {
    $stQuestions = $pdo->prepare('SELECT * FROM quiz_questions_repetitorat WHERE quiz_id = :qid ORDER BY id ASC');
    $stQuestions->execute(array(':qid' => $quizId));
    $questionsList = $stQuestions->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questionsList as &$q) {
        if ($q['type_question'] === 'qcm') {
            $stOpts = $pdo->prepare('SELECT * FROM quiz_options_repetitorat WHERE question_id = :q_id ORDER BY id ASC');
            $stOpts->execute(array(':q_id' => $q['id']));
            $q['options'] = $stOpts->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $q['options'] = array();
        }
    }
    unset($q);
} catch (Throwable $e) {
    // Tables potentiellement vides ou inexistantes
}