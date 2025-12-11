<?php declare(strict_types=1);
require __DIR__.'/config.php';
try {
  require_csrf(); $u=current_user();
  $in=json_decode(file_get_contents('php://input')?:'', true)??[];
  $email=strtolower(trim((string)array_get($in,'email', $u['email'] ?? '')));
  $role=(string)array_get($in,'role','both');
  if(!$email) json_fail('auth_required',401);
  if(!in_array($role,['juge','build','both'], true)) json_fail('invalid_role',400);
  $pdo=get_pdo(); kv_set($pdo,'pref:'.$email,['role'=>$role]);
  json_ok(['ok'=>true]);
} catch (Throwable $e){ json_fail('exception',500,['message'=>$e->getMessage()]); }
