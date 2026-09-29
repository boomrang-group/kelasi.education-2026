<?php
// admin/paiement_cash.php
// Liste des paiements Cash avec approbation/rejet par l’admin (AJAX)

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../database/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('DB indisponible'); }

// --- Auth admin ---
$userId     = (int)($_SESSION['user_id'] ?? 0);
$role       = strtolower((string)($_SESSION['role'] ?? ''));
$code_ecole = (string)($_SESSION['code_ecole'] ?? '');
$isAdmin    = in_array($role, ['admin','administrateur'], true);
if (!$isAdmin) { http_response_code(403); exit('Accès refusé.'); }

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', ' '); }
function hasCol(PDO $pdo, string $t, string $c): bool {
    try {
        $q="SELECT 1 FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
              AND table_name=:t 
              AND column_name=:c 
            LIMIT 1";
        $st=$pdo->prepare($q);
        $st->execute([':t'=>$t, ':c'=>$c]);
        return (bool)$st->fetchColumn();
    } catch(Throwable $e){
        return false;
    }
}

// Vérifier colonnes nécessaires
$hasMode         = hasCol($pdo, 'paiement', 'mode');
$hasIsValidated  = hasCol($pdo, 'paiement', 'is_validated');
$hasValidatedAt  = hasCol($pdo, 'paiement', 'validated_at');
$hasValidatedBy  = hasCol($pdo, 'paiement', 'validated_by');

if (!$hasMode) {
    echo "<div style='padding:20px;font-family:sans-serif'>
            La colonne <code>mode</code> n'existe pas dans <code>paiement</code>. Ajoutez-la d’abord.
          </div>";
    exit;
}
if (!$hasIsValidated || !$hasValidatedAt || !$hasValidatedBy) {
    echo "<div style='padding:20px;font-family:sans-serif'>
            La table <code>paiement</code> doit contenir les colonnes 
            <code>is_validated</code>, <code>validated_at</code> et <code>validated_by</code> 
            pour gérer l’approbation des paiements.
          </div>";
    exit;
}

// Filtres
$status  = $_GET['status'] ?? 'pending'; // pending|approved|rejected|all
$allowed = ['pending','approved','rejected','all'];
if (!in_array($status, $allowed, true)) $status='pending';

// Recherche par mot-clé (Nom, Prénom, Réf)
$search = trim($_GET['q'] ?? '');

$whereConditions = [];
$params = [];

// --- REGLE CRITIQUE : Toujours filtrer exclusivement sur le mode Cash ---
$whereConditions[] = "p.mode = 'Cash'";

// 1. Filtrage selon le statut de validation
if ($status === 'pending') {
    $whereConditions[] = "p.is_validated = 0 AND p.validated_at IS NULL";
} elseif ($status === 'approved') {
    $whereConditions[] = "p.is_validated = 1";
} elseif ($status === 'rejected') {
    $whereConditions[] = "p.is_validated = 0 AND p.validated_at IS NOT NULL";
}
// Si status === 'all' : Affiche TOUS les paiements Cash (quel que soit le statut de validation)

// 2. Filtrage par École
if ($code_ecole !== '') {
    $whereConditions[] = "p.code_ecole = :ce";
    $params[':ce'] = $code_ecole;
}

// 3. Recherche par Élève / Référence
if ($search !== '') {
    $whereConditions[] = "(s.first_name LIKE :q OR s.last_name LIKE :q OR CONCAT(s.first_name, ' ', s.last_name) LIKE :q OR p.reference LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$where = implode(' AND ', $whereConditions);

// Récupérer la liste des paiements Cash
$sql = "
SELECT
  p.id, p.reference, p.statut, p.eleve, p.montant_paye, p.solde, p.date_paiement,
  p.mode, p.is_validated, p.validated_at, p.validated_by,
  s.first_name, s.last_name, s.class_id,
  c.classe, c.description AS classe_desc
FROM paiement p
LEFT JOIN students s ON s.id = p.eleve
LEFT JOIN classes  c ON c.id = s.class_id
WHERE $where
ORDER BY 
  (p.is_validated = 0 AND p.validated_at IS NULL) DESC, 
  p.date_paiement DESC, 
  p.id DESC
LIMIT 1000
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<!doctype html>
<html class="no-js" lang="fr">

<head>
    <meta charset="utf-8">
    <title>Gestion des Paiements Cash | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">
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
    .badge-soft {
        background: #f1f3f5;
        color: #495057;
        border-radius: .5rem;
        padding: .15rem .5rem;
        font-size: .75rem
    }

    .table td,
    .table th {
        vertical-align: middle
    }

    .filter-wrap {
        display: flex;
        gap: .5rem;
        align-items: center;
        flex-wrap: wrap
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
                <div class="breadcrumbs-area">
                    <h3>Gestion des Paiements Cash</h3>
                    <p class="mt-1">Validation et suivi exclusif des encaissements en espèces (Cash).</p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

                            <!-- Boutons de filtre par statut (TOUS RESTREINTS AU CASH) -->
                            <div class="filter-wrap">
                                <span class="mr-2 font-weight-bold">Filtre Cash :</span>
                                <a class="btn btn-sm <?= $status==='pending'?'btn-primary':'btn-outline-primary' ?>"
                                    href="?status=pending<?= $search!==''?'&q='.urlencode($search):'' ?>">En attente</a>
                                <a class="btn btn-sm <?= $status==='approved'?'btn-success':'btn-outline-success' ?>"
                                    href="?status=approved<?= $search!==''?'&q='.urlencode($search):'' ?>">Approuvés</a>
                                <a class="btn btn-sm <?= $status==='rejected'?'btn-danger':'btn-outline-danger' ?>"
                                    href="?status=rejected<?= $search!==''?'&q='.urlencode($search):'' ?>">Rejetés</a>
                                <a class="btn btn-sm <?= $status==='all'?'btn-dark':'btn-outline-dark' ?>"
                                    href="?status=all<?= $search!==''?'&q='.urlencode($search):'' ?>">Tous (Cash)</a>
                            </div>

                            <!-- Formulaire de recherche d'élève -->
                            <form method="GET" class="form-inline mt-2 mt-md-0">
                                <input type="hidden" name="status" value="<?= h($status) ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="q" class="form-control"
                                        placeholder="Rechercher élève ou réf..." value="<?= h($search) ?>">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit"><i
                                                class="fas fa-search"></i></button>
                                        <?php if ($search !== ''): ?>
                                        <a href="?status=<?= h($status) ?>" class="btn btn-outline-secondary"><i
                                                class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>

                        </div>

                        <div class="table-responsive">
                            <table class="table display data-table w-100">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Réf</th>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Frais</th>
                                        <th>Mode</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows): foreach ($rows as $r):
                  $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ('#'.$r['eleve']);
                  $classeTxt = trim(($r['classe'] ?? '').' '.($r['classe_desc'] ?? ''));

                  $isValid = (int)($r['is_validated'] ?? 0);
                  $vAt     = $r['validated_at'] ?? null;

                  if ($isValid === 1) {
                      $stBadge = '<span class="badge badge-success">Approuvé</span>';
                  } elseif ($isValid === 0 && $vAt !== null) {
                      $stBadge = '<span class="badge badge-danger">Rejeté</span>';
                  } else {
                      $stBadge = '<span class="badge badge-warning">En attente</span>';
                  }

                  $isPending = ($isValid === 0 && $vAt === null);
                ?>
                                    <tr data-id="<?= (int)$r['id'] ?>">
                                        <td><?= (int)$r['id'] ?></td>
                                        <td><?= h($r['reference']) ?></td>
                                        <td><strong><?= h($name) ?></strong></td>
                                        <td><?= h($classeTxt) ?></td>
                                        <td><?= h($r['statut']) ?></td>
                                        <td><span class="badge badge-info"><?= h($r['mode']) ?></span></td>
                                        <td><strong><?= money($r['montant_paye']) ?> $</strong></td>
                                        <td><?= h($r['date_paiement']) ?></td>
                                        <td class="st-badge"><?= $stBadge ?></td>
                                        <td>
                                            <?php if ($isPending): ?>
                                            <button class="btn btn-sm btn-success act-approve">Approuver</button>
                                            <button class="btn btn-sm btn-outline-danger act-reject">Rejeter</button>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Aucun paiement Cash trouvé.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <input type="hidden" id="csrf" value="<?= h($csrf) ?>">
                    </div>
                </div>

                <?php require_once('layout/footer.php'); ?>
            </div>
        </div>
    </div>

    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    $(function() {
        if ($.fn.DataTable) {
            $('.data-table').DataTable({
                pageLength: 25,
                order: [
                    [0, 'desc']
                ],
                language: {
                    search: "Filtrer le tableau :",
                    lengthMenu: "Afficher _MENU_ éléments",
                    info: "Affichage de _START_ à _END_ sur _TOTAL_ paiements",
                    paginate: {
                        first: "Premier",
                        last: "Dernier",
                        next: "Suivant",
                        previous: "Précédent"
                    }
                }
            });
        }

        function postAction(id, action) {
            const token = $('#csrf').val();
            const $row = $('tr[data-id="' + id + '"]');
            const $badge = $row.find('.st-badge');
            const $btns = $row.find('button');

            $btns.prop('disabled', true);
            $badge.html('<span class="badge badge-info">Traitement...</span>');

            $.ajax({
                url: 'service/approve_cash.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    id: id,
                    action: action,
                    _csrf: token
                },
                success: function(resp) {
                    if (resp && resp.status === 'ok') {
                        if (action === 'approve') {
                            $badge.html('<span class="badge badge-success">Approuvé</span>');
                        } else {
                            $badge.html('<span class="badge badge-danger">Rejeté</span>');
                        }
                        $btns.remove();
                    } else {
                        $badge.html('<span class="badge badge-danger">Erreur</span>');
                        alert(resp && resp.message ? resp.message : 'Erreur inconnue.');
                        $btns.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    $badge.html('<span class="badge badge-danger">Erreur</span>');
                    alert('Erreur ' + xhr.status + ' lors de la requête.');
                    $btns.prop('disabled', false);
                }
            });
        }

        $('.act-approve').on('click', function() {
            const id = $(this).closest('tr').data('id');
            if (confirm('Confirmer l’approbation de ce paiement Cash ?')) {
                postAction(id, 'approve');
            }
        });

        $('.act-reject').on('click', function() {
            const id = $(this).closest('tr').data('id');
            if (confirm('Rejeter ce paiement Cash ?')) {
                postAction(id, 'reject');
            }
        });
    });
    </script>
</body>

</html>