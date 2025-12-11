<?php declare(strict_types=1);
/**
 * POST /avail_save.php
 * Body JSON: { "avail": { "samedi 09:00":"juge", ... }, "email"?: "autre@..." }
 * - Sauvegarde les dispos de l'utilisateur courant.
 * - Si "email" est fourni et différent, il faut être admin.
 * - Stockage: kv_store (k="avail:<email>", v=JSON)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// ⚠️ Idéalement à placer dans un bootstrap inclus par tous les endpoints :
// session_set_cookie_params(['path'=>'/la-hache-contest','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);

ini_set('display_errors','0'); error_reporting(0);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function jerr(int $code, string $msg){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function clean_email(?string $s): string { return strtolower(trim((string)$s)); }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr(405, 'method_not_allowed');

  // Auth requise
$sessionEmail = clean_email($_SESSION['user_email'] ?? ($_SESSION['user']['email'] ?? ''));
  if ($sessionEmail === '') jerr(401, 'not_authenticated');

  // Parse JSON
  $raw = file_get_contents('php://input') ?: '';
  $in = json_decode($raw, true);
  if (!is_array($in)) jerr(400, 'invalid_json');

  $avail = $in['avail'] ?? null;
  $target = clean_email($in['email'] ?? '');
  if (!is_array($avail)) jerr(400, 'invalid_input');

  $db = dirname(__DIR__).'/data/app.sqlite';
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Si on modifie quelqu'un d'autre → admin requis
  $emailToSave = $target !== '' ? $target : $sessionEmail;
  if ($emailToSave !== $sessionEmail) {
    $st = $pdo->prepare('SELECT role FROM users WHERE lower(email)=lower(?) AND is_active=1');
    $st->execute([$sessionEmail]);
    if (($st->fetchColumn() ?: '') !== 'admin') jerr(403, 'forbidden');
  }

  // Normaliser/filtrer les valeurs
  $norm = [];
  foreach ($avail as $k=>$v) {
    $k2 = trim((string)$k);
    if ($k2 === '') continue;
    $vv = (string)$v;
    if (!in_array($vv, ['none','dispo','juge','build'], true)) continue;
    $norm[$k2] = $vv;
  }

  // Assurer la table kv_store
  $pdo->exec('CREATE TABLE IF NOT EXISTS kv_store (k TEXT PRIMARY KEY, v TEXT, updated_at INTEGER)');

  // Upsert
  $st = $pdo->prepare('INSERT INTO kv_store(k,v,updated_at) VALUES(?,?,?)
                       ON CONFLICT(k) DO UPDATE SET v=excluded.v, updated_at=excluded.updated_at');
  $now = time();
  $st->execute(['avail:'.$emailToSave, json_encode($norm, JSON_UNESCAPED_UNICODE), $now]);
  $st->execute(['avail_updated:'.$emailToSave, json_encode(['at'=>$now], JSON_UNESCAPED_UNICODE), $now]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  jerr(500, 'exception');
}
