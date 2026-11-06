<?php
// customs/teacher/view/live.php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
require_once '../../../service/LiveClassService.php';

// Sécurité rôle prof
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$username   = $_SESSION['username']   ?? 'Professeur';
$code_ecole = $_SESSION['code_ecole'] ?? '';

$liveService = new LiveClassService($pdo);

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Action POST (créer, démarrer, terminer)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_live') {
        $coursId      = (int)($_POST['cours_id'] ?? 0);
        $classId      = (int)($_POST['class_id'] ?? 0);
        $titre        = trim((string)($_POST['titre'] ?? ''));
        $description  = trim((string)($_POST['description'] ?? ''));
        $dateDebut    = trim((string)($_POST['date_debut'] ?? ''));
        $dureeMinutes = (int)($_POST['duree_minutes'] ?? 60);

        if ($coursId > 0 && $classId > 0 && $titre !== '' && $dateDebut !== '') {
            try {
                $liveId = $liveService->createLiveSession(
                    $coursId,
                    $classId,
                    $userId,
                    $titre,
                    $description,
                    $dateDebut,
                    $dureeMinutes,
                    $code_ecole
                );
                header("Location: live.php?session_id={$liveId}&msg=created");
                exit;
            } catch (Throwable $e) {
                $msg = '<div class="alert alert-danger">Erreur lors de la programmation : '.e($e->getMessage()).'</div>';
            }
        } else {
            $msg = '<div class="alert alert-warning">Veuillez renseigner tous les champs obligatoires (Cours, Titre, Date & heure).</div>';
        }
    } elseif ($action === 'start_live') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $liveService->startLiveSession($sessionId, $userId, $code_ecole);
            header("Location: live.php?session_id={$sessionId}&msg=started");
            exit;
        }
    } elseif ($action === 'end_live') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $liveService->endLiveSession($sessionId, $userId, $code_ecole);
            header("Location: live.php?msg=ended");
            exit;
        }
    }
}

// Paramètres de vue
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$activeSession = null;
if ($sessionId > 0) {
    $activeSession = $liveService->getLiveSession($sessionId, $code_ecole);
    if (!$activeSession || (int)$activeSession['teacher_user_id'] !== $userId) {
        $activeSession = null;
    }
}

// Récupérer la liste des classes et cours du professeur pour le formulaire de création
$classesStmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.classe, c.description, c.niveau, c.section, c.options
    FROM classes c
    JOIN class_subject_teacher cst ON cst.class_id = c.id
    WHERE cst.teacher_user_id = :uid AND c.code_ecole = :ce
    ORDER BY c.classe
");
$classesStmt->execute([':uid' => $userId, ':ce' => $code_ecole]);
$myClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$preselectedCourseId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;

$coursStmt = $pdo->prepare("
    SELECT id, nom, class
    FROM cours
    WHERE teacher_user_id = :uid AND code_ecole = :ce
    ORDER BY nom
");
$coursStmt->execute([':uid' => $userId, ':ce' => $code_ecole]);
$myCourses = $coursStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les sessions live du prof
$allSessions = $liveService->getLivesForTeacher($userId, $code_ecole);

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $msg = '<div class="alert alert-success">Session en direct programmée avec succès !</div>';
    if ($_GET['msg'] === 'started') $msg = '<div class="alert alert-info">La session est désormais EN DIRECT pour les élèves.</div>';
    if ($_GET['msg'] === 'ended')   $msg = '<div class="alert alert-secondary">La session en direct a été clôturée.</div>';
}
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Cours en direct (Jitsi Meet) | MyKelasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <!-- Jitsi Meet External API -->
    <script src="https://meet.jit.si/external_api.js"></script>
    <style>
        .jitsi-container {
            width: 100%;
            height: 700px;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .badge-live {
            background-color: #dc3545;
            color: white;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div id="wrapper" class="wrapper bg-ash">
        <?php include '../layout/navbar.php'; ?>
        <div class="dashboard-page-one">
            <?php include '../layout/sidebar.php'; ?>
            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3>📡 Cours en direct (Visioconférence Jitsi)</h3>
                    <ul>
                        <li><a href="dashboard.php">Accueil</a></li>
                        <li>Cours en direct</li>
                    </ul>
                </div>

                <?= $msg ?>

                <?php if ($activeSession): ?>
                    <!-- SALLE VIRTUELLE EN DIRECT -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 text-white">
                                    <i class="fas fa-video text-danger mr-2"></i>
                                    <?= e($activeSession['titre']) ?> — <em><?= e($activeSession['cours_nom'] ?? 'Cours') ?></em>
                                </h5>
                                <small class="text-light">
                                    Salle : <?= e($activeSession['room_name']) ?> | Statut :
                                    <span class="badge <?= $activeSession['status'] === 'live' ? 'badge-live' : 'badge-warning' ?>">
                                        <?= strtoupper(e($activeSession['status'])) ?>
                                    </span>
                                </small>
                            </div>
                            <div class="d-flex" style="gap: 10px;">
                                <?php if ($activeSession['status'] !== 'live'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="start_live">
                                        <input type="hidden" name="session_id" value="<?= (int)$activeSession['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-play"></i> Démarrer le live (ouvrir aux élèves)
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment terminer la session live ?');">
                                        <input type="hidden" name="action" value="end_live">
                                        <input type="hidden" name="session_id" value="<?= (int)$activeSession['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-stop"></i> Terminer le live
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="live.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-list"></i> Liste des sessions
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Conteneur iframe Jitsi -->
                            <div id="jitsi-meet-container" class="jitsi-container"></div>
                        </div>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            const domain = 'meet.jit.si';
                            const options = {
                                roomName: '<?= e($activeSession['room_name']) ?>',
                                width: '100%',
                                height: 700,
                                parentNode: document.querySelector('#jitsi-meet-container'),
                                userInfo: {
                                    displayName: '<?= addslashes($username) ?> (Professeur)'
                                },
                                configOverwrite: {
                                    prejoinPageEnabled: false,
                                    startWithAudioMuted: false,
                                    startWithVideoMuted: false
                                },
                                interfaceConfigOverwrite: {
                                    TOOLBAR_BUTTONS: [
                                        'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                                        'fodeviceselection', 'hangup', 'chat', 'raisehand',
                                        'videoquality', 'filmstrip', 'tileview'
                                    ]
                                }
                            };
                            const api = new JitsiMeetExternalAPI(domain, options);
                        });
                    </script>

                <?php else: ?>

                    <!-- FORMULAIRE DE PLANIFICATION & LISTE DES SESSIONS -->
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0 text-white"><i class="fas fa-calendar-plus mr-2"></i> Planifier un cours en direct</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="live.php">
                                        <input type="hidden" name="action" value="create_live">

                                        <div class="form-group mb-3">
                                            <label class="font-weight-bold">Cours rattaché *</label>
                                            <select name="cours_id" id="cours_select" class="form-control" required onchange="updateClassFromCourse(this)">
                                                <option value="">Sélectionnez un cours</option>
                                                <?php foreach ($myCourses as $c): ?>
                                                    <?php $isSelected = ($preselectedCourseId === (int)$c['id']) ? 'selected' : ''; ?>
                                                    <option value="<?= (int)$c['id'] ?>" data-class="<?= (int)$c['class'] ?>" <?= $isSelected ?>>
                                                        <?= e($c['nom']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="font-weight-bold">Classe concernée *</label>
                                            <select name="class_id" id="class_select" class="form-control" required>
                                                <option value="">Sélectionnez une classe</option>
                                                <?php foreach ($myClasses as $cl): ?>
                                                    <?php
                                                        $label = trim(($cl['classe'] ?? '') . ' ' . ($cl['description'] ?? '') . ' ' . ($cl['niveau'] ?? '') . ' ' . ($cl['section'] ?? '') . ' ' . ($cl['options'] ?? ''));
                                                        if ($label === '') $label = 'Classe #' . (int)$cl['id'];
                                                    ?>
                                                    <option value="<?= (int)$cl['id'] ?>">
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="font-weight-bold">Titre de la session *</label>
                                            <input type="text" name="titre" class="form-control" placeholder="ex: Révision Chapitre 3" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="font-weight-bold">Description (optionnelle)</label>
                                            <textarea name="description" class="form-control" rows="2" placeholder="Objectifs de la session en direct..."></textarea>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="font-weight-bold">Date et Heure de début *</label>
                                            <input type="datetime-local" name="date_debut" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                                        </div>

                                        <div class="form-group mb-4">
                                            <label class="font-weight-bold">Durée estimée (minutes)</label>
                                            <input type="number" name="duree_minutes" class="form-control" value="60" min="15" step="5">
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-block w-100">
                                            <i class="fas fa-video mr-1"></i> Créer la session
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0 text-white"><i class="fas fa-list mr-2"></i> Mes sessions en direct</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!$allSessions): ?>
                                        <p class="text-muted mb-0">Aucun cours en direct programmé pour le moment.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Titre</th>
                                                        <th>Cours</th>
                                                        <th>Date & Heure</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($allSessions as $s): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= e($s['titre']) ?></strong>
                                                                <?php if (!empty($s['description'])): ?>
                                                                    <div class="text-muted small"><?= e($s['description']) ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= e($s['cours_nom'] ?? '—') ?></td>
                                                            <td><?= date('d/m/Y H:i', strtotime($s['date_debut'])) ?> (<?= (int)$s['duree_minutes'] ?> min)</td>
                                                            <td>
                                                                <?php if ($s['status'] === 'live'): ?>
                                                                    <span class="badge badge-live">EN DIRECT</span>
                                                                <?php elseif ($s['status'] === 'scheduled'): ?>
                                                                    <span class="badge badge-warning">PROGRAMMÉ</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">TERMINÉ</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($s['status'] === 'live'): ?>
                                                                    <a href="live.php?session_id=<?= (int)$s['id'] ?>" class="btn btn-danger btn-sm">
                                                                        <i class="fas fa-video"></i> Rejoindre la salle
                                                                    </a>
                                                                <?php elseif ($s['status'] === 'scheduled'): ?>
                                                                    <a href="live.php?session_id=<?= (int)$s['id'] ?>" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-play"></i> Démarrer le live
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Terminé</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function updateClassFromCourse(select) {
                            const selectedOption = select.options[select.selectedIndex];
                            const classId = selectedOption.getAttribute('data-class');
                            if (classId) {
                                document.getElementById('class_select').value = classId;
                            }
                        }
                        document.addEventListener("DOMContentLoaded", function () {
                            const sel = document.getElementById('cours_select');
                            if (sel && sel.value) {
                                updateClassFromCourse(sel);
                            }
                        });
                    </script>
                <?php endif; ?>

                <?php include '../layout/footer.php'; ?>
            </div>
        </div>
    </div>
    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/plugins.js"></script>
    <script src="../../../js/popper.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script src="../../../js/main.js"></script>
</body>
</html>
