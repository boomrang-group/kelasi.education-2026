<?php
// mykelasi/admin/admit-form.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Connexion DB robuste ---
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Contexte école ---
$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$uid]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
    }
}

// --- Mode édition ? ---
$editId  = (isset($_GET['edit_id']) && ctype_digit((string)$_GET['edit_id'])) ? (int)$_GET['edit_id'] : 0;
$isEdit  = $editId > 0;
$student = null;

if ($isEdit) {
    // Charger l'élève ciblé (scopé par code_ecole si dispo)
    $sql = "SELECT * FROM students WHERE id = :id";
    $params = [':id'=>$editId];
    if ($codeEcole !== '') { $sql .= " AND code_ecole = :ce"; $params[':ce'] = $codeEcole; }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$student) {
        header('Location: all-students.php?msg=not_found'); exit;
    }
}

// --- Charger la liste des classes (scopée école) ---
$classes = [];
try {
    $sqlC = "
        SELECT 
            c.id AS identity, 
            c.description AS description,
            c.classe AS classe, 
            n.description AS niveau,
            s.description AS section, 
            o.description AS options
        FROM classes c
        LEFT JOIN section s ON c.section = s.id
        LEFT JOIN niveau  n ON c.niveau  = n.id
        LEFT JOIN options o ON c.options = o.id
    ";
    $paramsC = [];
    if ($codeEcole !== '') { $sqlC .= " WHERE c.code_ecole = :ce "; $paramsC[':ce'] = $codeEcole; }
    $sqlC .= " ORDER BY n.description, s.description, o.description, c.classe, c.description";

    $st = $pdo->prepare($sqlC);
    $st->execute($paramsC);
    $classes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $classes = [];
}

// --- Helpers de valeurs (édition vs création) ---
$val = function(string $key, string $fallback='') use ($student) {
    return h($student[$key] ?? $fallback);
};
$selectedClassId = $isEdit ? (int)($student['class_id'] ?? 0) : 0;
$genderVal       = $isEdit ? (string)($student['gender'] ?? '') : '';
$dobVal          = ($isEdit && !empty($student['date_of_birth'])) ? date('d/m/Y', strtotime((string)$student['date_of_birth'])) : '';
$photoHelp       = $isEdit ? '(laisser vide pour ne pas changer)' : '(150px x 150px)';

// --- Action du formulaire ---
// On garde ton endpoint existant "service/add-student.php".
// Convention: s'il y a hidden "edit_id", le script mettra à jour au lieu de créer.
$formAction = 'service/add-student.php';
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | <?= $isEdit ? 'Modifier élève' : 'Admission Form' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
    <!-- CSS existants -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    .muted {
        color: #6b7280
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once('layout/navbar.php'); ?>
        <div class="dashboard-page-one">
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <h3><?= $isEdit ? 'Modifier un élève' : 'Création des élèves' ?></h3>
                    <div>
                        <a class="btn btn-secondary btn-md" href="all-students.php">← Retour à la liste</a>
                        <?php if ($isEdit): ?>
                        <a class="btn btn-outline-danger btn-sm" href="delete_student.php?id=<?= (int)$editId ?>"
                            onclick="return confirm('Supprimer cet élève (ID <?= (int)$editId ?>) ? Action irréversible.');">
                            Supprimer
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card height-auto">
                    <div class="card-body">
                        <form class="new-added-form" action="<?= h($formAction) ?>" method="POST"
                            enctype="multipart/form-data" autocomplete="off">
                            <?php
                        require_once __DIR__ . '/../service/security_helpers.php';
                        csrf_input();
                        ?>
                            <?php if ($isEdit): ?>
                            <input type="hidden" name="edit_id" value="<?= (int)$editId ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Prenom *</label>
                                    <input type="text" name="first_name" class="form-control" required
                                        value="<?= $val('first_name') ?>">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Nom de famille *</label>
                                    <input type="text" name="last_name" class="form-control" required
                                        value="<?= $val('last_name') ?>">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Genre *</label>
                                    <select class="select2 form-control" name="gender" required>
                                        <option value="" disabled <?= $genderVal===''?'selected':''; ?>>Sélectionner
                                            votre genre *</option>
                                        <option value="Homme" <?= $genderVal==='Homme'?'selected':''; ?>>Homme</option>
                                        <option value="Femme" <?= $genderVal==='Femme'?'selected':''; ?>>Femme</option>
                                    </select>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group"></div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Date de naissance *</label>
                                    <input type="text" placeholder="dd/mm/yyyy" class="form-control air-datepicker"
                                        name="date_of_birth" data-position='bottom right' value="<?= h($dobVal) ?>"
                                        required>
                                    <!-- <i class="far fa-calendar-alt"></i> -->
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Adresse e-mail (élève)</label>
                                    <input type="email" name="email" class="form-control" value="<?= $val('email') ?>">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Téléphone</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= $val('phone') ?>">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group"></div>

                                <!-- <div class="col-lg-6 col-12 form-group mg-t-30">
                                    <label class="text-dark-medium">Téléverser photo élève <?= h($photoHelp) ?></label>
                                    <input type="file" name="photo" class="form-control-file">
                                </div>
                                <div class="col-lg-6 col-12 form-group mg-t-30">
                                    <label class="text-dark-medium">Téléverser les documents de l'élève</label>
                                    <input type="file" name="document" class="form-control-file">
                                </div> -->

                                <div class="col-xl-3 col-lg-6 col-12 form-group mt-3">
                                    <label>Ecole provenance *</label>
                                    <input type="text" name="ecole_provenance" class="form-control"
                                        value="<?= $val('ecole_provenance','Kelasi school') ?>" required>
                                </div>

                                <div class="col-lg-12 col-12 form-group mg-t-30">
                                    <h3 class="text-dark-medium" style="text-transform: uppercase; font-size: 16px;">
                                        info du responsable
                                    </h3>
                                    <hr>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Nom du père *</label>
                                    <input type="text" name="father" class="form-control" value="<?= $val('father') ?>"
                                        required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Nom de la mère *</label>
                                    <input type="text" name="mother" class="form-control" value="<?= $val('mother') ?>"
                                        required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Téléphone *</label>
                                    <input type="tel" name="phone_responsable" class="form-control"
                                        value="<?= $val('phone_responsable') ?>" required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Adresse e-mail *</label>
                                    <input type="email" name="email_responsable" class="form-control"
                                        value="<?= $val('email_responsable') ?>" required>
                                </div>

                                <div class="col-lg-12 col-12 form-group mg-t-30">
                                    <h3 class="text-dark-medium" style="text-transform: uppercase; font-size: 16px;">
                                        Affectation & CONNEXION DE L'ELEVE</h3>
                                    <hr>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Classe *</label>
                                    <select class="select2 form-control" name="classe" required>
                                        <option value="">Veuillez sélectionner la classe *</option>
                                        <?php foreach ($classes as $row):
                                        $cid = (int)$row['identity'];
                                        $opt = trim(($row['classe'] ?? '').($row['description'] ? ' '.$row['description'] : '').' '.($row['niveau'] ?? '').' '.($row['section'] ?? '').' '.($row['options'] ?? ''));
                                        if ($opt==='') $opt = 'Classe #'.$cid;
                                        $sel = ($cid === $selectedClassId) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $cid ?>" <?= $sel ?>><?= h($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if (!$isEdit): ?>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Nom d'utilisateur *</label>
                                    <input type="text" name="username" class="form-control" required
                                        value="<?= $val('username') ?>">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Mot de passe *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Confirmer le mot de passe *</label>
                                    <input type="password" name="password_confirm" class="form-control" required>
                                </div>

                                <?php endif; ?>

                                <div class="col-12 form-group mg-t-8">
                                    <button type="submit" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark"
                                        name="submit">
                                        <?= $isEdit ? 'Mettre à jour' : 'Créer élève' ?>
                                    </button>
                                    <a href="all-students.php"
                                        class="btn-fill-lg bg-blue-dark btn-hover-yellow">Annuler</a>
                                </div>
                            </div>
                        </form>
                        <!-- <p class="muted mb-0">
                            <small>
                                Astuce : ce formulaire envoie toujours vers <code>service/add-student.php</code>.
                                Si <code>edit_id</code> est présent dans le POST, votre script doit faire un
                                <strong>UPDATE</strong> au lieu d’un INSERT.
                            </small>
                        </p> -->
                    </div>
                </div>

                <?php require_once('layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    $(function() {
        if ($.fn.select2) $('.select2').select2({
            width: '100%'
        });
    });
    </script>
</body>

</html>