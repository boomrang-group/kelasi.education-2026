<?php 
// customs/teacher/layout/navbar.php

// Connexion utilisateur (ton existant)
require_once '../service/user_connecter.php';

// ⚠️ Assure la connexion DB (si pas déjà faite dans user_connecter.php)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }
}

// Helper "classe active" (centralisé)
require_once __DIR__ . '/../_teacher_active_class.php';
$ctx            = teacher_active_context($pdo);
$activeClassId  = $ctx['active_class_id'];
$classRow       = $ctx['class'];      // infos classes.* avec jointures
$assigned       = $ctx['assigned'];   // liste des classes affectées

// Étiquette classe active
function _fmt_class_label(?array $r): string {
    if (!$r) return 'Aucune classe';
    $parts = [
        $r['classe'] ?? '',
        $r['description'] ?? '',
        $r['niveau'] ?? '',
        $r['section'] ?? '',
        $r['options'] ?? '',
    ];
    return trim(preg_replace('~\s+~', ' ', implode(' ', array_filter($parts, fn($v)=>$v!==null))));
}

// Variables existantes pour l’école (déjà présentes dans ton code)
$nom        = $nom        ?? ($_SESSION['nom_ecole'] ?? '');
$url_ecole  = $url_ecole  ?? ($_SESSION['url_ecole'] ?? '');

// Si aucune classe active mais des affectations existent, on forcera l’ouverture du modal
$shouldAutoOpenModal = (!$activeClassId && !empty($assigned));
?>
<div class="navbar navbar-expand-md header-menu-one bg-light">
    <div class="nav-bar-header-one">
        <div class="header-logo">
            <a href="https://kelasi.education" target="black">
                <img src="../../../img/logo.png" alt="logo">
            </a>
        </div>
        <div class="toggle-button sidebar-toggle">
            <button type="button" class="item-link">
                <span class="btn-icon-wrap">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>
    </div>

    <div class="d-md-none mobile-nav-bar">
        <button class="navbar-toggler pulse-animation" type="button" data-toggle="collapse" data-target="#mobile-navbar"
            aria-expanded="false">
            <i class="far fa-arrow-alt-circle-down"></i>
        </button>
        <button type="button" class="navbar-toggler sidebar-toggle-mobile">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="header-main-menu collapse navbar-collapse" id="mobile-navbar">
        <ul class="navbar-nav">
            <li class="navbar-item header-search-bar">
                <div class="input-group stylish-input-group">
                    <span class="mt-3">
                        <?php echo htmlspecialchars($nom ?: 'Mon école'); ?>
                        <br>
                        <a href="../../mykelasi/@<?php echo htmlspecialchars($url_ecole); ?>" target="_blank">
                            www.kelasi.education/@<?php echo htmlspecialchars($url_ecole); ?>
                        </a>
                        <?php if ($classRow): ?>
                        <br>
                        <small class="text-muted">Classe active :</small>
                        <span class="badge badge-primary ml-1">
                            <?php echo htmlspecialchars(_fmt_class_label($classRow)); ?>
                        </span>
                        <?php else: ?>
                        <br>
                        <small class="text-danger">Aucune classe active</small>
                        <?php endif; ?>
                    </span>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto align-items-center">

            <!-- Bouton BASCULER toujours visible -->
            <li class="d-none navbar-item">
                <button type="button" class="btn btn-sm btn-outline-primary mr-3" data-toggle="modal"
                    data-target="#switchClassModal">
                    Basculer
                </button>
            </li>

            <!-- Profil -->
            <li class="navbar-item dropdown header-admin">
                <a class="navbar-nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown"
                    aria-expanded="false">
                    <div class="admin-title">
                        <h5 class="item-title">
                            <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : "teacher"; ?>
                            !
                        </h5>
                        <span>Professeur</span>
                    </div>
                    <div class="admin-img">
                        <img src="../../../img/figure/admin.jpg" alt="Admin">
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <div class="item-header">
                        <h6 class="item-title">
                            <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : "teacher"; ?>
                        </h6>
                    </div>
                    <div class="item-content">
                        <ul class="settings-list">
                            <li><a href="mon_profil.php"><i class="flaticon-user"></i>Mon compte</a></li>
                            <!--<li><a href="#"><i class="flaticon-list"></i>Task</a></li>-->
                            <!-- <li><a href="../view/chat.php"><i
                                        class="flaticon-chat-comment-oval-speech-bubble-with-text-lines"></i>Message</a>
                            </li> -->
                            <!--<li><a href="#"><i class="flaticon-gear-loading"></i>Account Settings</a></li>-->
                            <li><a href="../../../login/logout.php"><i class="flaticon-turn-off"></i>Déconnexion</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</div>

<!-- Modal BASCULE CLASSE -->
<div class="modal fade" id="switchClassModal" tabindex="-1" role="dialog" aria-labelledby="switchClassLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="post" action="../switch_class.php" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="switchClassLabel">Changer de classe</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if (!empty($assigned)): ?>
                <div class="form-group">
                    <label for="class_id">Sélectionner une classe</label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        <?php foreach ($assigned as $c): ?>
                        <?php
                            $lbl = trim(($c['classe'] ?? '').' '.($c['description'] ?? '').' '.($c['niveau'] ?? '').' '.($c['section'] ?? '').' '.($c['options'] ?? ''));
                            if ($lbl==='') $lbl = 'Classe #'.(int)$c['id'];
                        ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= ($activeClassId && (int)$c['id']===(int)$activeClassId)?'selected':''; ?>>
                            <?= htmlspecialchars($lbl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <small class="text-muted d-block">
                    La classe sélectionnée sera définie comme <strong>classe active</strong> et sera utilisée dans tout
                    l’espace enseignant.
                </small>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    Aucune classe ne vous est encore affectée.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if (!empty($assigned)): ?>
                <button type="submit" class="btn btn-primary">Basculer</button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </form>
    </div>
</div>

<!-- Auto-open du modal si prof a des affectations mais aucune classe active -->
<?php if ($shouldAutoOpenModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $('#switchClassModal').modal) {
        $('#switchClassModal').modal('show');
    } else {
        // Fallback Bootstrap: tente d'ouvrir via DOM (si lib chargée plus tard, l’utilisateur a le bouton "Basculer")
    }
});
</script>
<?php endif; ?>