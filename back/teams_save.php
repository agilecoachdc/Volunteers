<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function norm_cat(string $s): string {
  $sl = mb_strtolower(trim($s), 'UTF-8');
  if ($sl === '' || $sl === '—' || $sl === '-') return '';
  if (in_array($sl, ['régular','regular','regulat','reg'], true)) return 'Régular';
  if (in_array($sl, ['inter','intermediaire','intermédiaire'], true)) return 'Inter';
  if ($sl === 'rx' || $sl === 'r x') return 'RX';
  if (in_array($s, ['Régular','Inter','RX'], true)) return $s;
  return '';
}

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);

$teams = [];
if (is_array($in) && isset($in['teams']) && is_array($in['teams'])) {
  $teams = $in['teams'];
} elseif (is_array($in) && array_is_list($in)) {
  $teams = $in;
} else {
  echo json_encode(['ok'=>false,'error'=>'invalid_payload'], JSON_UNESCAPED_UNICODE);
  exit;
}

// normalisation minimale: id/name/cat
$out = [];
foreach ($teams as $t) {
  $id   = (string)($t['id'] ?? '');
  $name = trim((string)($t['name'] ?? ''));
  $cat  = norm_cat((string)($t['cat'] ?? ''));
  if ($name === '') continue;
  if ($id === '') $id = bin2hex(random_bytes(8));
  $out[] = ['id'=>$id, 'name'=>$name, 'cat'=>$cat];
}

@file_put_contents(__DIR__.'/teams.json', json_encode(['teams'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
