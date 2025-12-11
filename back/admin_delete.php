<?php declare(strict_types=1); require __DIR__.'/config.php';
$u=current_user(); if(!$u || ($u['role']??'')!=='admin') json_fail('forbidden',403); require_csrf();
$in=json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$email=strtolower(trim((string)($in['email']??'')));
if(!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) json_fail('invalid_input',400);
if($email===strtolower($u['email'])) json_fail('cannot_delete_self',409);
$pdo=get_pdo(); $st=$pdo->prepare('DELETE FROM users WHERE email=?'); $st->execute([$email]);
json_ok(['deleted'=>$st->rowCount(),'email'=>$email]);
