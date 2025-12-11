<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fn = __DIR__ . '/teams.json';
$payload = ['teams' => []];

if (file_exists($fn)) {
  $j = json_decode(@file_get_contents($fn), true);
  if (is_array($j)) {
    // tolère soit {teams:[...]} soit [...] directement
    if (isset($j['teams']) && is_array($j['teams'])) $payload['teams'] = $j['teams'];
    elseif (array_is_list($j)) $payload['teams'] = $j;
  }
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
