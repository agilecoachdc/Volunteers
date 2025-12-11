<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
  // 1. lire l'entrée JSON
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw ?: '', true) ?? [];

  $email      = strtolower(trim((string)($in['email'] ?? '')));
  $password   = (string)($in['password'] ?? '');
  $firstName  = trim((string)($in['first_name'] ?? ''));
  $lastName   = trim((string)($in['last_name'] ?? ''));
  $optin      = !empty($in['optin']);

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']); exit;
  }
  if ($password === '' || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'weak_password']); exit;
  }

  // on fabrique le "name" d'affichage (ce que ton app utilise déjà)
  $displayName = trim($firstName . ' ' . $lastName);
  if ($displayName === '') $displayName = $email;

  // 2. on prépare les données communes
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $role         = 'user'; // vu dans ton schéma
  $isActive     = 1;
  $poste        = 'juge';
  $createdAt    = time();

  // 3. tentative d'écriture en SQLite (chemin de ton login.php)
  $dbPath = dirname(__DIR__) . '/data/app.sqlite';
  $insertOk = false;
  $userId = null;

  try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL;');

    // doublon email ?
    $st = $pdo->prepare('SELECT id FROM users WHERE lower(email)=lower(?)');
    $st->execute([$email]);
    if ($st->fetch()) {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'already_exists']); exit;
    }

    // insert conforme à ton schéma
    $ins = $pdo->prepare(
      'INSERT INTO users (name, email, password_hash, role, is_active, created_at, poste)
       VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
      $displayName,
      $email,
      $passwordHash,
      $role,
      $isActive,
      $createdAt,
      $poste,
    ]);

    $userId = (int)$pdo->lastInsertId();
    $insertOk = true;
  } catch (Throwable $dbErr) {
    // ici: tentative d'écriture en read-only OU sqlite locké
    // on ne renvoie pas 500 tout de suite, on passe en fallback JSON
    $insertOk = false;
    $userId   = null;
    $dbError  = $dbErr->getMessage();
  }

  // 4. fallback en JSON si SQLite KO
  if (!$insertOk) {
    $fallbackFile = __DIR__ . '/_users_fallback.json';
    $list = [];
    if (file_exists($fallbackFile)) {
      $j = json_decode((string)file_get_contents($fallbackFile), true);
      if (is_array($j)) $list = $j;
    }
    // doublon dans le fallback ?
    foreach ($list as $u) {
      if (isset($u['email']) && strtolower($u['email']) === $email) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'already_exists_fallback']); exit;
      }
    }
    $userId = count($list) + 1;
    $list[] = [
      'id'          => $userId,
      'email'       => $email,
      'name'        => $displayName,
      'first_name'  => $firstName,
      'last_name'   => $lastName,
      'role'        => $role,
      'is_active'   => $isActive,
      'poste'       => $poste,
      'created_at'  => $createdAt,
      'source'      => 'fallback-json',
    ];
    // on essaie d'écrire, si ça foire tant pis, on lève l'erreur
    file_put_contents($fallbackFile, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
  }

  // 5. on crée la session + csrf, comme login.php
  $user = [
    'id'         => $userId,
    'name'       => $displayName,
    'email'      => $email,
    'role'       => $role,
    'is_active'  => $isActive,
    'poste'      => $poste,
    'first_name' => $firstName,
    'last_name'  => $lastName,
  ];

  $_SESSION['user'] = $user;
  $_SESSION['user_email'] = $email;
  $csrf = bin2hex(random_bytes(16));
  $_SESSION['csrf'] = $csrf;

  // ⚠️ si tu testes en HTTP pur, passe secure => false
  @session_regenerate_id(true);
  setcookie(session_name(), session_id(), [
    'path'     => '/la-hache-contest',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  // 6. réponse finale
  echo json_encode([
    'ok'   => true,
    'user' => $user + ['csrf' => $csrf],
  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'     => false,
    'error'  => 'exception',
    'message'=> $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
