<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);

$heats = [];
if (is_array($in) && isset($in['heats']) && is_array($in['heats'])) {
  $heats = $in['heats'];
} elseif (is_array($in) && array_is_list($in)) {
  $heats = $in;
} else {
  echo json_encode(['ok'=>false,'error'=>'invalid_payload'], JSON_UNESCAPED_UNICODE);
  exit;
}

// sécurité basique: s'assurer que 'lignes' est un tableau quand présent
foreach ($heats as &$h) {
  if (!isset($h['lignes']) || !is_array($h['lignes'])) $h['lignes'] = [];
}
unset($h);

@file_put_contents(__DIR__.'/_heats.json', json_encode($heats, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
