<?php
// api/users_update.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  $db = dirname(__DIR__) . '/data/app.sqlite';
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA journal_mode=WAL;');

  // colonnes sûres
  try { $pdo->exec("ALTER TABLE users ADD COLUMN poste TEXT"); } catch(Throwable $e) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT"); } catch(Throwable $e) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN is_active INTEGER"); } catch(Throwable $e) {}

  $raw = json_decode(file_get_contents('php://input'), true) ?: [];
  $email = strtolower(trim((string)($raw['email'] ?? '')));

  if ($email==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'email_required']); exit; }

  $fields = [];
  $params = [':email'=>$email];

  if (isset($raw['poste'])) {
    $poste = strtolower(trim((string)$raw['poste']));
    if ($poste!=='' && !in_array($poste,['juge','build','staff'],true)) { $poste=null; }
    $fields[] = 'poste = :poste'; $params[':poste'] = $poste;
  }
  if (isset($raw['role'])) {
    $role = strtolower(trim((string)$raw['role']));
    if ($role!=='' && !in_array($role,['admin','benevole'],true)) $role='benevole';
    $fields[] = 'role = :role'; $params[':role'] = $role;
  }
  if (array_key_exists('is_active',$raw)) {
    $ia = (int)((bool)$raw['is_active'] ? 1 : 0);
    $fields[] = 'is_active = :ia'; $params[':ia'] = $ia;
  }

  if (!$fields) { echo json_encode(['ok'=>true,'updated'=>0]); exit; }

  $sql = 'UPDATE users SET '.implode(', ',$fields).' WHERE lower(email)=:email';
  $st = $pdo->prepare($sql); $st->execute($params);

  echo json_encode(['ok'=>true,'updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','message'=>$e->getMessage()]);
}
