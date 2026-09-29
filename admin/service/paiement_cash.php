<?php
// admin/service/approve_cash.php
// POST { id, action: approve|reject, _csrf } -> JSON
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); echo json_encode(['status'=>'err','message'=>'DB indisponible']); exit; }

// Auth admin
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = strtolower((string)($_SESSION['role'] ?? ''));
$code_ecole = (string)($_SESSION['code_ecole'] ?? '');
$isAdmin  = in_array($role, ['admin','administrateur'], true);
if (!$isAdmin) { http_response_code(403); echo json_encode(['status'=>'err','message'=>'Accès refusé']); exit; }

// CSRF
$csrf = $_POST['_csrf'] ?? '';
if (!$csrf || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$csrf)) {
    http_response_code(400); echo json_encode(['status'=>'err','message'=>'CSRF invalide']); exit;
}

// Helpers
function hasCol(PDO $pdo, string $t, string $c): bool {
    try {
        $q="SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name=:t AND column_name=:c LIMIT 1";
        $st=$pdo->prepare($q); $st->execute([':t'=>$t, ':c'=>$c]); return (bool)$st->fetchColumn();
    } catch(Throwable $e){ return false; }
}
function json_fail(string $m, int $code=200){ http_response_code($code); echo json_encode(['status'=>'err','message'=>$m]); exit; }

// Colonnes requises
$hasMode     = hasCol($pdo, 'paiement', 'mode');
$hasApproved = hasCol($pdo, 'paiement', 'approved');
$hasApprovedAt = hasCol($pdo, 'paiement', 'approved_at');
$hasApprovedBy = hasCol($pdo, 'paiement', 'approved_by');
if (!$hasMode || !$hasApproved) json_fail("Schéma paiement incomplet (mode/approved manquants).");

$id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';
if ($id<=0 || !in_array($action, ['approve','reject'], true)) json_fail('Paramètres invalides.');

// Charger le paiement
$st = $pdo->prepare("
SELECT p.*, s.class_id, s.id AS sid, c.niveau, c.section, c.options, c.id AS cid
FROM paiement p
LEFT JOIN students s ON s.id = p.eleve
LEFT JOIN classes  c ON c.id = s.class_id
WHERE p.id=:id
LIMIT 1
");
$st->execute([':id'=>$id]);
$pay = $st->fetch(PDO::FETCH_ASSOC);
if (!$pay) json_fail('Paiement introuvable.');
if (strcasecmp((string)$pay['mode'], 'Cash') !== 0) json_fail('Ce paiement ne relève pas du mode Cash.');
if ((int)$pay['approved'] === 1 && $action==='approve') json_fail('Déjà approuvé.');
if ((int)$pay['approved'] === -1 && $action==='reject') json_fail('Déjà rejeté.');

// (optionnel) restreindre à l’école de l’admin
if (!empty($code_ecole) && !empty($pay['code_ecole']) && $code_ecole !== $pay['code_ecole']) {
    json_fail('Vous ne pouvez pas modifier un paiement d’une autre école.');
}

// Fonction: calculer le montant dû pour un élève selon le type (Inscription|Minerval|Autre)
function computeDue(PDO $pdo, array $payRow): int {
    $niv = (int)($payRow['niveau'] ?? 0);
    $sec = (int)($payRow['section'] ?? 0);
    $opt = (int)($payRow['options'] ?? 0);
    $cls = (int)($payRow['cid'] ?? 0);
    $ce  = (string)($payRow['code_ecole'] ?? '');
    $type= (string)($payRow['statut'] ?? 'Inscription');

    try {
        if ($type === 'Inscription') {
            $st=$pdo->prepare("SELECT montant FROM frais_d_inscription
                               WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls LIMIT 1");
            $st->execute([':ce'=>$ce, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
            return (int)($st->fetchColumn() ?: 0);
        } elseif ($type === 'Minerval') {
            $st=$pdo->prepare("SELECT montant FROM minerval
                               WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls LIMIT 1");
            $st->execute([':ce'=>$ce, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
            return (int)($st->fetchColumn() ?: 0);
        } else { // Autre => somme des autres_frais
            $st=$pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM autres_frais
                               WHERE code_ecole=:ce AND niveau=:niv AND section=:sec AND `OPTION`=:opt AND classe=:cls");
            $st->execute([':ce'=>$ce, ':niv'=>$niv, ':sec'=>$sec, ':opt'=>$opt, ':cls'=>$cls]);
            return (int)$st->fetchColumn();
        }
    } catch(Throwable $e){ return 0; }
}

// Somme déjà approuvée avant traitement (hors ce paiement s’il est encore en attente)
$sumSql = "SELECT COALESCE(SUM(montant_paye),0) FROM paiement
           WHERE eleve=:sid AND statut=:st AND approved=1";
$sumParams = [':sid'=>(int)$pay['eleve'], ':st'=>$pay['statut']];
$st = $pdo->prepare($sumSql); $st->execute($sumParams);
$alreadyApproved = (float)$st->fetchColumn();

$due = computeDue($pdo, $pay);

// Exécuter action
try {
    if ($action === 'approve') {
        // Nouvelle somme approuvée après ajout de CE paiement
        $after = $alreadyApproved + (float)$pay['montant_paye'];
        $reste = max(0, $due - $after);

        // MAJ ligne
        if ($hasApprovedBy && $hasApprovedAt) {
            $st=$pdo->prepare("UPDATE paiement
                               SET approved=1, approved_at=NOW(), approved_by=:uid, solde=:solde
                               WHERE id=:id LIMIT 1");
            $st->execute([':uid'=>$userId, ':solde'=>$reste, ':id'=>$id]);
        } else {
            $st=$pdo->prepare("UPDATE paiement
                               SET approved=1, solde=:solde
                               WHERE id=:id LIMIT 1");
            $st->execute([':solde'=>$reste, ':id'=>$id]);
        }

        echo json_encode(['status'=>'ok','message'=>'Paiement approuvé','reste'=>$reste]);
        exit;

    } else { // reject
        if ($hasApprovedBy && $hasApprovedAt) {
            $st=$pdo->prepare("UPDATE paiement
                               SET approved=-1, approved_at=NOW(), approved_by=:uid
                               WHERE id=:id LIMIT 1");
            $st->execute([':uid'=>$userId, ':id'=>$id]);
        } else {
            $st=$pdo->prepare("UPDATE paiement
                               SET approved=-1
                               WHERE id=:id LIMIT 1");
            $st->execute([':id'=>$id]);
        }

        echo json_encode(['status'=>'ok','message'=>'Paiement rejeté']);
        exit;
    }
} catch(Throwable $e){
    http_response_code(200);
    echo json_encode(['status'=>'err','message'=>'Erreur: '.$e->getMessage()]);
    exit;
}
