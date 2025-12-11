
<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$out = ['ok'=>true];
try {
  $pdoOk = extension_loaded('pdo');
  $sqliteOk = extension_loaded('pdo_sqlite');
  $baseDir = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
  $dataDir = $baseDir . '/data';
  $out['php'] = PHP_VERSION;
  $out['pdo'] = $pdoOk;
  $out['pdo_sqlite'] = $sqliteOk;
  $out['data_dir'] = $dataDir;
  $out['data_dir_exists'] = is_dir($dataDir);
  $out['data_dir_is_writable'] = is_writable($dataDir) ?: false;
  if (!$out['data_dir_exists']) { @mkdir($dataDir, 0755, true); $out['data_dir_created'] = is_dir($dataDir); $out['data_dir_is_writable'] = is_writable($dataDir) ?: false; }

  $db = $dataDir . '/health_test.sqlite';
  if ($sqliteOk) {
    $pdo = new PDO("sqlite:$db", null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS t (i INTEGER); INSERT INTO t(i) VALUES (1);");
    $out['sqlite_write_ok'] = true;
    @unlink($db);
  } else {
    $out['sqlite_write_ok'] = false;
  }
} catch (Throwable $e) {
  $out['ok'] = false;
  $out['error'] = 'health_check_failed';
  $out['debug'] = $e->getMessage();
}
echo json_encode($out);
