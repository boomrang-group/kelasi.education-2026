<?php
// repetitorats/students/passer_quiz.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || !in_array($role, ['eleve', 'eleve_encadre'], true)) {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$pdo = null;
$dbPaths = [
    __DIR__ . '/../../../database/db_connect.php',
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
];

foreach ($dbPaths as $path) {
    if (is_file($path)) { require_once $path; break; }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur : base de données indisponible.');
}

$userId = (int) $_SESSION['user_id'];
$quizId = (int) ($_GET['id'] ?? 0);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Charger le quiz
$stQ = $pdo->prepare("
    SELECT q.*, c.nom AS cours_nom 
    FROM quiz_repetitorat q
    LEFT JOIN cours_repetitorat c ON q.cours_id = c.id
    WHERE q.id = :id AND q.is_published = 1
    LIMIT 1
");
$stQ->execute([':id' => $quizId]);
$quiz = $stQ->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: mes_quiz.php');
    exit;
}

// Vérifier si l'élève a déjà soumis ce quiz
$stRes = $pdo->prepare("SELECT * FROM quiz_resultats_repetitorat WHERE quiz_id = :qid AND student_id = :uid LIMIT 1");
$stRes->execute([':qid' => $quizId, ':uid' => $userId]);
$resultatExistant = $stRes->fetch(PDO::FETCH_ASSOC);

// Traitement de la soumission du Formulaire de travail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$resultatExistant) {
    $reponses = $_POST['reponses'] ?? [];
    
    // Récupérer toutes les questions et les bonnes options
    $stQuestions = $pdo->prepare("SELECT * FROM quiz_questions_repetitorat WHERE quiz_id = :qid");
    $stQuestions->execute([':qid' => $quizId]);
    $questions = $stQuestions->fetchAll(PDO::FETCH_ASSOC);

    $totalPointsTotal = 0;
    $totalPointsObtenus = 0;

    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $pts = (float)$q['points'];
        $totalPointsTotal += $pts;

        if ($q['type_question'] === 'qcm') {
            // Chercher l'option correcte
            $stOpt = $pdo->prepare("SELECT id FROM quiz_options_repetitorat WHERE question_id = :qid AND est_correcte = 1 LIMIT 1");
            $stOpt->execute([':qid' => $qId]);
            $bonneOptId = (int)$stOpt->fetchColumn();

            $optChoisie = isset($reponses[$qId]) ? (int)$reponses[$qId] : 0;
            if ($optChoisie > 0 && $optChoisie === $bonneOptId) {
                $totalPointsObtenus += $pts;
            }
        } elseif ($q['type_question'] === 'qr') {
            // Question rédactionnelle (accorde la totalité si répondu)
            if (!empty($reponses[$qId]) && trim((string)$reponses[$qId]) !== '') {
                $totalPointsObtenus += $pts;
            }
        }
    }

    // Calcul de la note ramenée sur 20
    $noteSur20 = ($totalPointsTotal > 0) ? round(($totalPointsObtenus / $totalPointsTotal) * 20, 2) : 20.0;

    // Insertion du résultat
    $stIns = $pdo->prepare("
        INSERT INTO quiz_resultats_repetitorat (quiz_id, student_id, note_obtenue, submitted_at)
        VALUES (:qid, :uid, :note, NOW())
    ");
    $stIns->execute([
        ':qid'  => $quizId,
        ':uid'  => $userId,
        ':note' => $noteSur20
    ]);

    header('Location: mes_quiz.php?msg=submitted');
    exit;
}

// Récupération des questions et options pour l'affichage
$questionsList = [];
$stQuest = $pdo->prepare("SELECT * FROM quiz_questions_repetitorat WHERE quiz_id = :qid ORDER BY id ASC");
$stQuest->execute([':qid' => $quizId]);
$rawQuestions = $stQuest->fetchAll(PDO::FETCH_ASSOC);

foreach ($rawQuestions as $q) {
    $qId = (int)$q['id'];
    $opts = [];
    if ($q['type_question'] === 'qcm') {
        $stOpt = $pdo->prepare("SELECT id, option_texte FROM quiz_options_repetitorat WHERE question_id = :qid");
        $stOpt->execute([':qid' => $qId]);
        $opts = $stOpt->fetchAll(PDO::FETCH_ASSOC);
    }
    $q['options'] = $opts;
    $questionsList[] = $q;
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Travail du Quiz - <?= h($quiz['titre']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../../css/normalize.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../style.css">
    <script src="../../js/modernizr-3.6.0.min.js"></script>

    <style>
    .card-box {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .06);
        padding: 25px;
        margin-bottom: 25px;
    }

    .question-block {
        border-left: 4px solid #0d6efd;
        background-color: #f8f9fa;
        padding: 18px;
        border-radius: 0 8px 8px 0;
        margin-bottom: 20px;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <?php require_once __DIR__ . '/includes/navbar.php'; ?>

        <div class="dashboard-page-one">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area mb-4">
                    <a href="mes_quiz.php" class="btn btn-sm btn-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>
                        Retour aux quiz</a>
                    <h3 style="text-transform: uppercase;"><?= h($quiz['titre']) ?></h3>
                    <p class="muted">Matière : <strong><?= h($quiz['cours_nom'] ?? 'Général') ?></strong> | Format :
                        <span class="badge bg-primary text-uppercase"><?= h($quiz['type_quiz']) ?></span></p>
                </div>

                <?php if ($resultatExistant): ?>
                <!-- Si le quiz a déjà été effectué -->
                <div class="card-box text-center py-4">
                    <i class="bi bi-check-circle-fill text-success fs-1"></i>
                    <h3 class="fw-bold mt-2">Vous avez déjà soumis cette évaluation !</h3>
                    <p class="text-muted">Votre note obtenue :</p>
                    <h1 class="display-4 text-primary fw-bold"><?= (float)$resultatExistant['note_obtenue'] ?> / 20</h1>
                    <p class="small text-muted">Soumis le : <?= h($resultatExistant['submitted_at']) ?></p>
                    <a href="mes_quiz.php" class="btn btn-primary mt-3">Retour à la liste</a>
                </div>
                <?php else: ?>
                <!-- Formulaire de travail du quiz -->
                <div class="card-box">
                    <?php if (!empty($quiz['description'])): ?>
                    <div class="alert alert-info mb-4">
                        <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i> Consignes :</h6>
                        <p class="mb-0"><?= h($quiz['description']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quiz['fichier_pj'])): ?>
                    <div class="mb-4 p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-2"><i class="bi bi-paperclip text-primary me-1"></i> Pièce jointe / Sujet
                            d'évaluation :</h6>
                        <a href="../../uploads/quiz_repetitorats/<?= h($quiz['fichier_pj']) ?>" target="_blank"
                            class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-download me-1"></i> Télécharger / Consulter le fichier sujet
                        </a>
                    </div>
                    <?php endif; ?>

                    <form action="passer_quiz.php?id=<?= (int)$quizId ?>" method="POST">
                        <?php if (!empty($questionsList)): foreach ($questionsList as $idx => $q): ?>
                        <div class="question-block">
                            <h5 class="fw-bold text-dark mb-3">
                                Question <?= $idx + 1 ?> : <?= h($q['question_texte']) ?>
                                <span class="badge bg-secondary fs-6 ms-2">(<?= (int)$q['points'] ?> pts)</span>
                            </h5>

                            <?php if ($q['type_question'] === 'qcm' && !empty($q['options'])): ?>
                            <div class="form-check-group">
                                <?php foreach ($q['options'] as $opt): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="reponses[<?= (int)$q['id'] ?>]"
                                        value="<?= (int)$opt['id'] ?>" id="opt_<?= (int)$opt['id'] ?>" required>
                                    <label class="form-check-label fw-medium" for="opt_<?= (int)$opt['id'] ?>">
                                        <?= h($opt['option_texte']) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <textarea name="reponses[<?= (int)$q['id'] ?>]" class="form-control" rows="3"
                                placeholder="Rédigez votre réponse ici..." required></textarea>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; else: ?>
                        <p class="text-muted text-center py-4">Aucune question dans ce quiz. Répondez au sujet
                            téléchargé si disponible.</p>
                        <?php endif; ?>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success btn-lg fw-bold px-5">
                                <i class="bi bi-send me-2"></i> Soumettre mes réponses
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="../../js/jquery-3.3.1.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>