<?php
// api/overwrite_avail.php
declare(strict_types=1);

@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php'; // get_pdo(), current_user(), json_ok(), json_fail()

// ————————————————————————
// Helpers
// ————————————————————————
function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function is_allowed_key(string $key, int $H_MAX = 20): bool {
  $key = strtolower(trim($key));
  if (!preg_match('~^(samedi|dimanche)\s+([0-2]\d):([0-5]\d)$~', $key, $m)) return false;
  $hh = (int)$m[2]; $mi = (int)$m[3];
  if ($mi !== 0 && $mi !== 30) return false;
  if ($hh < $H_MAX) return true;
  if ($hh === $H_MAX && $mi === 0) return true;
  return false;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('method_not_allowed', 405, ['message'=>'POST only']);
  }

  $body = read_json_body();
  if (!$body) {
    json_fail('bad_request', 400, ['message'=>'Invalid or empty JSON body']);
  }

  // Email: explicite dans le body sinon utilisateur courant
  $me = current_user();
  $email = strtolower(trim((string)($body['email'] ?? ($me['email'] ?? ''))));
  if ($email === '') {
    json_fail('bad_request', 400, ['message'=>'email manquant']);
  }

  // Carte d’entrée: { "samedi 09:00": "dispo"|"none", ... }
  $incoming = (array)($body['avail'] ?? []);
  // Filtre strict + on ne stocke QUE les "dispo"
  $out = [];
  foreach ($incoming as $k => $v) {
    $kk = (string)$k;
    $vv = (string)$v;
    if ($vv !== 'dispo' && $vv !== 'none') continue;
    if (!is_allowed_key($kk, 20)) continue; // <= 20:00, pas de :15/:45, jours valides
    if ($vv === 'dispo') $out[strtolower(trim($kk))] = 'dispo';
  }

  // Écraser totalement
  $pdo = get_pdo();
  $kAvail   = 'avail:'.$email;
  $kUpdated = 'avail_updated:'.$email;

  // kv_set doit accepter un tableau (json-encodé côté helper)
  kv_set($pdo, $kAvail, $out);                   // -> purge complète & remplace
  kv_set($pdo, $kUpdated, ['at' => time()]);

  json_ok([
    'email'  => $email,
    'saved'  => count($out),
    'keys'   => array_keys($out),
  ]);

} catch (Throwable $e) {
  // remonter l’erreur concrète pour debug
  json_fail('exception', 500, ['message' => $e->getMessage()]);
}
