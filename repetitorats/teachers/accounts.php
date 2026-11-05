<?php
// repetitorats/teachers/accounts.php
declare(strict_types=1);
require_once __DIR__ . '/services/accounts-services.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Mon Profil Répétiteur</title>
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
    </style>
</head>


<body>

    <div id="wrapper" class="wrapper bg-ash">
        <?php require_once(__DIR__ . '/includes/navbar.php'); ?>

        <div class="dashboard-page-one">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">MON PROFIL RÉPÉTITEUR</h3>
                    <p>Gérez vos informations personnelles et modifiez vos paramètres de sécurité.</p>
                </div>

                <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row gutters-20">
                    <!-- Formulaire Informations Personnelles -->
                    <div class="col-md-6">
                        <div class="card-custom">
                            <h4 class="text-primary fw-bold mb-4"><i class="bi bi-person-lines-fill me-2"></i>
                                Informations
                                Personnelles</h4>
                            <form action="profil.php" method="POST">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nom d'utilisateur (Identifiant)</label>
                                    <input type="text" class="form-control"
                                        value="<?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        disabled>
                                    <small class="text-muted">L'identifiant ne peut pas être modifié.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Prénom</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="<?= htmlspecialchars((string)($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nom</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="<?= htmlspecialchars((string)($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Téléphone</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="<?= htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>

                                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                                    <i class="fa fa-save me-2"></i> Mettre à jour le profil
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Formulaire Sécurité / Mot de passe -->
                    <div class="col-md-6">
                        <div class="card-custom">
                            <h4 class="text-dark fw-bold mb-4"><i class="bi bi-shield-lock-fill me-2"></i> Sécurité &
                                Mot de
                                passe</h4>
                            <form action="profil.php" method="POST">
                                <input type="hidden" name="action" value="update_password">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Mot de passe actuel</label>
                                    <input type="password" name="current_password" class="form-control"
                                        placeholder="••••••••" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nouveau mot de passe</label>
                                    <input type="password" name="new_password" class="form-control"
                                        placeholder="••••••••" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="confirm_password" class="form-control"
                                        placeholder="••••••••" required>
                                </div>

                                <button type="submit" class="btn btn-dark px-4 py-2 fw-bold">
                                    <i class="fa fa-key me-2"></i> Changer le mot de passe
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once(__DIR__ . '/includes/footer.php'); ?>
</body>

</html>