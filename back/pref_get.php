<?php declare(strict_types=1);
require __DIR__.'/config.php';
try {
  $u=current_user(); $email=strtolower(trim((string)($_GET['email'] ?? ($u['email'] ?? ''))));
  if(!$email) json_fail('auth_required',401);
  $pdo=get_pdo(); $pref=kv_get($pdo,'pref:'.$email,['role'=>'both']);
  json_ok(['pref'=>$pref]);
} catch (Throwable $e){ json_fail('exception',500,['message'=>$e->getMessage()]); }
