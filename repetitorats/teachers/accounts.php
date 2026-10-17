<?php
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--body-bg); margin: 0; color: #333; }
        .navbar-top { position: fixed; top: 0; left: 0; right: 0; height: 70px; background: var(--topbar-bg); box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; padding: 0 20px; z-index: 1000; }
        .brand-box { width: var(--sidebar-width); background: var(--primary-orange); height: 70px; display: flex; align-items: center; justify-content: center; position: absolute; left: 0; top: 0; font-weight: 800; font-size: 1.4rem; color: #fff; text-decoration: none; gap: 8px; }
        .brand-box span { font-size: 0.65rem; display: block; font-weight: 400; text-transform: uppercase; }
        .topbar-center { margin-left: 260px; }
        .topbar-school-name { font-weight: 600; color: #333; font-size: 0.95rem; margin: 0; }
        .topbar-school-url { color: #0d6efd; font-size: 0.8rem; text-decoration: none; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .user-name-title { font-weight: 700; font-size: 0.9rem; margin: 0; text-align: right; color: #333; }
        .user-role-title { font-size: 0.75rem; color: #6c757d; margin: 0; text-align: right; }
        .user-avatar-circle { width: 38px; height: 38px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0d6efd; font-size: 1.1rem; }

        .sidebar { position: fixed; top: 70px; left: 0; width: var(--sidebar-width); height: calc(100vh - 70px); background: var(--sidebar-bg); padding-top: 15px; z-index: 999; overflow-y: auto; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li a { display: flex; align-items: center; padding: 13px 20px; color: #b0bec5; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s; border-left: 4px solid transparent; }
        .sidebar-menu li a i { margin-right: 12px; font-size: 1.1rem; width: 20px; text-align: center; }
        .sidebar-menu li a:hover, .sidebar-menu li.active a { background: rgba(255,255,255,0.06); color: #fff; border-left-color: var(--primary-orange); }
        .sidebar-menu li.logout-item a { color: #ff5252; }
        .sidebar-menu li.logout-item a:hover { background: rgba(255, 82, 82, 0.1); border-left-color: #ff5252; color: #fff; }

        .main-content { margin-left: var(--sidebar-width); margin-top: 70px; padding: 30px; }
        .page-title-box h2 { font-weight: 800; font-size: 1.5rem; color: #263238; margin-bottom: 5px; text-transform: uppercase; }
        .page-title-box p { color: #546e7a; font-size: 0.95rem; margin-bottom: 25px; }

        .card-custom { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
    </style>
</head>
<body>

    <header class="navbar-top">
        <a href="dashboard.php" class="brand-box"><i class="fa fa-graduation-cap"></i> Kelasi <span>Education</span></a>
        <div class="topbar-center">
            <h6 class="topbar-school-name"><?= htmlspecialchars($nomEcole, ENT_QUOTES, 'UTF-8') ?></h6>
            <a href="#" class="topbar-school-url"><?= htmlspecialchars($urlEcole, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="topbar-right">
            <div>
                <p class="user-name-title"><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="user-role-title">Répétiteur</p>
            </div>
            <div class="user-avatar-circle"><i class="fa fa-user"></i></div>
        </div>
    </header>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-title-box">
            <h2>MON PROFIL</h2>
            <p>Gérez vos informations personnelles et identifiants de connexion.</p>
        </div>

        <?php if ($successMsg !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i> <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card-custom">
                    <form action="profil.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Prénom</label>
                                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($userData['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nom</label>
                                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($userData['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nom d'utilisateur</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($userData['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled>
                                <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">E-mail</label>
                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($userData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nouveau mot de passe <span class="text-muted fw-normal">(laisser vide pour ne pas changer)</span></label>
                            <input type="password" name="new_password" class="form-control" placeholder="Entrez un nouveau mot de passe">
                        </div>

                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                            <i class="fa fa-save me-2"></i> Enregistrer les modifications
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>
</html>