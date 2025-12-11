<?php
// api/purge_avail_all.php
declare(strict_types=1);
@ini_set('display_errors','0'); error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/common_storage.php'; // loadUsers(), loadAvail(), saveAvail(), normalize_slot()

// === Réglages whitelist
$days  = ['samedi','dimanche'];  // jours autorisés
$hFrom = 8;   // 08:00
$hTo   = 18;  // 18:00
// ===

function in_whitelist(string $key, array $days, int $hFrom, int $hTo): bool {
  $k = strtolower(trim($key));
  $okDay = false; $day = '';
  foreach ($days as $d) { if (strpos($k, $d.' ') === 0) { $okDay = true; $day = $d; break; } }
  if (!$okDay) return false;

  $t = trim(substr($k, strlen($day)));
  $t = ltrim($t);
  if (!preg_match('/^([0-2][0-9]):([0-5][0-9])$/', $t, $m)) return false;
  $h = (int)$m[1]; $mi = (int)$m[2];
  if ($h < $hFrom || $h > $hTo) return false;
  // pas de 15 min
  if (($mi % 15) !== 0) return false;
  return true;
}

$users = loadUsers();
$report = [];

foreach ($users as $u) {
  if ((int)($u['is_active'] ?? 1) !== 1) continue;
  $email = '';
  foreach (['email','Email','mail','e-mail','login','user'] as $k) {
    $v = trim((string)($u[$k] ?? '')); if ($v!=='') { $email = strtolower($v); break; }
  }
  if ($email==='') continue;

  $cur = loadAvail($email) ?: [];
  $cur = is_array($cur) ? $cur : [];
  $kept = [];
  foreach ($cur as $k => $v) {
    if (in_whitelist((string)$k, $days, $hFrom, $hTo)) {
      // garde uniquement les valeurs 'dispo' (tout le reste = on jette)
      $vv = strtolower(trim((string)$v)) === 'dispo' ? 'dispo' : '';
      if ($vv === 'dispo') $kept[$k] = 'dispo';
    }
  }
  saveAvail([], $email);   // reset total
  saveAvail($kept, $email);

  $report[] = ['email'=>$email, 'before'=>count($cur), 'after'=>count($kept)];
}

echo json_encode(['ok'=>true, 'report'=>$report], JSON_UNESCAPED_UNICODE);
