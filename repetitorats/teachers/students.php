<?php
declare(strict_types=1);
require_once __DIR__ . '/services/students-services.php';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Mes Élèves Encadrés</title>
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
                    <h3 style="text-transform: uppercase;">MES ÉLÈVES ENCADRÉS</h3>
                    <p>Liste des élèves assignés à votre programme de répétitorat.</p>
                </div>

                <div class="card-custom">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nom & Prénom</th>
                                    <th>Classe</th>
                                    <th>Matières</th>
                                    <th>Mode</th>
                                    <th>Contact Parent</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($eleves)): foreach ($eleves as $index => $el): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($el['nom_eleve'] . ' ' . $el['prenom_eleve'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($el['classe'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span
                                            class="badge bg-light text-dark border"><?= htmlspecialchars($el['matieres'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst($el['mode']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($el['phone_parent'] ?? $el['whatsapp_parent'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?php if ($el['statut'] === 'confirmee'): ?>
                                        <span class="badge bg-success text-white">Confirmée</span>
                                        <?php elseif ($el['statut'] === 'annulee'): ?>
                                        <span class="badge bg-danger">Annulée</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Aucun élève encadré trouvé
                                        pour le
                                        moment.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>