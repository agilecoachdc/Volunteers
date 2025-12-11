<?php
// api/show_all_dispos.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/common_storage.php';

$out = [
  'ok' => true,
  'source' => 'sqlite+files',
  'sqlite_count' => 0,
  'file_count' => 0,
  'avail_sqlite' => [],   // k => (array|raw string)
  'avail_files' => [],    // filename => (array|raw string)
];

try {
  // --- Depuis SQLite (kv_store: keys 'avail:<email>')
  $st = db()->query("SELECT k, v FROM kv_store WHERE k LIKE 'avail:%' ORDER BY k");
  while ($row = $st->fetch()) {
    $k = (string)$row['k'];
    $v = (string)$row['v'];
    $decoded = json_decode($v, true);
    // loadAvail() gère 2 formats: soit { "samedi 09:00":"dispo", ... }, soit { "avail": { ... } }
    $out['avail_sqlite'][$k] = is_array($decoded) ? $decoded : $v;
    $out['sqlite_count']++;
  }

  // --- Fichiers (fallback historique)
  $dir = __DIR__.'/avail';
  if (is_dir($dir)) {
    foreach (glob($dir.'/*.json') as $fn) {
      $txt = (string)@file_get_contents($fn);
      $decoded = json_decode($txt, true);
      $out['avail_files'][basename($fn)] = is_array($decoded) ? $decoded : $txt;
      $out['file_count']++;
    }
  }
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'sqlite_error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
