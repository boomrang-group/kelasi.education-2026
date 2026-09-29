<?php
// mykelasi/admin/all-students.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin','administrateur'], true)) {
    header('Location: ../login/index.php?msg=forbidden'); exit;
}

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
    // Optionnel : fallback via users
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$uid]);
        $codeEcole = (string)($st->fetchColumn() ?: '');
        if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
    }
}

// --- Charger étudiants (scopé par école) ---
$students = [];
try {
    $sql = "
        SELECT
            s.id              AS students_id,
            s.first_name,
            s.last_name,
            s.gender,
            s.phone,
            s.email,
            s.date_of_birth,
            s.username,
            c.classe,
            c.description     AS classe_desc,
            n.description     AS niveau,
            sec.description   AS section,
            opt.description   AS option_label
        FROM students s
        LEFT JOIN classes  c   ON s.class_id = c.id
        LEFT JOIN niveau   n   ON c.niveau  = n.id
        LEFT JOIN section  sec ON c.section = sec.id
        LEFT JOIN options  opt ON c.options = opt.id
    ";

    $params = [];
    if ($codeEcole !== '') {
        $sql .= " WHERE s.code_ecole = :ce ";
        $params[':ce'] = $codeEcole;
    }

    $sql .= " ORDER BY s.id DESC ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $students = [];
}
?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | All Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- CSS existants -->
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>
    <style>
    .search-wrap {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        align-items: center
    }

    .search-wrap .form-control {
        max-width: 360px
    }

    .btn-sm {
        padding: .25rem .5rem
    }

    .table td,
    .table th {
        vertical-align: middle
    }

    .chip {
        display: inline-block;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: .1rem .55rem;
        font-size: .8rem;
        background: #fafafa
    }

    .muted {
        color: #6b7280
    }

    .img-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">
        <!-- Header -->
        <?php require_once('layout/navbar.php'); ?>

        <div class="dashboard-page-one">
            <!-- Sidebar -->
            <?php require_once('layout/sidebar.php'); ?>

            <div class="dashboard-content-one">
                <!-- Header + Bouton -->
                <div class="breadcrumbs-area d-flex align-items-center justify-content-between">
                    <div>
                        <h3>Liste des élèves</h3>
                        <p class="muted mb-0">École : <span class="chip"><?= $codeEcole ? h($codeEcole) : '—' ?></span>
                        </p>
                    </div>
                    <a href="admit-form.php" class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">+ Ajouter un
                        élève</a>
                </div>

                <!-- Zone de recherche -->
                <div class="card">
                    <div class="card-body">
                        <div class="search-wrap">
                            <input id="tableSearch" type="text" class="form-control"
                                placeholder="Rechercher (Nom, Classe, Téléphone, E-mail, ID)…">
                            <!-- <small class="muted">Filtrage instantané au clavier.</small> -->
                        </div>
                    </div>
                </div>

                <!-- Table étudiants -->
                <div class="card height-auto">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="studentsTable" class="table display data-table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Photo</th>
                                        <th>Nom</th>
                                        <th>Sexe</th>
                                        <th>Classe</th>
                                        <th>Date naissance</th>
                                        <th>Téléphone</th>
                                        <th>E-mail</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($students): ?>
                                    <?php foreach ($students as $st):
                                    // Nom complet
                                    $fullName = trim(($st['first_name'] ?? '').' '.($st['last_name'] ?? ''));

                                    // Libellé classe
                                    $classeLbl = trim(
                                        ($st['classe'] ?? '').
                                        (($st['classe_desc'] ?? '') ? ' '.$st['classe_desc'] : '').
                                        (($st['niveau'] ?? '') ? ' '.$st['niveau'] : '').
                                        (($st['section'] ?? '') ? ' '.$st['section'] : '').
                                        (($st['option_label'] ?? '') ? ' '.$st['option_label'] : '')
                                    );
                                    if ($classeLbl === '') $classeLbl = '—';

                                    // Date naissance
                                    $dob = $st['date_of_birth'] ? date('d/m/Y', strtotime((string)$st['date_of_birth'])) : '—';

                                    // Avatar selon genre
                                    $graw = trim((string)($st['gender'] ?? ''));
                                    $g = mb_strtolower($graw);
                                    if (in_array($g, ['homme','m','male','masculin'], true)) {
                                        $sex = 'm';
                                    } elseif (in_array($g, ['femme','f','female','féminin','feminin'], true)) {
                                        $sex = 'f';
                                    } else {
                                        $sex = 'x';
                                    }
                                    // M => student1.png, F => student.png, sinon fallback sur student1.png
                                    $avatar = ($sex === 'f') ? '../img/figure/student.png' : '../img/figure/student1.png';
                                ?>
                                    <tr>
                                        <td><?= (int)$st['students_id'] ?></td>
                                        <td class="text-center">
                                            <img class="img-avatar" src="<?= h($avatar) ?>" alt="student">
                                        </td>
                                        <td><?= h($fullName ?: ($st['username'] ?? '')) ?></td>
                                        <td><?= h($st['gender'] ?: '—') ?></td>
                                        <td><?= h($classeLbl) ?></td>
                                        <td><?= h($dob) ?></td>
                                        <td><?= h($st['phone'] ?: '—') ?></td>
                                        <td><?= h($st['email'] ?: '—') ?></td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-md btn-dark"
                                                href="students-show.php?eleve=<?= (int)$st['students_id'] ?>">
                                                Voir
                                            </a>
                                            <a class="btn btn-md btn-primary"
                                                href="admit-form.php?edit_id=<?= (int)$st['students_id'] ?>">
                                                Modifier
                                            </a>
                                            <button class="btn btn-md btn-danger"
                                                onclick="deleteStudent(<?= (int)$st['students_id'] ?>)">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center muted">Aucun élève trouvé.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    // Filtrage instantané (au clavier) sur toutes les colonnes visibles
    $(function() {
        const $rows = $('#studentsTable tbody tr');
        $('#tableSearch').on('input', function() {
            const q = $(this).val().toString().trim().toLowerCase();
            if (!q) {
                $rows.show();
                return;
            }
            $rows.each(function() {
                const txt = $(this).text().toLowerCase();
                $(this).toggle(txt.indexOf(q) !== -1);
            });
        });
    });

    // Suppression (redirige vers delete_student.php)
    function deleteStudent(studentId) {
        if (!studentId) return;
        if (confirm('Supprimer l’élève ID ' + studentId + ' ? Cette action est irréversible.')) {
            window.location.href = 'delete_student.php?id=' + encodeURIComponent(studentId);
        }
    }
    </script>
</body>

</html>