<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(0);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
  $in = json_decode(file_get_contents('php://input')?:'', true) ?? [];
  $email = strtolower(trim((string)($in['email'] ?? '')));
  if (!$email) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit; }

  $db = dirname(__DIR__).'/data/app.sqlite';
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $pdo->prepare('DELETE FROM users WHERE lower(email)=lower(?)')->execute([$email]);
  $pdo->prepare('DELETE FROM kv_store WHERE k IN (?, ?, ?)')->execute(["avail:$email","avail_updated:$email","pref:$email"]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'exception']);
}
