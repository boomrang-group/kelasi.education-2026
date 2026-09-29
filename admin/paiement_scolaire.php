<?php
if (session_status() !== PHP_SESSION_ACTIVE) { 
    session_start(); 
}

foreach ([__DIR__.'/../database/db_connect.php', __DIR__.'/../../database/db_connect.php', __DIR__.'/database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body><p style='color:#b00'>Erreur serveur : DB indisponible.</p></body></html>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Vérification de la session (Essayez 'code_ecole' ou d'autres variantes de clés si besoin)
$codeEcole = $_SESSION['code_ecole'] ?? $_SESSION['code_school'] ?? '';

$studentsList = [];

if (!empty($codeEcole)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                CONCAT(
                    s.first_name, ' ', s.last_name,
                    ' - ',
                    IFNULL(c.classe, ''), ' ',
                    IFNULL(c.description, ''), ' ',
                    IFNULL(niv.description, ''), ' ',
                    IFNULL(sec.description, ''), ' ',
                    IFNULL(opt.description, '')
                ) AS label
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id 
            LEFT JOIN niveau niv ON niv.id = c.niveau 
            LEFT JOIN section sec ON sec.id = c.section 
            LEFT JOIN options opt ON opt.id = c.options 
            WHERE s.code_ecole = ?
            ORDER BY s.first_name ASC
        ");

        $stmt->execute([$codeEcole]);
        $studentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Enregistre l'erreur dans les logs serveur si la requête échoue
        error_log("Erreur SQL paiements: " . $e->getMessage());
    }
}
?>

<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>MyKelasi | Effectuer le paiement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Assets -->
    <link rel="shortcut icon" type="image/x-icon" href="../../img/favicon.png">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../../fonts/flaticon.css">
    <link rel="stylesheet" href="../css/animate.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/datepicker.min.css">
    <link rel="stylesheet" href="../style.css">
    <script src="../js/modernizr-3.6.0.min.js"></script>

    <style>
    .autocomplete-container { position: relative; }
    .autocomplete-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 200px;
        overflow-y: auto;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-top: none;
        border-radius: 0 0 0.25rem 0.25rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
    }
    .autocomplete-suggestion {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f1f1f1;
        font-size: 14px;
    }
    .autocomplete-suggestion:hover,
    .autocomplete-suggestion.active {
        background-color: #f8f9fa;
        color: #007bff;
    }
    </style>
</head>

<body>
    <div id="preloader" class="d-none"></div>
    <div id="wrapper" class="wrapper bg-ash">

        <?php $nav = __DIR__ . '/layout/navbar.php'; if (file_exists($nav)) require $nav; ?>

        <div class="dashboard-page-one">
            <?php $side = __DIR__ . '/layout/sidebar.php'; if (file_exists($side)) require $side; ?>

            <div class="dashboard-content-one">
                <div class="card height-auto">
                    <div class="card-body mt-5">
                        <h3 class="text-uppercase">Effectuer le paiement</h3>

                        <?php if (empty($codeEcole)): ?>
                            <div class="alert alert-warning">
                                ⚠️ Session école non trouvée (`code_ecole`). Veuillez vous re-connecter.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="service/save_paiement.php">
                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Type de frais</label>
                                        <select name="statut" id="statut" class="form-control" required>
                                            <option value="#" selected disabled>Choisir type frais</option>
                                            <option value="Inscription">Inscription</option>
                                            <option value="Minerval">Minerval</option>
                                            <option value="Autre">Autre</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group autocomplete-container">
                                        <label>Élève</label>
                                        <input type="text" id="eleve_input" class="form-control"
                                            placeholder="Saisir le nom de l'élève..." autocomplete="off" required>
                                        <input type="hidden" name="eleve" id="eleve">
                                        <div id="custom_suggestions" class="autocomplete-suggestions"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Montant total $</label>
                                        <input type="number" id="montant_total" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Total déjà payé $</label>
                                        <input type="number" id="total_paye" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Solde restant $</label>
                                        <input type="number" name="solde_restant" id="solde_restant" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Montant payé $</label>
                                        <input type="number" name="montant_paye" id="montant_paye" class="form-control" placeholder="0" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Nouveau solde $</label>
                                <input type="number" name="solde" id="nouveau_solde" class="form-control" readonly>
                            </div>

                            <button class="btn-fill-lg btn-gradient-yellow btn-hover-bluedark">Valider le paiement</button>
                        </form>
                    </div>
                </div>

                <?php $foot = __DIR__ . '/layout/footer.php'; if (file_exists($foot)) require $foot; ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../js/jquery-3.3.1.min.js"></script>
    <script src="../js/plugins.js"></script>
    <script src="../js/popper.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/datepicker.min.js"></script>
    <script src="../js/jquery.scrollUp.min.js"></script>
    <script src="../js/main.js"></script>

    <script>
let soldeRestant = 0;

// Injections sécurisées JSON des données élèves
const studentsData = <?= json_encode($studentsList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

console.log("Élèves chargés depuis PHP:", studentsData);

const eleveInput = document.getElementById('eleve_input');
const hiddenEleve = document.getElementById('eleve');
const suggestionsBox = document.getElementById('custom_suggestions');

// Fonction pour réinitialiser les champs du formulaire (sauf le statut)
function resetFormFields() {
    eleveInput.value = '';
    hiddenEleve.value = '';
    document.getElementById('montant_total').value = '';
    document.getElementById('total_paye').value = '';
    document.getElementById('solde_restant').value = '';
    document.getElementById('montant_paye').value = '';
    document.getElementById('nouveau_solde').value = '';
    soldeRestant = 0;
    suggestionsBox.style.display = 'none';
}

eleveInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    suggestionsBox.innerHTML = '';
    hiddenEleve.value = '';

    if (query === '') {
        suggestionsBox.style.display = 'none';
        return;
    }

    const filtered = studentsData.filter(s => s.label && s.label.toLowerCase().includes(query));

    if (filtered.length > 0) {
        filtered.forEach(s => {
            const div = document.createElement('div');
            div.className = 'autocomplete-suggestion';
            div.textContent = s.label;
            div.addEventListener('click', function() {
                eleveInput.value = s.label;
                hiddenEleve.value = s.id;
                suggestionsBox.style.display = 'none';
                loadMontant();
            });
            suggestionsBox.appendChild(div);
        });
        suggestionsBox.style.display = 'block';
    } else {
        suggestionsBox.style.display = 'none';
    }
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.autocomplete-container')) {
        suggestionsBox.style.display = 'none';
    }
});

function loadMontant() {
    let statut = document.getElementById('statut').value;
    let eleve = document.getElementById('eleve').value;

    if (!statut || !eleve || eleve === '#') return;

    fetch(`service/get_montant.php?statut=${encodeURIComponent(statut)}&eleve=${encodeURIComponent(eleve)}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('montant_total').value = data.montant_total || 0;
            document.getElementById('total_paye').value = data.total_paye || 0;
            soldeRestant = data.solde_restant || 0;
            document.getElementById('solde_restant').value = soldeRestant;
            calculSolde();
        })
        .catch(err => console.error("Erreur Fetch Montant:", err));
}

function calculSolde() {
    let paye = parseFloat(document.getElementById('montant_paye').value || 0);
    let nouveauSolde = soldeRestant - paye;
    if (nouveauSolde < 0) nouveauSolde = 0;
    document.getElementById('nouveau_solde').value = nouveauSolde;
}

// Nettoyage automatique du formulaire lors du changement de type de frais
document.getElementById('statut').addEventListener('change', function() {
    resetFormFields();
    // Si un élève était déjà renseigné, recalculer immédiatement :
    if (hiddenEleve.value) {
        loadMontant();
    }
});

document.getElementById('montant_paye').addEventListener('input', calculSolde);
</script>
</body>
</html>