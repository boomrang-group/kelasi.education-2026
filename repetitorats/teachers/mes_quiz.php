<?php
// repetitorats/teachers/mes_quiz.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// =====================================================
// 1. VERIFICATION DE LA SESSION & ROLE
// =====================================================
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $role !== 'repetiteur') {
    header('Location: ../../login/index.php?msg=forbidden');
    exit;
}

// =====================================================
// 2. CONNEXION A LA BASE DE DONNEES
// =====================================================
$dbPaths = [
    __DIR__ . '/../../database/db_connect.php',
    __DIR__ . '/../database/db_connect.php',
    __DIR__ . '/../../../database/db_connect.php',
];

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
$nomComplet = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
if ($nomComplet === '') {
    $nomComplet = (string) ($_SESSION['username'] ?? 'Répétiteur');
}
$nomEcole = (string) ($_SESSION['nom_ecole'] ?? 'Raynée International School');
$urlEcole = (string) ($_SESSION['url_ecole'] ?? 'kelasi.education/@raynee-international');

$successMsg = '';
$errorMsg = '';

// =====================================================
// 3. CREATION OU SUPPRESSION D'UN QUIZ
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'create'));

    if ($action === 'create') {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        $coursId = (int) ($_POST['cours_id'] ?? 0);
        $typeQuiz = strtolower(trim((string) ($_POST['type_quiz'] ?? 'qcm')));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        $typesAutorises = ['qcm', 'qr', 'pj'];
        if (!in_array($typeQuiz, $typesAutorises, true)) {
            $typeQuiz = 'qcm';
        }

        if ($titre === '' || $coursId <= 0) {
            $errorMsg = 'Veuillez renseigner le titre du quiz et sélectionner un cours associé.';
        } elseif ($codeEcole === '') {
            $errorMsg = 'Code école introuvable dans la session.';
        } else {
            $fichierPj = '';
            try {
                // Gestion du fichier joint (PJ) si présent
                if (isset($_FILES['fichier_pj']) && (int) ($_FILES['fichier_pj']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['fichier_pj']['tmp_name'];
                    $origName = basename($_FILES['fichier_pj']['name']);
                    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $origName);
                    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
                    $uploadDir = __DIR__ . '/../../uploads/quiz_repetitorats/';

                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('Impossible de créer le dossier de stockage des quiz.');
                    }

                    $destination = $uploadDir . $fileName;
                    if (!move_uploaded_file($fileTmp, $destination)) {
                        throw new RuntimeException('Échec du déplacement de la pièce jointe.');
                    }
                    $fichierPj = $fileName;
                }

                // Insertion en base de données
                $stIns = $pdo->prepare(
                    'INSERT INTO quiz_repetitorat 
                        (cours_id, teacher_user_id, code_ecole, titre, type_quiz, description, fichier_pj, is_published) 
                     VALUES 
                        (:cid, :uid, :ce, :titre, :type, :desc, :pj, :pub)'
                );
                $stIns->execute([
                    ':cid'   => $coursId,
                    ':uid'   => $userId,
                    ':ce'    => $codeEcole,
                    ':titre' => $titre,
                    ':type'  => $typeQuiz,
                    ':desc'  => $description,
                    ':pj'    => $fichierPj,
                    ':pub'   => $isPublished,
                ]);

                $successMsg = 'Le quiz a été créé et enregistré avec succès.';
            } catch (Throwable $e) {
                error_log('Erreur création quiz : ' . $e->getMessage());
                $errorMsg = 'Erreur lors de l’enregistrement : ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        if ($quizId > 0) {
            try {
                $stDel = $pdo->prepare('DELETE FROM quiz_repetitorat WHERE id = :id AND teacher_user_id = :uid AND code_ecole = :ce');
                $stDel->execute([':id' => $quizId, ':uid' => $userId, ':ce' => $codeEcole]);
                $successMsg = 'Quiz supprimé avec succès.';
            } catch (Throwable $e) {
                $errorMsg = 'Impossible de supprimer ce quiz.';
            }
        }
    }
}

// =====================================================
// 4. RECUPERATION DES COURS ET DES QUIZZES
// =====================================================
$coursList = [];
try {
    $stC = $pdo->prepare('SELECT id, nom FROM cours_repetitorat WHERE teacher_user_id = :uid AND code_ecole = :ce ORDER BY nom ASC');
    $stC->execute([':uid' => $userId, ':ce' => $codeEcole]);
    $coursList = $stC->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$quizzesList = [];
try {
    $stQ = $pdo->prepare(
        'SELECT q.*, c.nom AS cours_nom 
         FROM quiz_repetitorat q 
         LEFT JOIN cours_repetitorat c ON q.cours_id = c.id 
         WHERE q.teacher_user_id = :uid AND q.code_ecole = :ce 
         ORDER BY q.id DESC'
    );
    $stQ->execute([':uid' => $userId, ':ce' => $codeEcole]);
    $quizzesList = $stQ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Table potentiellement absente avant la première création
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Gestion des Quiz & Évaluations</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    :root {
        --sidebar-width: 240px;
        --sidebar-bg: #071932;
        --topbar-bg: #ffffff;
        --body-bg: #f4f6f9;
        --primary-orange: #ff9800;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--body-bg);
        margin: 0;
        color: #333;
    }

    .navbar-top {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: var(--topbar-bg);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        z-index: 1000;
    }

    .brand-box {
        width: var(--sidebar-width);
        background: var(--primary-orange);
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        left: 0;
        top: 0;
        font-weight: 800;
        font-size: 1.4rem;
        color: #fff;
        text-decoration: none;
        gap: 8px;
    }

    .brand-box span {
        font-size: 0.65rem;
        display: block;
        font-weight: 400;
        text-transform: uppercase;
    }

    .topbar-center {
        margin-left: 260px;
    }

    .topbar-school-name {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
        margin: 0;
    }

    .topbar-school-url {
        color: #0d6efd;
        font-size: 0.8rem;
        text-decoration: none;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-name-title {
        font-weight: 700;
        font-size: 0.9rem;
        margin: 0;
        text-align: right;
        color: #333;
    }

    .user-role-title {
        font-size: 0.75rem;
        color: #6c757d;
        margin: 0;
        text-align: right;
    }

    .user-avatar-circle {
        width: 38px;
        height: 38px;
        background: #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        font-size: 1.1rem;
    }

    .sidebar {
        position: fixed;
        top: 70px;
        left: 0;
        width: var(--sidebar-width);
        height: calc(100vh - 70px);
        background: var(--sidebar-bg);
        padding-top: 15px;
        z-index: 999;
        overflow-y: auto;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li a {
        display: flex;
        align-items: center;
        padding: 13px 20px;
        color: #b0bec5;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.2s;
        border-left: 4px solid transparent;
    }

    .sidebar-menu li a i {
        margin-right: 12px;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .sidebar-menu li a:hover,
    .sidebar-menu li.active a {
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
        border-left-color: var(--primary-orange);
    }

    .sidebar-menu li.logout-item a {
        color: #ff5252;
    }

    .sidebar-menu li.logout-item a:hover {
        background: rgba(255, 82, 82, 0.1);
        border-left-color: #ff5252;
        color: #fff;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: 70px;
        padding: 30px;
    }

    .page-title-box h2 {
        font-weight: 800;
        font-size: 1.5rem;
        color: #263238;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .page-title-box p {
        color: #546e7a;
        font-size: 0.95rem;
        margin-bottom: 25px;
    }

    .card-custom {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        margin-bottom: 25px;
    }

    .table th {
        font-weight: 600;
        color: #37474f;
        background-color: #f8f9fa;
    }
    </style>
</head>

<body>

    <div id="wrapper" class="wrapper bg-ash">

        <!-- Inclusion de la Navbar -->
        <?php require_once(__DIR__ . '/includes/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Inclusion de la Sidebar -->
            <?php require_once(__DIR__ . '/includes/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">GESTION DES QUIZ & ÉVALUATIONS</h3>
                    <p>Créez des QCM, des questions ouvertes (QR) ou ajoutez des pièces jointes (PJ) pour tester vos
                        élèves.</p>
                </div>

                <?php if ($successMsg !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($errorMsg !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Formulaire de création de Quiz -->
                <div class="card-custom">
                    <h4 class="text-primary fw-bold mb-4"><i class="bi bi-plus-circle me-2"></i> Créer un nouveau Quiz /
                        Évaluation</h4>
                    <form action="mes_quiz.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Titre du Quiz</label>
                                <input type="text" name="titre" class="form-control"
                                    placeholder="Ex: Évaluation Chapitre 1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Cours / Matière associé</label>
                                <select name="cours_id" class="form-select" required>
                                    <option value="">-- Sélectionner un cours --</option>
                                    <?php foreach ($coursList as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        <?= htmlspecialchars($c['nom'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Type d'évaluation (Format)</label>
                                <select name="type_quiz" class="form-select" required>
                                    <option value="qcm">QCM (Questionnaire à Choix Multiples)</option>
                                    <option value="qr">QR (Question Réponse ouverte)</option>
                                    <option value="pj">PJ (Devoir / Fichier en Pièce Jointe)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fichier d'accompagnement (Optionnel pour PJ)</label>
                                <input type="file" name="fichier_pj" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Instructions / Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Consignes pour l'élève..."></textarea>
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" name="is_published" class="form-check-input" id="pubCheck" value="1"
                                checked>
                            <label class="form-check-label fw-bold text-success" for="pubCheck">Publier immédiatement
                                (visible
                                par les élèves)</label>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                            <i class="fa fa-save me-2"></i> Enregistrer le Quiz
                        </button>
                    </form>
                </div>

                <!-- Liste des Quiz existants -->
                <div class="card-custom">
                    <h4 class="text-dark fw-bold mb-4"><i class="bi bi-ui-checks me-2"></i> Mes quiz enregistrés</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre du Quiz</th>
                                    <th>Cours</th>
                                    <th>Format</th>
                                    <th>Pièce Jointe</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quizzesList)): foreach ($quizzesList as $idx => $q): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($q['titre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($q['cours_nom'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php 
                                $t = strtoupper($q['type_quiz']);
                                $badgeClass = 'bg-secondary';
                                if ($t === 'QCM') $badgeClass = 'bg-primary';
                                elseif ($t === 'QR') $badgeClass = 'bg-warning text-dark';
                                elseif ($t === 'PJ') $badgeClass = 'bg-info text-dark';
                                ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $t ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($q['fichier_pj'])): ?>
                                        <a href="../../uploads/quiz_repetitorats/<?= htmlspecialchars($q['fichier_pj'], ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-paperclip"> Voir PJ</i>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted small">Aucune</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$q['is_published'] === 1): ?>
                                        <span class="badge bg-success">Publié</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Brouillon</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($q['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <!-- Bouton pour gérer les questions et assertions -->
                                        <a href="details_quiz.php?id=<?= (int)$q['id'] ?>"
                                            class="btn btn-sm btn-outline-primary" title="Gérer les questions">
                                            <i class="bi bi-list-columns-reverse"></i> Gérer
                                        </a>

                                        <form action="mes_quiz.php" method="POST" class="d-inline"
                                            onsubmit="return confirm('Voulez-vous vraiment supprimer ce quiz ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="quiz_id" value="<?= (int)$q['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Vous n'avez pas encore créé de
                                        quiz.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>