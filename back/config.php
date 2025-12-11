<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$DEBUG = getenv('LHC_DEBUG') === '1';
if ($DEBUG) { ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL); }
else { ini_set('display_errors','0'); error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); }
if (session_status() !== PHP_SESSION_ACTIVE) {
  if (!ini_get('session.save_path')) {
    $sp = sys_get_temp_dir().'/php-sessions'; if (!is_dir($sp)) @mkdir($sp,0777,true); @ini_set('session.save_path',$sp);
  }
  session_start();
}
function json_ok(array $data=[]) : void { echo json_encode($data ?: ['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
function json_fail(string $error='error', int $status=400, array $ctx=[]) : void {
  http_response_code($status); $out=['ok'=>false,'error'=>$error]; if (!empty($ctx)) $out['context']=$ctx; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}
function array_get(array $a, string $k, $def=null){ return $a[$k] ?? $def; }
function current_user() : ?array { return $_SESSION['user'] ?? null; }
function require_csrf() : void { $h = $_SERVER['HTTP_X_CSRF'] ?? ''; if (!$h) json_fail('csrf_required',400); }
function data_dir() : string { $dir = dirname(__DIR__).'/data'; if (!is_dir($dir)) @mkdir($dir,0777,true); return $dir; }
function db_path() : string { return data_dir().'/app.sqlite'; }
function get_pdo() : PDO {
  static $pdo=null; if ($pdo instanceof PDO) return $pdo;
  $pdo = new PDO('sqlite:'.db_path(), null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $pdo->exec('PRAGMA journal_mode=WAL;'); ensure_schema($pdo); return $pdo;
}
function ensure_schema(PDO $pdo) : void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS kv_store (k TEXT PRIMARY KEY, v TEXT NOT NULL, updated_at INTEGER)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password_hash TEXT, role TEXT DEFAULT "benevole", is_active INTEGER DEFAULT 1, created_at INTEGER)');
}
function kv_get(PDO $pdo, string $k, $def=null){ $st=$pdo->prepare('SELECT v FROM kv_store WHERE k=?'); $st->execute([$k]); $row=$st->fetch(); if(!$row) return $def; $j=json_decode((string)$row['v'], true); return $j===null?$def:$j; }
function kv_set(PDO $pdo, string $k, $v) : void { $st=$pdo->prepare('INSERT INTO kv_store(k,v,updated_at) VALUES(?,?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v,updated_at=excluded.updated_at'); $st->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE), time()]); }
