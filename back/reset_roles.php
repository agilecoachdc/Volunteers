<?php
// api/reset_roles.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/common_storage.php';

function read_json(string $fn, $fallback) {
  if (!file_exists($fn)) return $fallback;
  $j = json_decode(@file_get_contents($fn), true);
  return is_array($j) ? $j : $fallback;
}
function write_json(string $fn, $data): void {
  @file_put_contents($fn, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

try {
  $heats = read_json(__DIR__.'/_heats.json', []);
  if (!is_array($heats)) $heats = [];

  foreach ($heats as &$h) {
    if (!isset($h['lignes']) || !is_array($h['lignes'])) $h['lignes'] = [];
    foreach ($h['lignes'] as &$ln) {
      $ln['juge']  = '';
      $ln['build'] = '';
    }
    unset($ln);
  }
  unset($h);

  write_json(__DIR__.'/_heats.json', $heats);
  echo json_encode(['ok'=>true, 'heats'=>$heats], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
