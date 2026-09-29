<?php
// service/notify_seen.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// On marque "vu maintenant" → les prochains décomptes ne compteront plus l’historique
$_SESSION['admin_notify_since'] = (new DateTime())->format('Y-m-d H:i:s');

echo json_encode(['status'=>'ok','since'=>$_SESSION['admin_notify_since']]);
