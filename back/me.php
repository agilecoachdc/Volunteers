<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors','0'); error_reporting(0);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
  $user = $_SESSION['user'] ?? null;

  // Rafraîchir rôle/is_active depuis la DB si possible (sans casser la réponse)
  if (is_array($user) && !empty($user['email'])) {
    try {
      $db = dirname(__DIR__).'/data/app.sqlite';
      $pdo = new PDO('sqlite:'.$db, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $st = $pdo->prepare('SELECT role,is_active FROM users WHERE lower(email)=lower(?)');
      $st->execute([strtolower($user['email'])]);
      if ($row = $st->fetch()) {
        $user['role'] = $row['role'];
        $user['is_active'] = (int)$row['is_active'];
        $_SESSION['user'] = $user;
      }
    } catch (Throwable $ignored) {}
  }

  $csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
  $_SESSION['csrf'] = $csrf;

  echo json_encode(['auth'=>(bool)$user,'user'=>$user,'csrf'=>$csrf], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(['auth'=>false,'user'=>null,'csrf'=>bin2hex(random_bytes(8)),'warn'=>'me_exception'], JSON_UNESCAPED_UNICODE);
}
