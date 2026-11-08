<?php
// customs/students/view/live.php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';
require_once '../../../service/LiveClassService.php';

// Sécurité rôle élève
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? '';
$username   = $_SESSION['username']   ?? '';
$email      = $_SESSION['email']      ?? '';

$liveService = new LiveClassService($pdo);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Retrouver l'élève
$student = null;
try {
    if ($email) {
        $st = $pdo->prepare("SELECT * FROM students WHERE email=:em " . ($code_ecole ? 'AND code_ecole=:ce ' : '') . "LIMIT 1");
        $p = [':em' => $email]; if ($code_ecole) $p[':ce'] = $code_ecole;
        $st->execute($p);
        $student = $st->fetch(PDO::FETCH_ASSOC);
    }
    if (!$student && $username) {
        $st = $pdo->prepare("SELECT * FROM students WHERE username=:un " . ($code_ecole ? 'AND code_ecole=:ce ' : '') . "LIMIT 1");
        $p = [':un' => $username]; if ($code_ecole) $p[':ce'] = $code_ecole;
        $st->execute($p);
        $student = $st->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

if (!$student || empty($student['class_id'])) {
    die("Dossier élève introuvable ou non assigné à une classe.");
}

$studentClassId = (int)$student['class_id'];
$studentFullName = trim(($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? ''));
if ($studentFullName === '') {
    $studentFullName = $username ?: 'Élève';
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$activeSession = null;
$errorMsg = '';

if ($sessionId > 0) {
    $session = $liveService->getLiveSession($sessionId, $code_ecole);
    if (!$session) {
        $errorMsg = "La session demandée n'existe pas.";
    } elseif ((int)$session['class_id'] !== $studentClassId) {
        $errorMsg = "Vous n'avez pas accès à ce cours en direct (classe différente).";
    } elseif ($session['status'] !== 'live') {
        $errorMsg = "Ce cours n'est pas encore lancé par le professeur ou est déjà terminé.";
    } else {
        $activeSession = $session;
    }
}

// Récupérer toutes les sessions pour la classe de l'élève
$classSessions = $liveService->getLivesForStudent($studentClassId, $code_ecole);
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Mes cours en direct | MyKelasi</title>
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
                    <h3>Cours en direct</h3>
                    <ul>
                        <li><a href="dashboard.php">Accueil</a></li>
                        <li>Cours en direct</li>
                    </ul>
                </div>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><?= h($errorMsg) ?></div>
                <?php endif; ?>

                <?php if ($activeSession): ?>
                    <!-- SALLE JITSI ÉLÈVE -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 text-white">
                                    <i class="fas fa-video text-danger mr-2"></i>
                                    <?= h($activeSession['titre']) ?> — <em><?= h($activeSession['cours_nom'] ?? 'Cours') ?></em>
                                </h5>
                                <small class="text-light">
                                    Statut : <span class="badge badge-live">EN DIRECT</span>
                                </small>
                            </div>
                            <div>
                                <a href="live.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Retour à la liste
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
                                roomName: '<?= h($activeSession['room_name']) ?>',
                                width: '100%',
                                height: 700,
                                parentNode: document.querySelector('#jitsi-meet-container'),
                                userInfo: {
                                    displayName: '<?= addslashes($studentFullName) ?>'
                                },
                                configOverwrite: {
                                    prejoinPageEnabled: false,
                                    startWithAudioMuted: true,
                                    startWithVideoMuted: false
                                },
                                interfaceConfigOverwrite: {
                                    TOOLBAR_BUTTONS: [
                                        'microphone', 'camera', 'desktop', 'fullscreen',
                                        'fodeviceselection', 'hangup', 'chat', 'raisehand',
                                        'videoquality', 'tileview'
                                    ]
                                }
                            };
                            const api = new JitsiMeetExternalAPI(domain, options);
                        });
                    </script>
                <?php else: ?>
                    <!-- LISTE DES SESSIONS LIVE POUR L'ÉLÈVE -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 text-white"><i class="fas fa-video mr-2"></i> Sessions en direct de ma classe</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$classSessions): ?>
                                <p class="text-muted mb-0">Aucune session en direct prévue ou active pour le moment.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Cours & Titre</th>
                                                <th>Date & Heure</th>
                                                <th>Statut</th>
                                                <th>Accès</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classSessions as $s): ?>
                                                <?php $isLive = ($s['status'] === 'live'); ?>
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark"><?= h($s['cours_nom'] ?? 'Cours') ?></strong> : <?= h($s['titre']) ?>
                                                        <?php if (!empty($s['description'])): ?>
                                                            <div class="text-muted small"><?= h($s['description']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
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
                                                        <?php if ($isLive): ?>
                                                            <a href="live.php?session_id=<?= (int)$s['id'] ?>" class="btn btn-danger btn-sm font-weight-bold">
                                                                <i class="fas fa-video"></i> Rejoindre le cours en direct
                                                            </a>
                                                        <?php elseif ($s['status'] === 'scheduled'): ?>
                                                            <button class="btn btn-secondary btn-sm" disabled title="Le professeur n'a pas encore lancé la session">
                                                                <i class="fas fa-clock"></i> En attente du professeur
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Session terminée</span>
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
