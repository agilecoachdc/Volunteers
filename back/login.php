<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(0);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw ?: '', true) ?? [];
  $email = strtolower(trim((string)($in['email'] ?? '')));
  $pass  = (string)($in['password'] ?? '');

  if (!$email || !$pass || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit;
  }

  $db = dirname(__DIR__).'/data/app.sqlite';
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA journal_mode=WAL;');

  $st = $pdo->prepare('SELECT id,name,email,password_hash,role,is_active FROM users WHERE lower(email)=lower(?)');
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u || !(int)$u['is_active']) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_credentials']); exit; }
  if (!password_verify($pass, (string)$u['password_hash'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_credentials']); exit; }

  $user = [
    'id'        => $u['id'],
    'name'      => $u['name'],
    'email'     => $u['email'],
    'role'      => $u['role'],
    'is_active' => (int)$u['is_active'],
  ];

  // --- SESSION ---
  $_SESSION['user'] = $user;
  $_SESSION['user_email'] = strtolower((string)$user['email']); // <== ajouté (compat avail_save.php)
  $csrf = bin2hex(random_bytes(16));
  $_SESSION['csrf'] = $csrf;

  // Important: cookie de session sur le bon path (app sous /la-hache-contest)
  @session_regenerate_id(true);
  setcookie(session_name(), session_id(), [
    'path'     => '/la-hache-contest',
    'secure'   => true,      // passe à false si tu testes en HTTP
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  echo json_encode(['ok'=>true, 'user'=>$user + ['csrf'=>$csrf]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception'], JSON_UNESCAPED_UNICODE);
}
