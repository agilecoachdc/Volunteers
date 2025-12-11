<?php
// api/show_dispo.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/common_storage.php';

function safe_key(string $s){ return preg_replace('/[^a-z0-9_.@-]/i','_', $s); }

$email = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? '')));
if ($email === '') { echo json_encode(['ok'=>false,'error'=>'missing_email']); exit; }

$kvKey = 'avail:'.$email;

// 1) SQLite
$row = kv_get($kvKey);
if (is_array($row)) {
  // Deux formats possibles
  if (array_key_exists('avail', $row) && is_array($row['avail'])) {
    echo json_encode(['ok'=>true,'source'=>'sqlite','key'=>$kvKey,'dispo'=>$row['avail']], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
  }
  echo json_encode(['ok'=>true,'source'=>'sqlite','key'=>$kvKey,'dispo'=>$row], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}

// 2) Fichier (fallback)
$fn = __DIR__ . '/avail/' . safe_key($email) . '.json';
if (!file_exists($fn)) {
  echo json_encode(['ok'=>false,'error'=>'not_found','source_tried'=>['sqlite','file'],'sqlite_key'=>$kvKey,'file'=>$fn], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}
$txt = (string)file_get_contents($fn);
$data = json_decode($txt, true);
if (!is_array($data)) {
  echo json_encode(['ok'=>false,'error'=>'invalid_file_json','file'=>basename($fn),'raw'=>$txt], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}
if (array_key_exists('avail',$data) && is_array($data['avail'])) $data = $data['avail'];
echo json_encode(['ok'=>true,'source'=>'file','file'=>basename($fn),'dispo'=>$data], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
