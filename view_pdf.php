<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header_remove('X-Powered-By');

const DEBUG_VIEWER = false; // passe à true en dev pour afficher l'erreur exacte

// ---------- PDO ----------
$pdo = $pdo ?? null;
$CANDIDATES = [
  __DIR__.'/database/db_connect.php',
  __DIR__.'/database/config.php',
  __DIR__.'/../database/db_connect.php',
  __DIR__.'/../database/config.php',
];
foreach ($CANDIDATES as $p) { if (is_file($p)) { require_once $p; break; } }
if (!($pdo instanceof PDO)) { http_response_code(500); exit("Erreur serveur: DB"); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------- Session ----------
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = strtolower((string)($_SESSION['role'] ?? ''));
$ecole  = (string)($_SESSION['code_ecole'] ?? '');
if (!$userId || !$role || !$ecole) { http_response_code(401); exit("Non autorisé"); }

// ---------- Helpers ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
/** Résout un chemin pour un PDF avec sécurité */
function resolve_media_path(string $stored, string $kindDir): ?string {
  if ($stored === '') return null;

  $baseUploadDir = realpath(__DIR__ . '/uploads');
  if (!$baseUploadDir) return null;

  // On n'autorise que les fichiers à l'intérieur du dossier uploads
  $cands = [
    realpath(__DIR__ . '/uploads/' . $stored),
    realpath(__DIR__ . '/uploads/'.$kindDir.'/' . $stored),
  ];

  foreach ($cands as $f) {
    if ($f && is_file($f) && strpos($f, $baseUploadDir) === 0) {
      return $f;
    }
  }

  return null;
}
function stream_file(string $file, string $name): void {
  if (!is_readable($file)) { http_response_code(404); exit('Fichier introuvable'); }
  header('Content-Type: application/pdf');
  header('Content-Length: '.filesize($file));
  header('Content-Disposition: inline; filename="'.rawurlencode($name ?: basename($file)).'"');
  readfile($file); exit;
}

// ---------- Entrées ----------
$id  = (int)($_GET['id'] ?? 0);
$raw = isset($_GET['raw']);
if ($id <= 0) { http_response_code(400); exit('id manquant'); }

try {
  $st = $pdo->prepare("SELECT id, title, filename, code_ecole FROM pdfs WHERE id=:id AND code_ecole=:ec LIMIT 1");
  $st->execute([':id'=>$id, ':ec'=>$ecole]);
  $pdf = $st->fetch(PDO::FETCH_ASSOC);
  if (!$pdf) { http_response_code(404); exit('PDF introuvable'); }

  $file = resolve_media_path((string)$pdf['filename'], 'pdfs');
  if (!$file) { http_response_code(404); exit('Fichier manquant'); }

  $name = ($pdf['title'] ?: ('document-'.$pdf['id'].'.pdf'));
  if ($raw) { stream_file($file, $name); }

} catch (Throwable $e) {
  error_log("view_pdf error: ".$e->getMessage());
  http_response_code(500);
  exit(DEBUG_VIEWER ? $e->getMessage() : 'Erreur interne');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?=h($pdf['title'] ?: 'Document PDF')?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html,body{height:100%;}
body{margin:0;background:#f8fafc;}
.viewer{height:100vh;display:flex;flex-direction:column;}
.viewer-header{padding:.5rem 1rem;background:#fff;border-bottom:1px solid #e5e7eb;}
.viewer-body{flex:1 1 auto;}
.viewer-body iframe{width:100%;height:100%;border:0;background:#fff;}
</style>
</head>
<body>
<div class="viewer">
  <div class="viewer-header d-flex align-items-center justify-content-between">
    <div class="fw-semibold text-truncate"><?=h($pdf['title'] ?: 'PDF')?></div>
    <div class="ms-auto">
      <a class="btn btn-sm btn-outline-secondary" href="?id=<?=$pdf['id']?>&raw=1" target="_blank">Télécharger</a>
    </div>
  </div>
  <div class="viewer-body">
    <iframe src="?id=<?=$pdf['id']?>&raw=1"></iframe>
  </div>
</div>
</body>
</html>
