<?php declare(strict_types=1); require __DIR__.'/config.php';
$u=current_user(); if(!$u) json_fail('auth',401); require_csrf();
$in=json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$payload=$in['data'] ?? null; if(!is_array($payload)) json_fail('invalid_input',400);
$pdo=get_pdo(); $st=$pdo->prepare("INSERT INTO kv_store(k,v,updated_at) VALUES(?,?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v, updated_at=excluded.updated_at");
$st->execute(['global_state', json_encode($payload, JSON_UNESCAPED_UNICODE), time()]);
json_ok();
