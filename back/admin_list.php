<?php declare(strict_types=1); require __DIR__.'/config.php';
$u=current_user(); if(!$u || ($u['role']??'')!=='admin') json_fail('forbidden',403);
$pdo=get_pdo(); $st=$pdo->query('SELECT id,name,email,role,is_active FROM users ORDER BY role DESC, name ASC');
$rows=$st->fetchAll(); json_ok(['users'=>$rows]);
