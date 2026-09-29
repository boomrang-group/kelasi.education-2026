<?php
// customs/teacher/_teacher_active_class.php
// Helper centralisé pour gérer la "classe active" d'un enseignant.
// - Déduit les classes affectées via class_subject_teacher
// - Valide/normalise $_SESSION['active_class_id']
// - Fournit un contexte unique : active_class_id, fiche classe, liste des affectations
//
// Utilisation rapide dans une page prof :
// require_once __DIR__.'/_teacher_active_class.php';
// $ctx = teacher_active_context($pdo);
// $activeClassId = $ctx['active_class_id'];  // null si aucune
// $classRow      = $ctx['class'];            // array|NULL (classes.* jointures)
// $assigned      = $ctx['assigned'];         // array[] des classes affectées
//
// Pour forcer une classe active (ex. après POST):
// if (teacher_set_active_class($pdo, (int)$cid)) { /* ok */ } else { /* pas autorisé */ }

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Assure la connexion $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $tried = [
        __DIR__.'/../../database/db_connect.php',
        __DIR__.'/../../../database/db_connect.php',
        __DIR__.'/../database/db_connect.php',
    ];
    foreach ($tried as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }
}

// Sécurité minimale (rôle prof)
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'prof') {
    header('Location: ../../login/index.php?msg=forbidden'); exit;
}

// Contexte user
$_TA_user_id  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; // users.id
$_TA_username = $_SESSION['username']   ?? null;
$_TA_email    = $_SESSION['email']      ?? null;
$_TA_code_ec  = $_SESSION['code_ecole'] ?? null;

// ========= Helpers internes =========
function _ta_build_where(PDO $pdo): array {
    $userId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $email   = $_SESSION['email'] ?? null;
    $uname   = $_SESSION['username'] ?? null;

    $where  = [];
    $params = [];

    $candidateIds = [];
    if ($userId) $candidateIds[] = (int)$userId;

    if ($email) {
        $st = $pdo->prepare("SELECT id FROM teacher WHERE email=:em LIMIT 1");
        $st->execute([':em'=>$email]);
        if ($tid = $st->fetchColumn()) $candidateIds[] = (int)$tid;
    }
    if ($candidateIds) {
        $in = [];
        foreach ($candidateIds as $i=>$val) {
            $ph = ':tid'.$i;
            $in[] = $ph; $params[$ph] = $val;
        }
        $where[] = 'cst.teacher_user_id IN ('.implode(',', $in).')';
    }
    if (!empty($uname)) {
        $where[] = 'cst.username = :uname';
        $params[':uname'] = $uname;
    }
    return [$where, $params];
}

function _ta_list_assigned_classes(PDO $pdo): array {
    [$where, $params] = _ta_build_where($pdo);
    if (!$where) return [];

    $sql = "
        SELECT c.id, c.classe, c.description,
               n.description AS niveau, s.description AS section, o.description AS options,
               cst.created_at
          FROM class_subject_teacher cst
          JOIN classes c ON c.id = cst.class_id
          LEFT JOIN niveau  n ON c.niveau  = n.id
          LEFT JOIN section s ON c.section = s.id
          LEFT JOIN options o ON c.options = o.id
         WHERE (".implode(' OR ', $where).")
    ";
    if (!empty($_SESSION['code_ecole'])) {
        $sql .= " AND cst.code_ecole = :ce";
        $params[':ce'] = $_SESSION['code_ecole'];
    }
    $sql .= " ORDER BY n.description, s.description, o.description, c.classe, c.description";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function _ta_class_belongs_to_teacher(PDO $pdo, int $classId): bool {
    [$where, $params] = _ta_build_where($pdo);
    if (!$where) return false;

    $sql = "SELECT 1 FROM class_subject_teacher cst WHERE cst.class_id=:cid AND (".implode(' OR ', $where).")";
    $params[':cid'] = $classId;
    if (!empty($_SESSION['code_ecole'])) {
        $sql .= " AND cst.code_ecole=:ce";
        $params[':ce'] = $_SESSION['code_ecole'];
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

function _ta_find_default_class_id(PDO $pdo): ?int {
    [$where, $params] = _ta_build_where($pdo);
    if (!$where) return null;

    if (!empty($_SESSION['code_ecole'])) {
        $sql = "SELECT cst.class_id
                  FROM class_subject_teacher cst
                 WHERE (".implode(' OR ', $where).") AND cst.code_ecole=:ce
              ORDER BY cst.id DESC LIMIT 1";
        $p = $params; $p[':ce'] = $_SESSION['code_ecole'];
        $st = $pdo->prepare($sql); $st->execute($p);
        if ($cid = $st->fetchColumn()) return (int)$cid;
    }

    $sql2 = "SELECT cst.class_id
               FROM class_subject_teacher cst
              WHERE (".implode(' OR ', $where).")
           ORDER BY cst.id DESC LIMIT 1";
    $st2 = $pdo->prepare($sql2); $st2->execute($params);
    $cid = $st2->fetchColumn();
    return $cid ? (int)$cid : null;
}

function _ta_load_class(PDO $pdo, int $classId): ?array {
    $st = $pdo->prepare("
        SELECT c.id, c.classe, c.description,
               n.description AS niveau, s.description AS section, o.description AS options
          FROM classes c
          LEFT JOIN niveau  n ON c.niveau  = n.id
          LEFT JOIN section s ON c.section = s.id
          LEFT JOIN options  o ON c.options = o.id
         WHERE c.id = :cid
    ");
    $st->execute([':cid'=>$classId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ========= API publique =========

/**
 * Construit et retourne le contexte "classe active" du prof.
 * - Valide $_SESSION['active_class_id'] s’il existe
 * - Sinon retrouve une classe par défaut
 * - Retourne: ['active_class_id'=>?int, 'class'=>?array, 'assigned'=>array]
 */
function teacher_active_context(PDO $pdo): array {
    $assigned = _ta_list_assigned_classes($pdo);

    $active = isset($_SESSION['active_class_id']) ? (int)$_SESSION['active_class_id'] : 0;
    if ($active>0 && !_ta_class_belongs_to_teacher($pdo, $active)) {
        unset($_SESSION['active_class_id']); $active = 0;
    }

    if ($active<=0) {
        $def = _ta_find_default_class_id($pdo);
        if ($def) {
            $_SESSION['active_class_id'] = $def;
            $active = $def;
        }
    }

    $classRow = $active ? _ta_load_class($pdo, $active) : null;

    return [
        'active_class_id' => $active ?: null,
        'class'           => $classRow,
        'assigned'        => $assigned,
    ];
}

/**
 * Valide et fixe une classe active (retourne true si OK).
 * À utiliser après un formulaire de bascule par ex.
 */
function teacher_set_active_class(PDO $pdo, int $classId): bool {
    if ($classId<=0) return false;
    if (!_ta_class_belongs_to_teacher($pdo, $classId)) return false;
    $_SESSION['active_class_id'] = $classId;
    return true;
}

/**
 * Utilitaire: renvoie la clause "AND code_ecole=:ce" + param si besoin.
 * Pratique pour vos requêtes afin d'appliquer le scope école.
 */
function teacher_school_scope_sql(string $alias = ''): array {
    $alias = $alias ? rtrim($alias, '.').'.' : '';
    if (!empty($_SESSION['code_ecole'])) {
        return [" AND {$alias}code_ecole = :ce ", [':ce'=>$_SESSION['code_ecole']]];
    }
    return ['', []];
}
