<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header_remove('X-Powered-By');

const DEBUG_VIEWER=false;

// PDO
$pdo=$pdo??null;
$C=[__DIR__.'/database/db_connect.php',__DIR__.'/database/config.php',__DIR__.'/../database/db_connect.php',__DIR__.'/../database/config.php'];
foreach($C as $p){ if(is_file($p)){ require_once $p; break; } }
if(!($pdo instanceof PDO)){ http_response_code(500); exit('DB'); }
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

// Session
$userId=(int)($_SESSION['user_id']??0);
$role=strtolower((string)($_SESSION['role']??''));
$ecole=(string)($_SESSION['code_ecole']??'');
if(!$userId||!$role||!$ecole){ http_response_code(401); exit('Non autorisé'); }

// Helpers
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function resolve_media_path(string $stored,string $kindDir): ?string{
  if($stored==='') return null;

  $baseUploadDir = realpath(__DIR__ . '/uploads');
  if (!$baseUploadDir) return null;

  // We only allow files within the uploads directory
  $c=[
    realpath(__DIR__.'/uploads/'.$stored),
    realpath(__DIR__.'/uploads/'.$kindDir.'/'.$stored),
  ];

  foreach($c as $f) {
    if($f && is_file($f) && strpos($f, $baseUploadDir) === 0) {
      return $f;
    }
  }
  return null;
}
function mime_from_ext($f){
  $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
  return match($ext){ 'png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','svg'=>'image/svg+xml', default=>'image/jpeg' };
}
function stream_inline($f,$mime,$name){
  if(!is_readable($f)){ http_response_code(404); exit('Fichier introuvable'); }
  header('Content-Type: '.$mime);
  header('Content-Length: '.filesize($f));
  header('Content-Disposition: inline; filename="'.rawurlencode($name?:basename($f)).'"');
  readfile($f); exit;
}

// Entrées
$id=(int)($_GET['id']??0); $raw=isset($_GET['raw']);
if($id<=0){ http_response_code(400); exit('id manquant'); }

try{
  $st=$pdo->prepare("SELECT id, title, filename, code_ecole FROM images WHERE id=:id AND code_ecole=:ec LIMIT 1");
  $st->execute([':id'=>$id,':ec'=>$ecole]);
  $img=$st->fetch(PDO::FETCH_ASSOC);
  if(!$img){ http_response_code(404); exit('Image introuvable'); }
  $file=resolve_media_path((string)$img['filename'], 'images');
  if(!$file){ http_response_code(404); exit('Fichier manquant'); }
  $name=$img['title'] ?: ('image-'.$img['id']);
  $mime=mime_from_ext($file);
  if($raw){ stream_inline($file,$mime,$name); }
} catch(Throwable $e){
  error_log("view_image error: ".$e->getMessage());
  http_response_code(500); exit(DEBUG_VIEWER?$e->getMessage():'Erreur interne');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?=h($img['title'] ?: 'Image')?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html,body{height:100%;}
body{margin:0;background:#f8fafc;}
.viewer{min-height:100vh;display:flex;flex-direction:column;}
.viewer-header{padding:.5rem 1rem;background:#fff;border-bottom:1px solid #e5e7eb;}
.viewer-body{flex:1 1 auto;display:grid;place-items:center;padding:16px;}
.viewer-body img{max-width:95vw;max-height:80vh;border-radius:8px;border:1px solid #e5e7eb;background:#fff;object-fit:contain}
</style>
</head>
<body>
<div class="viewer">
  <div class="viewer-header d-flex align-items-center justify-content-between">
    <div class="fw-semibold text-truncate"><?=h($img['title'] ?: 'Image')?></div>
    <div class="ms-auto">
      <a class="btn btn-sm btn-outline-secondary" href="?id=<?=$img['id']?>&raw=1" target="_blank">Télécharger</a>
    </div>
  </div>
  <div class="viewer-body">
    <img src="?id=<?=$img['id']?>&raw=1" alt="<?=h($img['title'] ?: 'Image')?>">
  </div>
</div>
</body>
</html>
