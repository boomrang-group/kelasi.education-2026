<?php
declare(strict_types=1);
session_start();

$pdo = null;

foreach ([
    __DIR__.'/../database/db_connect.php',
    __DIR__.'/../../database/db_connect.php'
] as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

if (!isset($pdo)) {
    die("Erreur de connexion");
}

function h($s){
    return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
}

function money($v){
    return number_format((float)$v,0,',',' ').' $';
}

/*-----------------------
    ID DU PAIEMENT
-----------------------*/

$paiementId = (int)($_GET['paiement_id'] ?? 0);

if($paiementId<=0){
    die("Paiement invalide");
}

/*-----------------------
   RECUPERATION COMPLETE
-----------------------*/

$sql="
SELECT
    p.*,

    s.first_name,
    s.last_name,
    s.gender,

    c.classe,
    c.description classe_desc,

    n.description niveau,
    sec.description section,
    opt.description option_name

FROM paiement p

INNER JOIN students s
        ON s.id=p.eleve

LEFT JOIN classes c
       ON c.id=s.class_id

LEFT JOIN niveau n
       ON n.id=c.niveau

LEFT JOIN section sec
       ON sec.id=c.section

LEFT JOIN options opt
       ON opt.id=c.options

WHERE p.id=?

LIMIT 1
";

$st=$pdo->prepare($sql);
$st->execute([$paiementId]);

$data=$st->fetch(PDO::FETCH_ASSOC);

if(!$data){
    die("Paiement introuvable");
}

$p=$data;
$student=$data;
$statut=$data['statut'];

if (!$student) die("Étudiant introuvable");

// PAIEMENT (dernier)
$st = $pdo->prepare("
    SELECT *
    FROM paiement
    WHERE eleve = ?
    AND statut = ?
    ORDER BY id DESC
    LIMIT 1
");
$st->execute([$studentId, $statut]);
$p = $st->fetch(PDO::FETCH_ASSOC);

// TYPE LABEL PROPRE
$typeLabel = match($statut) {
    'Inscription' => 'FRAIS D’INSCRIPTION',
    'Minerval'    => 'MINERVAL',
    'Autre'       => 'AUTRES FRAIS',
    default       => strtoupper($statut)
};

// NUMERO RECU (Année + ID paiement)
$year = date('Y');
$receiptNumber = $year . '-' . str_pad((string)($p['id'] ?? 0), 6, '0', STR_PAD_LEFT);

// ECOLE (optionnel)
$school = $_SESSION['code_ecole'] ?? '';
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>MyKelasi | Reçu de paiement</title>

    <meta charset="utf-8">
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title> MyKelasi | Aperçu du reçu de paiement
</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <!-- Normalize CSS -->
    <link rel="stylesheet" href="../css/normalize.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="../css/all.min.css">
    <!-- Flaticon CSS -->
    <link rel="stylesheet" href="../fonts/flaticon.css">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="../css/animate.min.css">
    <!-- Data Table CSS -->
    <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css">

    <style>
    body {
        background: #eef2f7;
        font-family: Arial, sans-serif;
    }

    .receipt {
        max-width: 800px;
        margin: auto;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .receipt-header {
        background: #0d6efd;
        color: #fff;
        padding: 20px;
        text-align: center;
    }

    .receipt-header h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .receipt-header small {
        opacity: .9;
    }

    .receipt-body {
        padding: 25px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px dashed #ddd;
    }

    .label {
        font-weight: 600;
        color: #333;
    }

    .value {
        font-weight: 500;
    }

    .badge-type {
        background: #198754;
        color: #fff;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
    }

    .receipt-footer {
        text-align: center;
        padding: 20px;
        border-top: 1px solid #eee;
    }

    .btn-print {
        margin-top: 10px;
    }

    .receipt-number {
        font-size: 13px;
        color: #666;
    }

    @media print {
        .btn-print {
            display: none;
        }

        body {
            background: #fff;
        }

        .receipt {
            box-shadow: none;
        }
    }
    </style>
</head>

<body style="background:#fff">
    <div class="receipt">
        <!-- HEADER -->
        <div class="receipt-header">
            <h2 class="text-white">REÇU DE PAIEMENT</h2>
            <small><?= h($school) ?> | Année <?= date('Y') ?></small>
        </div>

        <div class="receipt-body">

            <!-- TYPE + NUMERO -->
            <div class="info-row">
                <span class="label">Type</span>
                <span class="badge-type"><?= h($typeLabel) ?></span>
            </div>

            <div class="info-row">
                <span class="label">Numéro de reçu</span>
                <span class="value"><?= h($receiptNumber) ?></span>
            </div>

            <hr>

            <!-- ELEVE -->
            <div class="info-row">
                <span class="label">Élève</span>
                <span class="value"><?= h($student['first_name'].' '.$student['last_name']) ?></span>
            </div>

            <div class="info-row">
                <span class="label">Classe</span>
                <span class="value"><?= h($student['classe'].' '.$student['classe_desc']) ?></span>
            </div>

            <hr>

            <!-- PAIEMENT -->
            <div class="info-row">
                <span class="label">Montant payé</span>
                <span class="value text-success fw-bold"><?= money($p['montant_paye'] ?? 0) ?></span>
            </div>

            <div class="info-row">
                <span class="label">Solde</span>
                <span class="value text-danger"><?= money($p['solde'] ?? 0) ?></span>
            </div>

            <div class="info-row">
                <span class="label">Date de paiement</span>
                <span class="value"><?= h($p['date_paiement'] ?? '—') ?></span>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="receipt-footer">

            <div class="receipt-number">
                Reçu imprimé, non remboursable et non annulable !
            </div>

            <!-- <button onclick="window.print()" class="btn btn-primary btn-md btn-print">
                <i class="fas fa-print"></i> Imprimer le reçu
            </button> -->

        </div>

    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/main.js"></script>

</body>

</html>