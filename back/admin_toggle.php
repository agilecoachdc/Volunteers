<?php declare(strict_types=1); require __DIR__.'/config.php';
$u=current_user(); if(!$u || ($u['role']??'')!=='admin') json_fail('forbidden',403); require_csrf();
$in=json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$email=strtolower(trim((string)($in['email']??''))); $is_active= !!($in['is_active'] ?? true);
if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL)) json_fail('invalid_input',400);
$pdo=get_pdo(); $st=$pdo->prepare('UPDATE users SET is_active=? WHERE email=?'); $st->execute([$is_active?1:0,$email]);
json_ok(['email'=>$email,'is_active'=>$is_active]);
