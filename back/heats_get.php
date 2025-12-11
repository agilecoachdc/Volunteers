<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fn = __DIR__.'/_heats.json';
$heats = [];

if (file_exists($fn)) {
  $j = json_decode(@file_get_contents($fn), true);
  if (is_array($j)) {
    // tolère un tableau direct ou un objet {heats:[...]}
    if (isset($j['heats']) && is_array($j['heats'])) $heats = $j['heats'];
    elseif (array_is_list($j)) $heats = $j;
  }
}

echo json_encode(['heats'=>$heats], JSON_UNESCAPED_UNICODE);
