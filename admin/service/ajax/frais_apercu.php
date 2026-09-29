<?php
// service/ajax/frais_apercu.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

// --- Connexion DB ---
$pdo = null;
foreach ([__DIR__.'/../../database/db_connect.php', __DIR__.'/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de connexion DB']);
    exit;
}

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if (!$codeEcole) {
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit;
}

$type = $_POST['type'] ?? 'inscription';
$classeIds = $_POST['classe_ids'] ?? [];

// Mappage du type vers les tables et configurations d'affichage
$tableMap = [
    'inscription' => ['table' => 'frais_d_inscription', 'label' => 'Frais d\'inscription'],
    'minerval'    => ['table' => 'minerval', 'label' => 'Minerval'],
    'autres'      => ['table' => 'autres_frais', 'label' => 'Autres frais']
];

if (!array_key_exists($type, $tableMap)) {
    echo json_encode(['status' => 'error', 'message' => 'Type de frais invalide']);
    exit;
}

$targetTable = $tableMap[$type]['table'];
$hasDescription = ($type === 'autres');

// Requête SQL de récupération des frais configurés
$sql = "
    SELECT f.*, 
           c.classe AS nom_classe, c.description AS desc_classe,
           n.description AS nom_niveau, 
           s.description AS nom_section, 
           o.description AS nom_option
    FROM {$targetTable} f
    INNER JOIN classes c ON (c.id = f.classe AND c.code_ecole = f.code_ecole)
    LEFT JOIN niveau n ON c.niveau = n.id
    LEFT JOIN section s ON c.section = s.id
    LEFT JOIN options o ON c.options = o.id
    WHERE f.code_ecole = :code_ecole
";

$params = [':code_ecole' => $codeEcole];

// Si des classes précises sont cochées
if (!empty($classeIds) && is_array($classeIds)) {
    $cleanIds = array_map('intval', $classeIds);
    $inClause = implode(',', $cleanIds);
    if (!empty($inClause)) {
        $sql .= " AND f.classe IN ($inClause)";
    }
}

$sql .= " ORDER BY n.description, s.description, o.description, c.classe";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Construction du tableau HTML
ob_start();
?>

<?php if (empty($frais)): ?>
    <div class="alert alert-warning mb-0">
        Aucun tarif enregistré pour <strong><?= h($tableMap[$type]['label']) ?></strong><?= !empty($classeIds) ? ' pour la sélection' : '' ?>.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Classe / Option</th>
                    <?php if ($hasDescription): ?>
                        <th>Description</th>
                    <?php endif; ?>
                    <th class="text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($frais as $f): 
                    $lbl = trim(($f['nom_classe'] ?? '').' '.($f['desc_classe'] ?? '').' - '.($f['nom_niveau'] ?? '').' '.($f['nom_section'] ?? '').' '.($f['nom_option'] ?? ''));
                ?>
                    <tr>
                        <td><?= h($lbl) ?></td>
                        <?php if ($hasDescription): ?>
                            <td><?= h($f['description'] ?? '') ?></td>
                        <?php endif; ?>
                        <td class="text-right font-weight-bold">
                            <?= number_format((float)($f['montant'] ?? 0), 2, ',', ' ') ?> $
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$html = ob_get_clean();

echo json_encode([
    'status' => 'ok',
    'html' => $html
]);