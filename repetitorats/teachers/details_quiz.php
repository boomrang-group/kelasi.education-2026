<?php
// repetitorats/teachers/details_quiz.php
declare(strict_types=1);
require_once __DIR__ . '/services/quiz-details-services.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelasi | Configuration du Quiz</title>
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

    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <!-- Inclusion de la Navbar -->
        <?php require_once(__DIR__ . '/includes/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Inclusion de la Sidebar -->
            <?php require_once(__DIR__ . '/includes/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <div class="breadcrumbs-area">
                    <h3 style="text-transform: uppercase;">ÉVALUATION :
                        <?= htmlspecialchars($quiz['titre'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mb-0">Cours :
                            <strong><?= htmlspecialchars($quiz['cours_nom'] ?? 'Général', ENT_QUOTES, 'UTF-8') ?></strong>
                            |
                            Format : <span
                                class="badge bg-primary text-uppercase"><?= htmlspecialchars($quiz['type_quiz'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </p>
                        <a href="mes_quiz.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>
                            Retour aux
                            quiz</a>
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

                <!-- Formulaire d'ajout de question -->
                <div class="card-custom">
                    <h3 class="text-primary fw-bold mb-4"><i class="bi bi-plus-circle me-2"></i> Ajouter une question
                    </h3>
                    <form action="details_quiz.php?id=<?= (int)$quizId ?>" method="POST">
                        <input type="hidden" name="action" value="add_question">

                        <div class="row mb-3">
                            <div class="col-md-9">
                                <label class="form-label fw-bold">Libellé de la question</label>
                                <input type="text" name="question_texte" class="form-control"
                                    placeholder="Ex: Quelle est la formule de..." required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Points</label>
                                <input type="number" name="points" class="form-control" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Type de question</label>
                            <select name="type_question" id="typeQuestionSelect" class="form-select" required>
                                <option value="qcm">QCM (Choix multiples avec assertions)</option>
                                <option value="qr">QR (Réponse ouverte / Rédactionnelle)</option>
                            </select>
                        </div>

                        <!-- Section des assertions (visible uniquement pour QCM) -->
                        <div id="qcmOptionsContainer" class="mb-4 border p-3 rounded bg-light">
                            <label class="form-label fw-bold text-dark mb-2"><i class="bi bi-list-check"></i> Assertions
                                /
                                Options de réponse (Cochez la bonne réponse)</label>

                            <div id="optionsList">
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0" type="radio" name="bonne_option" value="0"
                                            checked title="Bonne réponse">
                                    </div>
                                    <input type="text" name="options[]" class="form-control" placeholder="Option A"
                                        required>
                                </div>
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0" type="radio" name="bonne_option" value="1"
                                            title="Bonne réponse">
                                    </div>
                                    <input type="text" name="options[]" class="form-control" placeholder="Option B"
                                        required>
                                </div>
                            </div>
                            <button type="button" id="addOptionBtn" class="btn btn-sm btn-outline-secondary mt-2"><i
                                    class="bi bi-plus"></i> Ajouter une option</button>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                            <i class="fa fa-save me-2"></i> Enregistrer la question
                        </button>
                    </form>
                </div>

                <!-- Liste des questions déjà enregistrées -->
                <div class="card-custom">
                    <h4 class="text-dark fw-bold mb-4"><i class="bi bi-question-circle me-2"></i> Questions enregistrées
                        pour ce
                        quiz (<?= count($questionsList) ?>)</h4>

                    <?php if (!empty($questionsList)): ?>
                    <div class="accordion" id="questionsAccordion">
                        <?php foreach ($questionsList as $idx =>$q): ?>
                        <div class="accordion-item mb-3 border rounded shadow-sm">
                            <h2 class="accordion-header" id="heading<?= (int)$q['id'] ?>">
                                <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapse<?= (int)$q['id'] ?>"
                                    aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>"
                                    aria-controls="collapse<?= (int)$q['id'] ?>">
                                    <span class="badge bg-secondary me-2">#<?= $idx + 1 ?></span>
                                    <strong><?= htmlspecialchars($q['question_texte'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span
                                        class="ms-auto me-3 badge bg-info text-dark"><?= strtoupper($q['type_question']) ?>
                                        (<?= (int)$q['points'] ?> pts)</span>
                                </button>
                            </h2>
                            <div id="collapse<?= (int)$q['id'] ?>"
                                class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>"
                                aria-labelledby="heading<?= (int)$q['id'] ?>">
                                <div class="accordion-body">
                                    <?php if ($q['type_question'] === 'qcm' && !empty($q['options'])): ?>
                                    <ul class="list-group mb-3">
                                        <?php foreach ($q['options'] as$opt): ?>
                                        <li
                                            class="list-group-item d-flex justify-content-between align-items-center <?= (int)$opt['est_correcte'] === 1 ? 'list-group-item-success fw-bold' : '' ?>">
                                            <span><i
                                                    class="bi <?= (int)$opt['est_correcte'] === 1 ? 'bi-check-circle-fill text-success' : 'bi-circle' ?> me-2"></i>
                                                <?= htmlspecialchars($opt['option_texte'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ((int)$opt['est_correcte'] === 1): ?>
                                            <span class="badge bg-success">Bonne réponse</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php else: ?>
                                    <p class="text-muted fst-italic">Question à réponse ouverte (l'élève rédigera sa
                                        réponse).
                                    </p>
                                    <?php endif; ?>

                                    <form action="details_quiz.php?id=<?= (int)$quizId ?>" method="POST"
                                        onsubmit="return confirm('Supprimer cette question ?');" class="text-end">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i
                                                class="bi bi-trash"></i>
                                            Supprimer la question</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">Aucune question n'a encore été ajoutée à ce quiz.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="../../js/bootstrap.bundle.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('typeQuestionSelect');
            const qcmContainer = document.getElementById('qcmOptionsContainer');
            const optionsInputs = qcmContainer.querySelectorAll('input[type="text"]');

            function toggleQcmOptions() {
                if (typeSelect.value === 'qcm') {
                    qcmContainer.style.display = 'block';
                    optionsInputs.forEach(input => input.setAttribute('required', 'required'));
                } else {
                    qcmContainer.style.display = 'none';
                    optionsInputs.forEach(input => input.removeAttribute('required'));
                }
            }

            typeSelect.addEventListener('change', toggleQcmOptions);
            toggleQcmOptions();

            const addOptionBtn = document.getElementById('addOptionBtn');
            const optionsList = document.getElementById('optionsList');

            addOptionBtn.addEventListener('click', function() {
                const count = optionsList.children.length;
                const div = document.createElement('div');
                div.className = 'input-group mb-2';
                div.innerHTML = `
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="radio" name="bonne_option" value="${count}" title="Bonne réponse">
                </div>
                <input type="text" name="options[]" class="form-control" placeholder="Option ${String.fromCharCode(65 + count)}" required>
                <button type="button" class="btn btn-outline-danger remove-opt-btn"><i class="bi bi-x-lg"></i></button>
            `;
                optionsList.appendChild(div);

                div.querySelector('.remove-opt-btn').addEventListener('click', function() {
                    div.remove();
                });
            });
        });
        </script>
</body>

</html>