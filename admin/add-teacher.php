<?php
// admin/add-teacher.php — Création d’un enseignant + affectation à plusieurs classes
declare(strict_types=1);

 // DEBUG handled in db_connect.php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ---- Connexion DB ----
$pdo = null;
foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Erreur serveur (DB).'); }

// ---- Auth : admin + code_ecole ----
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = strtolower((string)($_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin','administrateur'], true);

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId && !$codeEcole) {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $codeEcole = (string)($st->fetchColumn() ?: '');
    if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
}
if (!$isAdmin || !$codeEcole) { http_response_code(403); exit('Accès refusé (admin requis + code école manquant).'); }

// ---- Classes de l’école ----
$classes = [];
try {
    $sql = "
      SELECT c.id, c.classe, c.description AS desc_classe,
             n.description AS niveau, s.description AS section, o.description AS opt
      FROM classes c
      LEFT JOIN niveau  n ON c.niveau  = n.id
      LEFT JOIN section s ON c.section = s.id
      LEFT JOIN options o ON c.options = o.id
      WHERE c.code_ecole = :code
      ORDER BY n.description, s.description, o.description, c.classe, c.description
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':code'=>$codeEcole]);
    $classes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (DEBUG) { echo $e->getMessage(); exit; }
}

// ---- Messages retour ----
$msg = '';
if (!empty($_GET['msg'])) {
    $map = [
        'created'   => '✅ Enseignant créé et affecté aux classes sélectionnées.',
        'exists'    => '⚠️ Identifiant ou email déjà utilisé.',
        'badclass'  => '⚠️ Certaines classes ne sont pas valides pour cette école.',
        'invalid'   => '⚠️ Données invalides (veuillez vérifier les champs requis).',
        'error'     => '❌ Erreur lors de la création de l’enseignant.',
    ];
    $msg = $map[$_GET['msg']] ?? '';
    if (!empty($_GET['skipped'])) {
        $msg .= '<br><small>Classes ignorées (déjà affectées à un autre enseignant) : '.htmlspecialchars($_GET['skipped']).'</small>';
    }
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kelasi | Ajouter un enseignant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
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
    .card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .05)
    }

    .muted {
        color: #6b7280
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <?php $nav=__DIR__.'/layout/navbar.php';  if(file_exists($nav))  require $nav; ?>
        <div class="dashboard-page-one">
            <?php $side=__DIR__.'/layout/sidebar.php'; if(file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">Création enseignant & affectations multiples</h3>
                    <p class="muted mb-0">Un même compte peut être lié à plusieurs classes.</p>
                </div>

                <div class="card height-auto">
                    <div class="card-body">
                        <?php if ($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

                        <form class="new-added-form" method="POST" action="service/add-teacher.php" autocomplete="off">
                            <?php
                        require_once __DIR__ . '/../service/security_helpers.php';
                        csrf_input();
                        ?>
                            <input type="hidden" name="add_teacher" value="1">
                            <div class="row">
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Prénom *</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Nom *</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Genre *</label>
                                    <select class="select2 form-control" name="gender" required>
                                        <option value="">— Sélectionnez —</option>
                                        <option value="Homme">Homme</option>
                                        <option value="Femme">Femme</option>
                                    </select>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Date de naissance</label>
                                    <input type="text" name="date_of_birth" placeholder="yyyy-mm-dd"
                                        class="form-control">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Téléphone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Adresse</label>
                                    <input type="text" name="address" class="form-control">
                                </div>
                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Qualification *</label>
                                    <input type="text" name="qualification" class="form-control" required>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Spécialisation</label>
                                    <input type="text" name="specialization" class="form-control">
                                </div>

                                <!-- Multi-classes -->
                                <div class="col-xl-6 col-lg-12 col-12 form-group">
                                    <label>Classes (plusieurs) *</label>
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" id="btnSelectAll"
                                            class="btn btn-sm btn-outline-secondary">Tout sélectionner</button>
                                        <button type="button" id="btnUnselectAll"
                                            class="btn btn-sm btn-outline-secondary">Tout désélectionner</button>
                                    </div>
                                    <select class="select2 form-control" name="classes[]" id="classes" required
                                        multiple>
                                        <?php foreach ($classes as $r):
                                        $lbl = trim(($r['classe'] ?? '').' '.($r['desc_classe'] ?? '').' '.($r['niveau'] ?? '').' '.($r['section'] ?? '').' '.($r['opt'] ?? ''));
                                        if ($lbl==='') $lbl = 'Classe #'.(int)$r['id'];
                                    ?>
                                        <option value="<?= (int)$r['id'] ?>"><?= e($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="muted">Vous avez la possibilité de choisir la classe maintenant ou
                                        après. Il est possible pour une classe d'avoir un ou plusieurs
                                        professeurs.</small>
                                </div>

                                <div class="col-xl-3 col-lg-6 form-group">
                                    <label>Nom d’utilisateur *</label>
                                    <input type="text" name="username" class="form-control" required
                                        autocomplete="username">
                                </div>
                                <div class="col-xl-3 col-lg-6 form-group">
                                    <label>Mot de passe *</label>
                                    <input type="password" id="pwd" name="password" class="form-control" required
                                        autocomplete="new-password">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="showPwd">
                                        <label class="form-check-label" for="showPwd">Afficher le mot de passe</label>
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-12 form-group">
                                    <label>Date d’entrée</label>
                                    <input type="text" name="date_of_joining" placeholder="yyyy-mm-dd"
                                        class="form-control">
                                </div>

                                <div class="col-12 form-group mg-t-8">
                                    <button type="submit"
                                        class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">Créer
                                        l'enseignant</button>
                                    <button type="reset"
                                        class="btn-fill-lg bg-blue-dark btn-hover-yellow">Annuler</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php $foot=__DIR__.'/layout/footer.php'; if(file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    $(function() {
        if ($.fn.select2) {
            $('#classes').select2({
                width: '100%',
                placeholder: 'Sélectionnez une ou plusieurs classes'
            });
            $('select.select2').select2({
                width: '100%'
            });
        }
        $('#btnSelectAll').on('click', function() {
            const $s = $('#classes');
            $s.find('option').prop('selected', true);
            $s.trigger('change');
        });
        $('#btnUnselectAll').on('click', function() {
            const $s = $('#classes');
            $s.val(null).trigger('change');
        });
        $('#showPwd').on('change', function() {
            const i = document.getElementById('pwd');
            if (!i) return;
            i.type = this.checked ? 'text' : 'password';
        });
    });
    </script>
</body>

</html>