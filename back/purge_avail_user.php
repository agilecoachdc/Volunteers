<?php
// api/purge_avail_user.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/common_storage.php'; // loadAvail(), saveAvail()

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = $method === 'POST'
  ? (json_decode(file_get_contents('php://input'), true) ?: [])
  : $_GET;

$email = trim(strtolower((string)($input['email'] ?? '')));
$day   = trim(strtolower((string)($input['day']   ?? ''))); // 'samedi'|'dimanche'|''

if ($email === '') {
  echo json_encode(['ok'=>false, 'error'=>'Paramètre email manquant'], JSON_UNESCAPED_UNICODE);
  exit;
}

$cur = loadAvail($email) ?: [];
$cur = is_array($cur) ? $cur : [];

if ($day === '') {
  // purge TOTALE
  $new = [];
} else {
  // purge seulement le jour demandé
  $new = [];
  foreach ($cur as $k => $v) {
    $kk = strtolower(trim((string)$k));
    if (strpos($kk, $day.' ') === 0) {
      // drop
    } else {
      $new[$k] = $v;
    }
  }
}

saveAvail([], $email);     // on nettoie totalement la fiche
saveAvail($new, $email);   // puis on réécrit la version purgée

echo json_encode(['ok'=>true, 'email'=>$email, 'day'=>$day?:null, 'kept'=>count($new)], JSON_UNESCAPED_UNICODE);
