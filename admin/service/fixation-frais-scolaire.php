<?php
// admin/service/fixation-frais-scolaire.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- DB connect (chemins tolérants) ---
$pdo = null;
foreach ([__DIR__ . '/../../database/db_connect.php', __DIR__ . '/../../../database/db_connect.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Erreur serveur (DB).');
}

function back(string $code = 'error'): void {
    // Retourne à l’écran d’édition
    header('Location: ../fixation-frais-scolaire.php?msg=' . urlencode($code));
    exit;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// --- Auth / contexte école ---
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = strtolower((string)($_SESSION['role'] ?? ''));
$isAdmin  = in_array($role, ['admin', 'administrateur'], true);

$codeEcole = (string)($_SESSION['code_ecole'] ?? '');
if ($userId && $codeEcole === '') {
    $st = $pdo->prepare("SELECT code_ecole FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id' => $userId]);
    $codeEcole = (string)($st->fetchColumn() ?: '');
    if ($codeEcole) $_SESSION['code_ecole'] = $codeEcole;
}

if (!$isAdmin || $codeEcole === '') {
    http_response_code(403);
    exit('Accès refusé.');
}

// --- CSRF ---
$csrfForm = (string)($_POST['_csrf'] ?? '');
if ($csrfForm === '' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfForm)) {
    back('invalid');
}

// --- Détermine l'action (quel onglet a soumis) ---
$doInscription = isset($_POST['save-inscription']);
$doMinerval    = isset($_POST['save-minerval']);
$doAutre       = isset($_POST['save-autre']);

if (!$doInscription && !$doMinerval && !$doAutre) {
    back('invalid');
}

// --- Inputs communs ---
$classIds = array_filter(array_map('intval', (array)($_POST['classes'] ?? [])), fn($v) => $v > 0);
$montant  = (string)($_POST['montant'] ?? '');
$descAutre= (string)($_POST['description'] ?? ''); // requis que pour Autres frais

// Validation souple : on exige au moins 1 classe + un montant pour l’onglet soumis.
// (On ne bloque pas les autres onglets.)
if (count($classIds) < 1) { back('invalid'); }
if ($montant === '' || !is_numeric($montant)) { back('invalid'); }
$montantInt = (int)round((float)$montant);
if ($montantInt < 0) { back('invalid'); }
if ($doAutre && $descAutre === '') { back('invalid'); }

// --- Récupère le mapping des classes (et vérifie qu’elles appartiennent à ce code_ecole) ---
if (count($classIds) > 1000) {
    // garde-fou
    $classIds = array_slice($classIds, 0, 1000);
}
$placeholders = implode(',', array_fill(0, count($classIds), '?'));

$sqlMap = "
  SELECT 
         c.id AS classe_id,
         c.niveau AS niveau_id,
         c.section AS section_id,
         c.options AS option_id,
         c.code_ecole
  FROM classes c
  WHERE c.id IN ($placeholders) AND c.code_ecole = ?
";

$stMap = $pdo->prepare($sqlMap);
$bind  = $classIds;
$bind[] = $codeEcole;
$stMap->execute($bind);
$rows = $stMap->fetchAll(PDO::FETCH_ASSOC);

if (!$rows || count($rows) !== count($classIds)) {
    // au moins une classe n'est pas trouvée / pas liée à cette école
    back('badclass');
}

// --- Prépare les UPSERT selon l’onglet ---
try {
    $pdo->beginTransaction();

    if ($doInscription) {
        // frais_d_inscription: UNIQUE (code_ecole, niveau, section, OPTION, classe)
        $sql = "
          INSERT INTO frais_d_inscription (classe, niveau, section, `OPTION`, montant, code_ecole)
          VALUES (:classe, :niveau, :section, :opt, :montant, :code)
          ON DUPLICATE KEY UPDATE montant = VALUES(montant)
        ";
        $ins = $pdo->prepare($sql);

        foreach ($rows as $r) {
            $ins->execute([
                ':classe'  => (int)$r['classe_id'],
                ':niveau'  => (int)$r['niveau_id'],
                ':section' => (int)$r['section_id'],
                ':opt'     => (int)$r['option_id'],
                ':montant' => $montantInt,
                ':code'    => $codeEcole,
            ]);
        }
    }
    elseif ($doMinerval) {
        // minerval: UNIQUE (code_ecole, niveau, section, OPTION, classe)
        $sql = "
          INSERT INTO minerval (niveau, section, `OPTION`, classe, montant, code_ecole)
          VALUES (:niveau, :section, :opt, :classe, :montant, :code)
          ON DUPLICATE KEY UPDATE montant = VALUES(montant)
        ";
        $ins = $pdo->prepare($sql);

        foreach ($rows as $r) {
            $ins->execute([
                ':classe'  => (int)$r['classe_id'],
                ':niveau'  => (int)$r['niveau_id'],
                ':section' => (int)$r['section_id'],
                ':opt'     => (int)$r['option_id'],
                ':montant' => $montantInt,
                ':code'    => $codeEcole,
            ]);
        }
    }
    elseif ($doAutre) {
        // autres_frais: UNIQUE (code_ecole, niveau, section, OPTION, classe)
        $sql = "
          INSERT INTO autres_frais (niveau, section, `OPTION`, classe, montant, description, code_ecole)
          VALUES (:niveau, :section, :opt, :classe, :montant, :descr, :code)
          ON DUPLICATE KEY UPDATE montant = VALUES(montant), description = VALUES(description)
        ";
        $ins = $pdo->prepare($sql);

        foreach ($rows as $r) {
            $ins->execute([
                ':classe'  => (int)$r['classe_id'],
                ':niveau'  => (int)$r['niveau_id'],
                ':section' => (int)$r['section_id'],
                ':opt'     => (int)$r['option_id'],
                ':montant' => $montantInt,
                ':descr'   => $descAutre,
                ':code'    => $codeEcole,
            ]);
        }
    }

    $pdo->commit();
    back('created');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // Optionnel: logger l’erreur
    // error_log('Fixation frais error: ' . $e->getMessage());
    back('error');
}
