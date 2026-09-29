<?php
// customs/eleve/view/paiement.php
// Gestion des paiements élève avec file d'attente pour le mode "Cash" (admin approval)

header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

//  $DEBUG = DEBUG; // DEBUG handled in db_connect.php
// if ($DEBUG) {  // display_errors handled in db_connect.php  // display_startup_errors handled in db_connect.php  // error_reporting handled in db_connect.php }

require_once '../../../database/db_connect.php';
if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); } catch(Throwable $e){} }

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'eleve') {
    header('Location: ../../../login/index.php?msg=forbidden');
    exit;
}

$code_ecole = $_SESSION['code_ecole'] ?? null;
$username   = $_SESSION['username']   ?? null;
$email      = $_SESSION['email']      ?? null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', ' '); }

// --- Helpers ---
function hasCol(PDO $pdo, string $table, string $col): bool {
    try {
        $q="SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1";
        $st=$pdo->prepare($q); $st->execute([':t'=>$table, ':c'=>$col]); return (bool)$st->fetchColumn();
    } catch(Throwable $e){ return false; }
}

// --- Élève + classe
function getStudentFull(PDO $pdo, ?string $email, ?string $username, ?string $codeEcole): ?array {
    try{
        $where=''; $p=[];
        if ($email) { $where='email=:em'; $p[':em']=$email; }
        elseif ($username) { $where='username=:un'; $p[':un']=$username; }
        else { return null; }
        if ($codeEcole) { $where.=' AND code_ecole=:ce'; $p[':ce']=$codeEcole; }

        $st=$pdo->prepare("SELECT * FROM students WHERE $where LIMIT 1");
        $st->execute($p);
        if (!($stu=$st->fetch(PDO::FETCH_ASSOC))) return null;

        $cls=null;
        if (!empty($stu['class_id'])) {
            $st2=$pdo->prepare("SELECT * FROM classes WHERE id=:id");
            $st2->execute([':id'=>$stu['class_id']]);
            $cls=$st2->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $stu + ['classe_meta'=>$cls];
    }catch(Throwable $e){}
    return null;
}

$me = getStudentFull($pdo, $email, $username, $code_ecole);
if (!$me) { echo "Élève introuvable."; exit; }

$studentId = (int)$me['id'];
$classId   = (int)($me['class_id'] ?? 0);
$classe    = $me['classe_meta'] ?: [];

$niv = (int)($classe['niveau']  ?? 0);
$sec = (int)($classe['section'] ?? 0);
$opt = (int)($classe['options'] ?? 0);
$cls = (int)($classe['id']      ?? 0);

// --- Colonnes optionnelles (file d'attente)
$hasMode     = hasCol($pdo, 'paiement', 'mode');
$hasApproved = hasCol($pdo, 'paiement', 'approved');

// --- Config montants
$cfgIns = null; $cfgMin = null; $cfgAutres = [];
try {
    if ($classId) {
        $st=$pdo->prepare("SELECT montant FROM frais_d_inscription
                           WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls
                           LIMIT 1");
        $st->execute([':ce'=>$code_ecole, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
        if ($r=$st->fetch(PDO::FETCH_ASSOC)) $cfgIns=(int)$r['montant'];

        $st=$pdo->prepare("SELECT montant FROM minerval
                           WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls
                           LIMIT 1");
        $st->execute([':ce'=>$code_ecole, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
        if ($r=$st->fetch(PDO::FETCH_ASSOC)) $cfgMin=(int)$r['montant'];

        $st=$pdo->prepare("SELECT id, description, montant FROM autres_frais
                           WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls
                           ORDER BY description,id");
        $st->execute([':ce'=>$code_ecole, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
        $cfgAutres=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch(Throwable $e){}

// --- Sommes déjà payées (ne compter que les paiements approuvés si colonne disponible)
$paid = ['Inscription'=>0,'Minerval'=>0,'Autre'=>0];
try{
    $sql = "SELECT statut, COALESCE(SUM(montant_paye),0) s FROM paiement WHERE eleve=:sid";
    if ($hasApproved) { $sql .= " AND approved=1"; }
    $sql .= " GROUP BY statut";
    $st=$pdo->prepare($sql);
    $st->execute([':sid'=>$studentId]);
    while($r=$st->fetch(PDO::FETCH_ASSOC)){
        if(isset($paid[$r['statut']])) $paid[$r['statut']]=(float)$r['s'];
    }
}catch(Throwable $e){}

// --- Totaux par type
$autresTotal=0; foreach($cfgAutres as $af){ $autresTotal+=(int)$af['montant']; }
$totaux = [
    'Inscription' => (int)($cfgIns ?? 0),
    'Minerval'    => (int)($cfgMin ?? 0),
    'Autre'       => (int)$autresTotal
];
$restes = [
    'Inscription' => max(0, $totaux['Inscription'] - $paid['Inscription']),
    'Minerval'    => max(0, $totaux['Minerval']    - $paid['Minerval']),
    'Autre'       => max(0, $totaux['Autre']       - $paid['Autre'])
];

// --- Type demandé
$allowedTypes = ['Inscription','Minerval','Autre'];
$inputType = $_GET['type'] ?? $_POST['type'] ?? '';
$type = in_array($inputType, $allowedTypes, true) ? $inputType : '';
$needsTypeSelection = ($type==='');

// --- Générer une référence unique
function generateReference(PDO $pdo): int {
    for ($i=0; $i<5; $i++) {
        $ref = random_int(10000000, 99999999);
        $st=$pdo->prepare("SELECT 1 FROM paiement WHERE reference=:r LIMIT 1");
        $st->execute([':r'=>$ref]);
        if(!$st->fetch()) return $ref;
    }
    return (int)substr(date('YmdHis'), -8);
}

// --- Enregistrement du paiement
$alert = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='pay' && !$needsTypeSelection) {
    // Recalcule live (toujours côté serveur)
    $due = $totaux[$type];

    // Somme déjà payée approuvée
    $sqlPaid = "SELECT COALESCE(SUM(montant_paye),0) s FROM paiement WHERE eleve=:sid AND statut=:st";
    if ($hasApproved) { $sqlPaid .= " AND approved=1"; }
    $st=$pdo->prepare($sqlPaid);
    $st->execute([':sid'=>$studentId, ':st'=>$type]);
    $already = (float)$st->fetchColumn();
    $resteLive = max(0, $due - $already);

    // Montant demandé
    $raw = str_replace([' ', "\xc2\xa0"], '', (string)($_POST['montant'] ?? ''));
    $raw = str_replace(',', '.', $raw);
    $montant = (float)$raw;

    if ($montant <= 0) {
        $alert = '<div class="alert alert-danger">Montant invalide.</div>';
    } elseif ($resteLive <= 0) {
        $alert = '<div class="alert alert-success">Ce poste est déjà soldé.</div>';
    } else {
        if ($montant > $resteLive) $montant = $resteLive;
        $mode = $_POST['mode'] ?? 'Autre';

        if ($mode === 'MaxiCash') {
            // Redirection vers le processeur MaxiCash
            ?>
            <form id="redirMaxi" action="maxicash_pay.php" method="POST">
                <input type="hidden" name="type" value="<?= h($type) ?>">
                <input type="hidden" name="montant" value="<?= h($montant) ?>">
                <input type="hidden" name="telephone" value="<?= h($_POST['telephone'] ?? '') ?>">
                <input type="hidden" name="action" value="pay">
            </form>
            <script>document.getElementById('redirMaxi').submit();</script>
            <?php
            exit;
        }

        $ref  = generateReference($pdo);
        $now  = date('Y-m-d H:i:s');

        // Si file d’attente dispo: cash = approved=0, sinon approved=1
        $approvedValue = 1;
        if ($hasApproved && strcasecmp($mode, 'Cash') === 0) {
            $approvedValue = 0;
        }

        // Nouveau solde côté "approuvé"
        $newSolde = max(0, $resteLive - ($approvedValue ? $montant : 0));

        try{
            if ($hasMode && $hasApproved) {
                $st=$pdo->prepare("INSERT INTO paiement
                    (reference, statut, eleve, montant_paye, mode, approved, solde, date_paiement, code_ecole)
                    VALUES (:ref,:st,:sid,:montant,:mode,:app,:solde,:dt,:ce)");
                $st->execute([
                    ':ref'=>$ref, ':st'=>$type, ':sid'=>$studentId,
                    ':montant'=>$montant, ':mode'=>$mode, ':app'=>$approvedValue,
                    ':solde'=>$newSolde, ':dt'=>$now, ':ce'=>$code_ecole
                ]);
            } elseif ($hasMode && !$hasApproved) {
                // Pas de colonne approved -> on enregistre normalement (pas d’attente technique)
                $st=$pdo->prepare("INSERT INTO paiement
                    (reference, statut, eleve, montant_paye, mode, solde, date_paiement, code_ecole)
                    VALUES (:ref,:st,:sid,:montant,:mode,:solde,:dt,:ce)");
                $st->execute([
                    ':ref'=>$ref, ':st'=>$type, ':sid'=>$studentId,
                    ':montant'=>$montant, ':mode'=>$mode,
                    ':solde'=>max(0, $resteLive - $montant), ':dt'=>$now, ':ce'=>$code_ecole
                ]);
            } else {
                // Pas de colonne mode -> fallback historique
                $st=$pdo->prepare("INSERT INTO paiement
                    (reference, statut, eleve, montant_paye, solde, date_paiement, code_ecole)
                    VALUES (:ref,:st,:sid,:montant,:solde,:dt,:ce)");
                $st->execute([
                    ':ref'=>$ref, ':st'=>$type, ':sid'=>$studentId,
                    ':montant'=>$montant, ':solde'=>max(0, $resteLive - $montant), ':dt'=>$now, ':ce'=>$code_ecole
                ]);
            }

            if ($hasApproved && strcasecmp($mode, 'Cash') === 0) {
                // Paiement enregistré mais en attente
                $alert = '<div class="alert alert-info">Votre paiement <strong>Cash</strong> a été enregistré et est <strong>en attente de validation</strong> par l’administration.</div>';
            } else {
                // Approuvé immédiatement
                header('Location: finances.php?msg=paid');
                exit;
            }
        }catch(Throwable $e){
            $alert = '<div class="alert alert-danger">Erreur enregistrement : '.h($e->getMessage()).'</div>';
        }
    }
}

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= $needsTypeSelection ? 'Choisir un type de paiement' : 'Paiement — '.h($type) ?> | Espace élève</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../../../img/favicon.png">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/main.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="../../../css/all.min.css"> -->
    <link rel="stylesheet" href="../../../fonts/flaticon.css">
    <link rel="stylesheet" href="../../../css/animate.min.css">
    <link rel="stylesheet" href="../../../css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../style.css">
    <script src="../../../js/modernizr-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        .muted{color:#6b7280}
        .money{font-weight:700}
        .card-soft{border:1px solid #e5e7eb;border-radius:12px}
        .grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem}
    </style>
</head>
<body>
<div id="wrapper" class="wrapper bg-ash">
    <?php include '../layout/navbar.php'; ?>
    <div class="dashboard-page-one">
        <?php include '../layout/sidebar.php'; ?>

        <div class="dashboard-content-one">
            <div class="breadcrumbs-area">
                <h3><?= $needsTypeSelection ? 'Choisir un type de paiement' : 'Paiement — '.h($type) ?></h3>
                <p class="muted">Classe : <strong><?= h(($classe['classe']??'').' '.($classe['description']??'')) ?></strong></p>
            </div>

            <?php if ($needsTypeSelection): ?>
                <div class="grid-3">
                    <?php foreach (['Inscription','Minerval','Autre'] as $t): ?>
                        <div class="card card-soft">
                            <div class="card-body">
                                <h5><?= h($t) ?></h5>
                                <div>Montant dû : <span class="money"><?= money($totaux[$t]) ?> $</span></div>
                                <div>Déjà payé : <span class="money"><?= money($paid[$t]) ?> $</span></div>
                                <div>Reste : <span class="money"><?= money(max(0, $totaux[$t]-$paid[$t])) ?> $</span></div>
                                <div class="mt-2">
                                    <?php if ($totaux[$t] <= 0): ?>
                                        <span class="badge badge-secondary">Non configuré</span>
                                    <?php elseif (max(0, $totaux[$t]-$paid[$t]) <= 0): ?>
                                        <span class="badge badge-success">Soldé</span>
                                    <?php else: ?>
                                        <a class="btn btn-primary btn-sm" href="paiement.php?type=<?= urlencode($t) ?>">Payer ce type</a>
                                    <?php endif; ?>
                                </div>
                                <?php if ($t==='Autre' && $cfgAutres): ?>
                                    <hr>
                                    <small class="muted">Détails :</small>
                                    <ul class="mb-0">
                                        <?php foreach ($cfgAutres as $a): ?>
                                            <li><?= h($a['description']) ?> — <?= money((int)$a['montant']) ?> $</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php include '../layout/footer.php'; ?>
                </div></div></div>
                <script src="../../../js/jquery-3.3.1.min.js"></script>
                <script src="../../../js/plugins.js"></script>
                <script src="../../../js/popper.min.js"></script>
                <script src="../../../js/bootstrap.min.js"></script>
                <script src="../../../js/main.js"></script>
                </body></html>
                <?php exit; ?>
            <?php endif; ?>

            <?php
                // Si on est ici, le type est valide
                $due     = $totaux[$type];
                $already = $paid[$type];
                $reste   = max(0, $due - $already);
            ?>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-soft">
                        <div class="card-body">
                            <h5>Résumé</h5>
                            <div>Type : <strong><?= h($type) ?></strong></div>
                            <div>Montant dû : <span class="money"><?= money($due) ?> $</span></div>
                            <div>Déjà payé (approuvé) : <span class="money"><?= money($already) ?> $</span></div>
                            <div>Reste à payer : <span class="money"><?= money($reste) ?> $</span></div>
                            <?php if ($type==='Autre' && $cfgAutres): ?>
                                <hr>
                                <div class="muted">Détails des autres frais :</div>
                                <ul class="mb-0">
                                    <?php foreach ($cfgAutres as $a): ?>
                                        <li><?= h($a['description']) ?> — <?= money((int)$a['montant']) ?> $</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-soft">
                        <div class="card-body">
                            <h5>Effectuer un paiement</h5>
                            <?= $alert ?>
                            <?php if ($due<=0): ?>
                                <div class="alert alert-warning">Aucun montant configuré pour ce type. Contactez l’administration.</div>
                                <a class="btn btn-outline-secondary" href="finances.php">Retour</a>
                            <?php elseif ($reste<=0): ?>
                                <div class="alert alert-success">Ce poste est déjà soldé.</div>
                                <a class="btn btn-outline-secondary" href="finances.php">Retour</a>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="pay">
                                    <input type="hidden" name="type" value="<?= h($type) ?>">

                                    <div class="form-group">
                                        <label>Montant à payer ($)</label>
                                        <input type="number" step="1" min="1" max="<?= (int)$reste ?>" class="form-control"
                                               name="montant" value="<?= (int)$reste ?>" required>
                                        <small class="muted">Maximum autorisé : <?= money($reste) ?> $.</small>
                                    </div>

                                    <div class="form-group" id="phone_group" style="display:none;">
                                        <label>Téléphone (pour MaxiCash/Mobile Money)</label>
                                        <input type="tel" name="telephone" class="form-control" placeholder="+243...">
                                    </div>

                                    <div class="form-group">
                                        <label>Mode de paiement</label>
                                        <select name="mode" id="mode_select" class="form-control" required onchange="togglePhoneField()">
                                            <option value="MaxiCash">MaxiCash (Mobile Money / Cards)</option>
                                            <option value="Mobile Money">Mobile Money (M-Pesa / Airtel / Orange)</option>
                                            <option value="Carte">Carte bancaire</option>
                                            <option value="Cash">Cash (à l’école)</option>
                                            <option value="Autre">Autre</option>
                                        </select>
                                        <?php if ($hasApproved): ?>
                                            <small class="muted d-block mt-1">
                                                NB: Les paiements <strong>Cash</strong> apparaîtront “en attente” jusqu’à validation par l’administration.
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary">Enregistrer le paiement</button>
                                        <a class="btn btn-outline-secondary" href="finances.php">Annuler</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../layout/footer.php'; ?>
        </div>
    </div>
</div>

    <script src="../../../js/jquery-3.3.1.min.js"></script>
    <script src="../../../js/plugins.js"></script>
    <script src="../../../js/popper.min.js"></script>
    <script src="../../../js/bootstrap.min.js"></script>
    <script src="../../../js/jquery.dataTables.min.js"></script>
    <script src="../../../js/main.js"></script>
    <script>
        function togglePhoneField() {
            const mode = document.getElementById('mode_select').value;
            const phoneGroup = document.getElementById('phone_group');
            if (mode === 'MaxiCash' || mode === 'Mobile Money') {
                phoneGroup.style.display = 'block';
            } else {
                phoneGroup.style.display = 'none';
            }
        }
        window.onload = togglePhoneField;
    </script>
</body>
</html>
