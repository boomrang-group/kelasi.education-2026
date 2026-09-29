<?php
// customs/eleve/view/finances.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../database/db_connect.php';

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$studentId = $_SESSION['student_id'] ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }

/* ===== ÉLÈVE ===== */
$st = $pdo->prepare("SELECT * FROM students WHERE id=:id LIMIT 1");
$st->execute([':id'=>$studentId]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if(!$me){
    echo "<div class='p-4 text-danger'>Élève introuvable</div>";
    exit;
}
$classId = (int)$me['class_id'];

/* ===== CLASSE ===== */
$st = $pdo->prepare("SELECT * FROM classes WHERE id=:id LIMIT 1");
$st->execute([':id'=>$classId]);
$classe = $st->fetch(PDO::FETCH_ASSOC);

/* ===== FRAIS ===== */
/* ===== INFOS CLASSE COMPLETES ===== */
$niveau  = $classe['niveau'] ?? '';
$section = $classe['section'] ?? '';
$option  = $classe['options'] ?? '';

/* ===== FRAIS D’INSCRIPTION ===== */
$st = $pdo->prepare("
    SELECT montant 
    FROM frais_d_inscription 
    WHERE classe = :classe
    LIMIT 1
");
$st->execute([':classe' => $classId]);
$cfgIns = (int)($st->fetchColumn() ?: 0);

/* ===== MINERVAL ===== */
$st = $pdo->prepare("
    SELECT montant 
    FROM minerval 
    WHERE classe = :classe
    LIMIT 1
");
$st->execute([':classe' => $classId]);
$cfgMin = (int)($st->fetchColumn() ?: 0);

/* ===== AUTRES FRAIS ===== */
$st = $pdo->prepare("
    SELECT montant 
    FROM autres_frais 
    WHERE classe = :classe
");
$st->execute([':classe' => $classId]);
$cfgAutres = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== PAIEMENTS VALIDÉS ===== */
$sum=['Inscription'=>0,'Minerval'=>0,'Autre'=>0];
$st=$pdo->prepare("
    SELECT statut, COALESCE(SUM(montant_paye),0) s
    FROM paiement
    WHERE eleve=:e AND is_validated=1
    GROUP BY statut
");
$st->execute([':e'=>$studentId]);
while($r=$st->fetch(PDO::FETCH_ASSOC)){
    $sum[$r['statut']] = (float)$r['s'];
}

$autresTotal = array_sum(array_column($cfgAutres,'montant'));
$resteIns = max(0,$cfgIns-$sum['Inscription']);
$resteMin = max(0,$cfgMin-$sum['Minerval']);
$resteAut = max(0,$autresTotal-$sum['Autre']);

/* ===== HISTORIQUE DES PAIEMENTS ===== */
$histPaiements = [];

try {
    $st = $pdo->prepare("
        SELECT 
            id,
            reference,
            statut,
            montant_paye,
            solde,
            mode,
            date_paiement,
            is_validated
        FROM paiement
        WHERE eleve = :e
        ORDER BY date_paiement DESC, id DESC
    ");
    $st->execute([':e' => $studentId]);
    $histPaiements = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $histPaiements = [];
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mes finances | Espace élève</title>

    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/all.min.css">
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous" />
    <script src="../../../js/modernizr-3.6.0.min.js"></script>

    <style>
    .money {
        font-weight: 700
    }

    .muted {
        color: #6b7280
    }

    .card-soft {
        border: 1px solid #e5e7eb;
        border-radius: 12px
    }

    .due {
        color: #b91c1c
    }

    .pay-method {
        border: 1px dashed #ccc;
        border-radius: 10px;
        padding: 14px;
        cursor: pointer;
        transition: .2s;
    }

    .pay-method:hover {
        background: #f8f9fa
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
                    <h3>Mes finances</h3>
                    <!-- <p class="muted">Classe : <strong><?= h($classe['classe']??'') ?></strong></p> -->
                </div>

                <div class="row">

                    <!-- INSCRIPTION -->
                    <div class="col-lg-4 mb-3">
                        <div class="card card-soft h-100">
                            <div class="card-body">
                                <h5>Frais d'inscription</h5>
                                <div>Montant à payer :
                                    <span class="money"><?= number_format($cfgIns,0,',',' ') ?> $</span>
                                </div>

                                <div>Montant payé :
                                    <span class="money text-success"><?= number_format($sum['Inscription'],0,',',' ') ?>
                                        $</span>
                                </div>

                                <div>Reste à payer :
                                    <span class="money due"><?= number_format($resteIns,0,',',' ') ?> $</span>
                                </div>
                                <?php if($resteIns>0): ?>
                                <button class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mt-2"
                                    onclick="openChoixPaiement('Inscription', <?= (int)$resteIns ?>)">
                                    <i class="fas fa-wallet mr-1"></i> Payer
                                </button>
                                <?php else: ?><span class="badge badge-success mt-2">Soldé</span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MINERVAL -->
                    <div class="col-lg-4 mb-3">
                        <div class="card card-soft h-100">
                            <div class="card-body">
                                <h5>Minerval</h5>
                                <div>Montant à payer :
                                    <span class="money"><?= number_format($cfgMin,0,',',' ') ?> $</span>
                                </div>

                                <div>Montant payé :
                                    <span class="money text-success"><?= number_format($sum['Minerval'],0,',',' ') ?>
                                        $</span>
                                </div>

                                <div>Reste à payer :
                                    <span class="money due"><?= number_format($resteMin,0,',',' ') ?> $</span>
                                </div>
                                <?php if($resteMin>0): ?>
                                <button class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mt-2"
                                    onclick="openChoixPaiement('Minerval', <?= (int)$resteMin ?>)">
                                    <i class="fas fa-wallet mr-1"></i> Payer
                                </button>
                                <?php else: ?><span class="badge badge-success mt-2">Soldé</span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- AUTRES -->
                    <div class="col-lg-4 mb-3">
                        <div class="card card-soft h-100">
                            <div class="card-body">
                                <h5>Autres frais</h5>
                                <div>Montant à payer :
                                    <span class="money"><?= number_format($autresTotal,0,',',' ') ?> $</span>
                                </div>

                                <div>Montant payé :
                                    <span class="money text-success"><?= number_format($sum['Autre'],0,',',' ') ?>
                                        $</span>
                                </div>

                                <div>Reste à payer :
                                    <span class="money due"><?= number_format($resteAut,0,',',' ') ?> $</span>
                                </div>
                                <?php if($resteAut>0): ?>
                                <button class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark mt-2"
                                    onclick="openChoixPaiement('Autre', <?= (int)$resteAut ?>)">
                                    <i class="fas fa-wallet mr-1"></i> Payer
                                </button>
                                <?php else: ?><span class="badge badge-success mt-2">Soldé</span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- MODAL CHOIX -->
                <div class="modal fade" id="modalChoixPaiement" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title"><i class="fas fa-credit-card mr-2"></i>Choisir un moyen</h5>
                                <button type="button" class="close"
                                    onclick="closeModal('modalChoixPaiement')"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="pay-method mb-3" onclick="choisirCash()">
                                    💵 <strong>Paiement Cash</strong>
                                    <div class="small muted">À la caisse</div>
                                </div>
                                <div class="pay-method" onclick="choisirOnline()">
                                    🌐 <strong>Paiement en ligne</strong>
                                    <div class="small muted">MaxiCash</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL CASH -->
                <div class="modal fade" id="modalCash" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">

                            <div class="modal-header bg-light">
                                <h5 class="modal-title">
                                    <i class="fas fa-money-bill-wave mr-2"></i>
                                    Paiement Cash
                                </h5>
                                <button type="button" class="close" onclick="closeModal('modalCash')">
                                    <span>&times;</span>
                                </button>
                            </div>

                            <div class="modal-body">
                                <label class="small muted mb-1">
                                    Montant à payer (modifiable)
                                </label>

                                <div class="input-group">
                                    <input type="number" id="cashAmount" class="form-control">
                                    <div class="input-group-append">
                                        <span class="input-group-text">$</span>
                                    </div>
                                </div>

                                <small class="muted d-block mt-1">
                                    Montant restant pour <strong id="labelType"></strong>
                                </small>

                                <button class="btn btn-success btn-block mt-3" onclick="enregistrerPaiementCash()">
                                    <i class="fas fa-save mr-1"></i>
                                    Enregistrer le paiement cash
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ===== HISTORIQUE DES PAIEMENTS ===== -->
                <div class="card card-soft mt-4">
                    <div class="card-body">

                        <h5 class="mb-3">
                            <i class="fas fa-list mr-1"></i>
                            Historique de mes paiements
                        </h5>

                        <div class="table-responsive">
                            <table id="tablePaiements" class="table table-striped table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Référence</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php if (!$histPaiements): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Aucun paiement enregistré
                                        </td>
                                    </tr>
                                    <?php else: foreach ($histPaiements as $p): ?>
                                    <tr>
                                        <td><?= (int)$p['id'] ?></td>
                                        <td><?= h($p['reference']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= h($p['statut']) ?>
                                            </span>
                                        </td>
                                        <td class="font-weight-bold">
                                            <?= number_format((float)$p['montant_paye'],0,',',' ') ?> $
                                        </td>
                                        <td>
                                            <?= h(ucfirst($p['mode'] ?? '—')) ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$p['is_validated'] === 1): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Validé
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> En attente
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>

                                </tbody>
                            </table>
                        </div>

                        <small class="muted">
                            Les paiements cash sont validés par l’administration.
                        </small>

                    </div>
                </div>


                <?php include '../layout/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- SCRIPTS — ORDRE CRITIQUE -->
    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/plugins.js"></script>
    <script src="../../../js/popper.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script src="../../../js/main.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>

    <script>
    let PAY_TYPE = null;
    let PAY_AMOUNT = 0;

    /* ===== utilitaires modals ===== */
    function showModal(id) {
        if (window.jQuery && typeof $().modal === 'function') {
            $('#' + id).modal('show');
        } else {
            const m = document.getElementById(id);
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
            const b = document.createElement('div');
            b.className = 'modal-backdrop fade show';
            b.id = 'bd-' + id;
            document.body.appendChild(b);
        }
    }

    function closeModal(id) {
        if (window.jQuery && typeof $().modal === 'function') {
            $('#' + id).modal('hide');
        } else {
            const m = document.getElementById(id);
            m.style.display = 'none';
            m.classList.remove('show');
            document.body.classList.remove('modal-open');
            const b = document.getElementById('bd-' + id);
            if (b) b.remove();
        }
    }

    /* ===== ouverture paiement ===== */
    function openChoixPaiement(type, montant) {
        PAY_TYPE = type;
        PAY_AMOUNT = montant;
        showModal('modalChoixPaiement');
    }

    /* ===== choix cash ===== */
    function choisirCash() {
        closeModal('modalChoixPaiement');

        document.getElementById('cashAmount').value = PAY_AMOUNT;
        document.getElementById('labelType').innerText = PAY_TYPE;

        showModal('modalCash');
    }

    /* ===== online ===== */
    function choisirOnline() {
        window.location.href =
            'maxicash/paiement.php?type=' +
            encodeURIComponent(PAY_TYPE) +
            '&montant=' +
            encodeURIComponent(PAY_AMOUNT);
    }

    /* ===== ENREGISTREMENT CASH ===== */
    function enregistrerPaiementCash() {
        const montant = document.getElementById('cashAmount').value;

        if (!montant || montant <= 0) {
            alert("Veuillez saisir un montant valide");
            return;
        }

        fetch('service/enregistrer_paiement_cash.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'type=' + encodeURIComponent(PAY_TYPE) +
                    '&montant=' + encodeURIComponent(montant) +
                    '&mode=cash'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Paiement cash enregistré avec succès.\nEn attente de validation.");
                    closeModal('modalCash');
                    location.reload();
                } else {
                    alert(data.message || 'Erreur lors de l’enregistrement');
                }
            })
            .catch(() => alert("Erreur réseau"));
    }

    $(document).ready(function() {
        $('#tablePaiements').DataTable({
            pageLength: 5,
            order: [
                [5, 'desc']
            ],
            language: {
                search: "Rechercher :",
                lengthMenu: "Afficher _MENU_ lignes",
                info: "Affichage _START_ à _END_ sur _TOTAL_ paiements",
                paginate: {
                    previous: "Préc.",
                    next: "Suiv."
                },
                zeroRecords: "Aucun résultat trouvé"
            }
        });
    });
    </script>

</body>

</html>