<?php
// api/repartir_heats.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/assign_engine.php';

try {
  $heats = engine_load_heats();
  engine_assign_all($heats);
  engine_save_heats($heats);
  echo json_encode(['ok'=>true,'heats'=>$heats], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','message'=>$e->getMessage()]);
}
