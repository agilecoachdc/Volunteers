<?php
// api/diag_repartir.php
declare(strict_types=1);
@ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__;
$files = [
  'config.php'          => $root.'/config.php',
  'common_storage.php'  => $root.'/common_storage.php',
  'assign_engine.php'   => $root.'/assign_engine.php',
  '_heats.json'         => $root.'/_heats.json',
  'data/app.sqlite'     => dirname($root).'/data/app.sqlite',
];

$probe = [];
foreach ($files as $k => $path) {
  $info = [
    'path'        => $path,
    'exists'      => file_exists($path),
    'readable'    => is_readable($path),
    'writable'    => is_writable($path),
    'size'        => null,
    'type'        => null,
  ];
  if ($info['exists']) {
    $info['type'] = is_dir($path) ? 'dir' : 'file';
    if ($info['type'] === 'file') {
      $info['size'] = @filesize($path);
    }
  }
  $probe[$k] = $info;
}

// Essaie de lire _heats.json si présent
$heatsPreview = null; $heatsErr = null;
if ($probe['_heats.json']['exists'] && $probe['_heats.json']['readable'] && !$probe['_heats.json']['type'] === 'dir') {
  try {
    $raw = @file_get_contents($files['_heats.json']);
    $j = json_decode($raw ?? 'null', true);
    $heatsPreview = is_array($j) ? array_slice($j, 0, 2) : $j;
  } catch (Throwable $e) {
    $heatsErr = $e->getMessage();
  }
}

// Résumé « bloquants »
$blocking = [];
foreach (['config.php','common_storage.php','assign_engine.php'] as $req) {
  if (!$probe[$req]['exists'] || !$probe[$req]['readable']) $blocking[] = $req;
}

echo json_encode([
  'ok' => empty($blocking),
  'blocking_missing_or_unreadable' => $blocking,
  'files' => $probe,
  'heats_preview' => $heatsPreview,
  'heats_error' => $heatsErr,
  'hints' => [
    'assign_engine.php manquant ou illisible' => 'Crée/replace api/assign_engine.php (moteur commun) et assure-toi des bons droits.',
    '_heats.json absent' => 'Laisse repartir_team.php ou un save initial le (re)créer, droits d’écriture sur /api/.',
    'config/common_storage' => 'Ces deux fichiers doivent définir get_pdo(), current_user(), kv_get/kv_set, normalize_slot(), keyNorm/keyAlt, etc.',
  ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
