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
  return match($ext){ 'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','m4a'=>'audio/mp4', default=>'audio/mpeg' };
}
function stream_range($file,$mime,$name){
  if(!is_readable($file)){ http_response_code(404); exit('Fichier introuvable'); }
  $size=filesize($file); $fp=fopen($file,'rb'); if(!$fp){ http_response_code(500); exit('Lecture impossible'); }
  header('Content-Type: '.$mime);
  header('Accept-Ranges: bytes');
  header('Content-Disposition: inline; filename="'.rawurlencode($name ?: basename($file)).'"');
  $start=0; $end=$size-1;
  if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/i',$_SERVER['HTTP_RANGE'],$m)){
    $start=(int)$m[1]; if($m[2]!=='') $end=(int)$m[2];
    if($start>$end||$start>=$size){ header('Content-Range: bytes */'.$size); http_response_code(416); exit; }
    $len=$end-$start+1;
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $len");
    fseek($fp,$start);
    $left=$len;
    while($left>0 && !feof($fp)){ $chunk=fread($fp,min(8192,$left)); if($chunk===false) break; echo $chunk; $left-=strlen($chunk); @ob_flush(); flush(); }
    fclose($fp); exit;
  } else {
    header("Content-Length: $size"); fpassthru($fp); fclose($fp); exit;
  }
}

// Entrées
$id=(int)($_GET['id']??0); $raw=isset($_GET['raw']);
if($id<=0){ http_response_code(400); exit('id manquant'); }

try{
  // audios: colonnes 'titre' et 'fichier'
  $st=$pdo->prepare("SELECT id, titre, fichier, code_ecole FROM audios WHERE id=:id AND code_ecole=:ec LIMIT 1");
  $st->execute([':id'=>$id,':ec'=>$ecole]);
  $a=$st->fetch(PDO::FETCH_ASSOC);
  if(!$a){ http_response_code(404); exit('Audio introuvable'); }
  $file=resolve_media_path((string)$a['fichier'], 'audios');
  if(!$file){ http_response_code(404); exit('Fichier manquant'); }
  $name = $a['titre'] ?: ('audio-'.$a['id'].'.mp3');
  $mime = mime_from_ext($file);
  if($raw){ stream_range($file,$mime,$name); }
} catch(Throwable $e){
  error_log("view_audio error: ".$e->getMessage());
  http_response_code(500); exit(DEBUG_VIEWER?$e->getMessage():'Erreur interne');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?=h($a['titre'] ?: 'Audio')?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html,body{height:100%;}
body{margin:0;background:#f8fafc;display:grid;place-items:center;}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);padding:16px;max-width:720px;width:92%;}
</style>
</head>
<body>
  <div class="card">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-semibold text-truncate me-2"><?=h($a['titre'] ?: 'Audio')?></div>
      <a class="btn btn-sm btn-outline-secondary" href="?id=<?=$a['id']?>&raw=1">Télécharger</a>
    </div>
    <audio controls style="width:100%">
      <source src="?id=<?=$a['id']?>&raw=1" type="<?=h($mime)?>">
      Votre navigateur ne supporte pas l’audio HTML5.
    </audio>
  </div>
</body>
</html>
