<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
  $db = dirname(__DIR__) . '/data/app.sqlite';
  if (!file_exists($db)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_not_found', 'path' => $db]);
    exit;
  }

  $pdo = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // lecture seule / journal WAL
  $pdo->exec('PRAGMA journal_mode=WAL;');

  // ⛔ on ne fait PLUS d'ALTER TABLE ici (ça peut faire planter en prod)
  // $pdo->exec("ALTER TABLE users ADD COLUMN poste TEXT");

  $sql = 'SELECT id, name, email, role, is_active, poste FROM users ORDER BY email';
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll();

  foreach ($rows as &$r) {
    $r['is_active'] = (int)($r['is_active'] ?? 0);
    if (!isset($r['poste']) || $r['poste'] === '') {
      $r['poste'] = null;
    }
  }
  unset($r);

  echo json_encode(['ok' => true, 'users' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'exception',
    'message' => $e->getMessage(),   // 👈 on l’envoie maintenant
  ], JSON_UNESCAPED_UNICODE);
}
