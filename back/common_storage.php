<?php
// api/common_storage.php
declare(strict_types=1);

ini_set('display_errors','0'); error_reporting(0);

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $db = dirname(__DIR__).'/data/app.sqlite';
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function kv_get(string $key): ?array {
  try {
    $st = db()->prepare('SELECT v FROM kv_store WHERE k=?');
    $st->execute([$key]);
    $row = $st->fetch();
    if ($row && is_string($row['v'])) {
      $data = json_decode($row['v'], true);
      if (is_array($data)) return $data;
    }
  } catch (Throwable $e) {}
  return null;
}

function kv_put(string $key, array $value): void {
  $json = json_encode($value, JSON_UNESCAPED_UNICODE);
  $st = db()->prepare('INSERT INTO kv_store(k,v,updated_at) VALUES(?,?,?) 
                       ON CONFLICT(k) DO UPDATE SET v=excluded.v, updated_at=excluded.updated_at');
  $st->execute([$key, $json, time()]);
}

function loadHeats(): array {
  $v = kv_get('heats');
  if (is_array($v)) return $v;
  $file = __DIR__.'/_heats.json';
  if (file_exists($file)) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) return $d;
  }
  return [];
}

function saveHeats(array $heats): void {
  kv_put('heats', $heats);
  @file_put_contents(__DIR__.'/_heats.json', json_encode($heats, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

function loadUsers(): array {
  try {
    $rs = db()->query('SELECT name,email,role,COALESCE(is_active,1) AS is_active, COALESCE(poste,"juge") AS poste FROM users');
    $a = $rs->fetchAll();
    if (is_array($a)) return $a;
  } catch (Throwable $e) {}
  $file = __DIR__.'/_users.json';
  if (file_exists($file)) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) return $d;
  }
  return [];
}

function loadAvail(string $email): array {
  $email = strtolower(trim($email));
  try {
    $row = kv_get('avail:'.$email);
    if (is_array($row)) {
      if (array_key_exists('avail',$row) && is_array($row['avail'])) return $row['avail'];
      return $row;
    }
  } catch (Throwable $e) {}
  $file = __DIR__."/avail/".preg_replace('/[^a-z0-9_.@-]/i','_', $email).".json";
  if (file_exists($file)) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) {
      if (array_key_exists('avail',$d) && is_array($d['avail'])) return $d['avail'];
      return $d;
    }
  }
  return [];
}

/* ===== Helpers temps / clés ===== */
function pad2($n){ $n=(int)$n; return $n<10?('0'.$n):(string)$n; }

function normalize_slot(string $start): string {
  $start = trim($start);
  if (!preg_match('/^\d{1,2}:\d{2}$/', $start)) return strtolower($start);
  [$h,$m] = explode(':',$start);
  return pad2((int)$h).':'.pad2((int)$m);
}

function keyNorm(string $day, string $start): string {
  $d = strtolower(trim(preg_replace('/\s+/u',' ', $day)));
  $s = normalize_slot($start);
  return $d.' '.$s;
}

function keyAlt(string $day, string $start): string {
  $d = strtolower(trim(preg_replace('/\s+/u',' ', $day)));
  if (!preg_match('/^\d{1,2}:\d{2}$/', $start)) return $d.' '.strtolower(trim($start));
  [$h,$m] = explode(':',$start);
  return $d.' '.((int)$h).':'.pad2((int)$m);
}

function hm_to_minutes(string $hhmm): int {
  $hhmm = normalize_slot($hhmm);
  if (!preg_match('/^\d{2}:\d{2}$/',$hhmm)) return -1;
  [$h,$m] = explode(':',$hhmm);
  return ((int)$h)*60 + (int)$m;
}

function timeInLunch(string $t): bool {
  // 12:00 <= t < 14:00
  $t = normalize_slot($t);
  return $t >= '12:00' && $t < '14:00';
}

/**
 * Retourne true si, dans [12:00;14:00[, il reste au moins 30 min d'affilée non affectées
 * pour $email (en simulant l'ajout à $candidateStart).
 * Hypothèse: les heats sont au pas de 15 minutes (comme tes données).
 */
function lunch_break_ok(array $allHeatsForDay, string $candidateStart, string $email): bool {
  $L0 = 12*60;  // 12:00
  $L1 = 14*60;  // 14:00
  $assign = [];
  // existant
  foreach ($allHeatsForDay as $h) {
    $s = normalize_slot((string)($h['start'] ?? $h['heure'] ?? ''));
    $m = hm_to_minutes($s);
    if ($m < $L0 || $m >= $L1) continue;
    foreach ($h['lignes'] ?? [] as $ln) {
      $j = strtolower((string)($ln['juge']  ?? ''));
      $b = strtolower((string)($ln['build'] ?? ''));
      if ($j === strtolower($email) || $b === strtolower($email)) {
        $assign[$m] = true;
      }
    }
  }
  // simulation ajout
  $mCand = hm_to_minutes($candidateStart);
  if ($mCand >= $L0 && $mCand < $L1) $assign[$mCand] = true;

  // y a-t-il 2 slots consécutifs libres (2×15min) quelque part entre 12:00 et 14:00 ?
  for ($m = $L0; $m <= $L1 - 30; $m += 15) {
    if (empty($assign[$m]) && empty($assign[$m+15])) {
      return true; // OK, pause possible
    }
  }
  return false; // aucune fenêtre 30min
}

/** utility: retourne tous les heats d’un jour donné (samedi/dimanche) */
function heats_of_day(array $heats, string $day): array {
  $dWanted = strtolower(trim($day));
  $out = [];
  foreach ($heats as $h) {
    $d = strtolower(trim((string)($h['day'] ?? $h['jour'] ?? '')));
    if ($d === $dWanted) $out[] = $h;
  }
  return $out;
}

function slot_variants_keys(string $day, string $start): array {
  $day = strtolower(trim(preg_replace('/\s+/u',' ', $day)));
  $norm = normalize_slot($start);
  if (!preg_match('/^\d{2}:\d{2}$/',$norm)) return [ keyNorm($day,$start), keyAlt($day,$start) ];

  [$h,$m] = explode(':',$norm); $h=(int)$h; $m=(int)$m;
  $set = [];
  $add = function(int $H,int $M) use (&$set,$day){
    $H = max(0,$H); $M = max(0,$M%60); $H += intdiv(max(0,$M),60);
    foreach ([ keyNorm($day, pad2($H).':'.pad2($M)), keyAlt($day, pad2($H).':'.pad2($M)) ] as $k) $set[$k]=true;
  };
  // exact
  $add($h,$m);
  // snaps +/-
  $add($h, ($m>=30?30:0));
  $add(($m>30)?$h+1:$h, ($m===0?0: ($m<=30?30:0)));
  // floor 15
  $add($h, ($m>=45?45: ($m>=30?30: ($m>=15?15:0))));
  // ±15 / ±30
  foreach ([-15,15,-30,30] as $d) { $tot = $h*60+$m+$d; if ($tot>=0){ $add(intdiv($tot,60), $tot%60); } }
  return array_keys($set);
}
