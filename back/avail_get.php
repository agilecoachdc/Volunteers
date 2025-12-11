<?php declare(strict_types=1);
require __DIR__.'/config.php';
try {
  $u=current_user(); $email=strtolower(trim((string)($_GET['email'] ?? ($u['email'] ?? ''))));
  if(!$email) json_fail('auth_required',401);
  $pdo=get_pdo(); $avail=kv_get($pdo,'avail:'.$email,[]); $updated=kv_get($pdo,'avail_updated:'.$email,null);
  json_ok(['email'=>$email,'avail'=>$avail,'updated_at'=>$updated]);
} catch (Throwable $e){ json_fail('exception',500,['message'=>$e->getMessage()]); }
