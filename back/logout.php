<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$_SESSION = [];
unset($_SESSION['user'], $_SESSION['user_email'], $_SESSION['csrf']);
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, [
    'path'     => '/la-hache-contest',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
@session_destroy();

echo json_encode(['ok'=>true]);
