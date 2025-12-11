<?php declare(strict_types=1); require __DIR__.'/config.php';
$u=current_user(); if(!$u || ($u['role']??'')!=='admin') json_fail('forbidden',403); require_csrf();
$in=json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$email=strtolower(trim((string)($in['email']??''))); $role=trim((string)($in['role']??''));
if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['benevole','admin'],true)) json_fail('invalid_input',400);
$pdo=get_pdo(); $st=$pdo->prepare('UPDATE users SET role=? WHERE email=?'); $st->execute([$role,$email]);
json_ok(['email'=>$email,'role'=>$role,'affected'=>$st->rowCount()]);
