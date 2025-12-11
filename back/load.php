<?php declare(strict_types=1); require __DIR__.'/config.php';
$pdo=get_pdo(); $st=$pdo->prepare('SELECT v,updated_at FROM kv_store WHERE k=?'); $st->execute(['global_state']);
$row=$st->fetch(); $payload=$row?json_decode($row['v'],true):[];
json_ok(['data'=>$payload,'updated_at'=>$row['updated_at'] ?? null]);
