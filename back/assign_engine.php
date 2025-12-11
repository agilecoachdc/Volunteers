<?php
// api/assign_engine.php
declare(strict_types=1);

require_once __DIR__.'/common_storage.php'; // loadUsers(), loadAvail(), normalize_slot(), keyNorm/keyAlt

/* =========================================================
 * IO utils
 * ======================================================= */
function _read_json(string $fn, $fallback) {
  if (!file_exists($fn)) return $fallback;
  $j = json_decode(@file_get_contents($fn), true);
  return is_array($j) ? $j : $fallback;
}
function _write_json(string $fn, $data): void {
  @file_put_contents($fn, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

/* =========================================================
 * Time & availability helpers
 * ======================================================= */
function norm_day(string $d): string { return strtolower(trim($d)); }
function norm_time(string $t): string { return normalize_slot($t); } // hh:mm

// Bucket 30' (10:37 → 10:30 ; 10:15 → 10:00 ; 10:45 → 10:30)
function bucket30(string $hhmm): string {
  $t = normalize_slot($hhmm);
  if (!preg_match('~^([0-2]\d):([0-5]\d)$~', $t, $m)) return $t;
  $h = (int)$m[1]; $mi=(int)$m[2];
  $mi = ($mi<30) ? 0 : 30;
  return sprintf('%02d:%02d', $h, $mi);
}
function slot_key_exact(string $day, string $start): string {
  return norm_day($day).' '.norm_time($start);
}
function slot_key_30(string $day, string $start): string {
  return norm_day($day).' '.bucket30($start);
}

function is_in_lunch(string $hhmm): bool {
  $t = normalize_slot($hhmm);
  if (!preg_match('~^([0-2]\d):([0-5]\d)$~', $t, $m)) return false;
  $h=(int)$m[1];
  return ($h>=12 && $h<=13); // 12:00..13:59
}
function lunch_bucket(string $hhmm): ?string {
  if (!is_in_lunch($hhmm)) return null;
  $b = bucket30($hhmm); // 12:00 / 12:30 / 13:00 / 13:30
  return in_array($b,['12:00','12:30','13:00','13:30'],true) ? $b : null;
}

/**
 * Dispo "tolérante" : un horaire ex. 10:37 tombe dans le seau [10:30,11:00).
 * Si le bénévole est "dispo" au bucket 10:30, on considère 10:37 comme dispo.
 */
function is_available(array $availMap, string $email, string $day, string $start): bool {
  $m = $availMap[$email] ?? [];
  $k = slot_key_30($day, $start);
  return (($m[$k] ?? 'none') === 'dispo');
}

/* =========================================================
 * Pools & avail
 * ======================================================= */
/**
 * Construit les pools juges/build (tolérance sur poste),
 * ainsi que la map des dispos (jour hh:mm bucket30 → 'dispo').
 *
 * Retourne: [ $judges, $builders, $all, $availMap, $users ]
 */
function build_pools_and_avail(): array {
  $users = loadUsers();
  $all=[]; $judges=[]; $builders=[]; $availMap=[];

  foreach ($users as $u){
    if ((int)($u['is_active'] ?? 1) !== 1) continue;
    $email = strtolower(trim((string)($u['email'] ?? '')));
    if ($email==='') continue;

    $all[] = $email;

    $poste = strtolower(trim((string)($u['poste'] ?? '')));
    // Classement tolérant du poste
    if ($poste === '' || (isset($poste[0]) && $poste[0] === 'j')) {
      // vide → juge par défaut ; ou commence par 'j' → juge
      $judges[] = $email;
    } elseif (isset($poste[0]) && $poste[0] === 'b') {
      // commence par 'b' → build
      $builders[] = $email;
    } elseif ($poste === 'staff') {
      // staff: pas d’ajout dans J/B
    } else {
      // poste inconnu → ne pas « perdre » le bénévole : on l’ouvre aux 2 pools
      $judges[] = $email;
      $builders[] = $email;
    }

    // map des dispos
    if (!isset($availMap[$email])) {
      // loadAvail(email) retourne déjà une map "jour hh:mm" => 'dispo'/'none'
      // côté overwrite_avail.php tu ne stockes que les 'dispo', parfait.
      $availMap[$email] = loadAvail($email);
    }
  }

  // Unicité
  $judges   = array_values(array_unique($judges));
  $builders = array_values(array_unique($builders));

  if (empty($judges))  $judges = $all;
  if (empty($builders)) $builders = $all;

  return [$judges,$builders,$all,$availMap,$users];
}

/* =========================================================
 * Règles "midi" & rotations
 * ======================================================= */
// Pause entre 12h et 14h : on impose au moins 30' de liberté
// On autorise max 3 buckets midi (12:00, 12:30, 13:00, 13:30) pris par bénévole/jour
function can_take_lunch_slot(array $lunchSlots, string $email, string $day, string $start): bool {
  $b = lunch_bucket($start);
  if ($b===null) return true;
  $cur = $lunchSlots[$email][$day] ?? [];
  // max 3 buckets → garde au moins 1 libre (pause 30')
  return (count($cur) < 3) || isset($cur[$b]); // ré-attribuer même bucket ok
}

const RUN_MIN = 2;  // min 2 heats consécutifs
const RUN_MAX = 4;  // max 4
const COOLDOWN = 2; // pause de 2 heats entre 2 runs

/* =========================================================
 * Outils d’indexation
 * ======================================================= */
function index_heats_by_exact_start(array $heats): array {
  $idxByKey = []; // "day startExact" => [heatIndex]
  foreach ($heats as $i => $h) {
    $d = norm_day((string)($h['day'] ?? $h['jour'] ?? ''));
    $s = norm_time((string)($h['start'] ?? $h['heure'] ?? ''));
    if ($d==='' || $s==='') continue;
    $idxByKey["$d $s"][] = $i;
  }
  return $idxByKey;
}

/* =========================================================
 * Noyau d’assignation "régles fortes" (continuité par lane, rotations)
 * ======================================================= */
/**
 * @param array $heats   (in/out)
 * @param array $scope   [['day'=>..,'start'=>..], ...] — slots à traiter, triés
 * @param array $pool    emails (juge) ou (build)
 * @param string $role   'juge'|'build'
 * @param array &$cAssigned  compteur global par email
 * @param array &$busyTime   email => set("day startExact" => true)  (évite double-book J/B)
 * @param array &$lunchSlots email => day => set('12:00'|'12:30'|'13:00'|'13:30' => true)
 * @param array $availMap    email => availMap
 */
function assign_over_scope(array &$heats, array $scope, array $pool, string $role,
                           array &$cAssigned, array &$busyTime, array &$lunchSlots,
                           array $availMap): void
{
  $idxByKey = index_heats_by_exact_start($heats);

  // Index temporel par jour pour calculer les pauses (cooldown)
  $timeline = []; // day => [start1, start2, ...] triés
  foreach ($scope as $s) {
    $day = $s['day']; $st = $s['start'];
    $timeline[$day][] = $st;
  }
  foreach ($timeline as $d => &$arr) { $arr = array_values(array_unique($arr)); sort($arr); }
  unset($arr);
  $idxOf = []; // day => start => index
  foreach ($timeline as $d => $arr) {
    $idxOf[$d] = [];
    foreach ($arr as $i => $st) $idxOf[$d][$st] = $i;
  }

  // État "run" par (lineIdx, day) → email + longueur + index départ
  $runEmail = [];     // "$day#$lineIdx" => email
  $runLen   = [];     // "$day#$lineIdx" => int
  $runStartIdx = [];  // "$day#$lineIdx" => int (index dans timeline[day])

  // Cooldown par bénévole et par jour : index minimal à partir duquel il peut reprendre un run
  $cooldownUntil = []; // email => day => index (exclusive: doit être >= à cette valeur)

  // Tri initial du pool par charge
  usort($pool, fn($a,$b)=> [ (int)($cAssigned[$a]??0), $a ] <=> [ (int)($cAssigned[$b]??0), $b ]);

  foreach ($scope as $slot) {
    $day = $slot['day']; $startExact = $slot['start'];
    $iIdx = $idxOf[$day][$startExact] ?? null;
    if ($iIdx===null) continue;

    $kExact = "$day $startExact";
    $heatIdxList = $idxByKey[$kExact] ?? [];
    if (empty($heatIdxList)) continue;

    foreach ($heatIdxList as $hi) {
      $nLines = max(0, count($heats[$hi]['lignes'] ?? []));
      for ($li=0; $li<$nLines; $li++) {
        // déjà rempli ?
        $already = trim((string)($heats[$hi]['lignes'][$li][$role] ?? ''));
        if ($already!=='') continue;

        $runKey = "$day#$li";

        // 1) Prolonger un run existant si possible (même lane)
        $current = $runEmail[$runKey] ?? null;
        if ($current) {
          $len = (int)($runLen[$runKey] ?? 0);
          // Si on a atteint RUN_MAX, on clôt le run et pose le cooldown
          if ($len >= RUN_MAX) {
            $cooldownUntil[$current][$day] = max($cooldownUntil[$current][$day] ?? 0, $iIdx + COOLDOWN);
            unset($runEmail[$runKey], $runLen[$runKey], $runStartIdx[$runKey]);
          } else {
            // peut-on prolonger ?
            if (is_available($availMap, $current, $day, $startExact)
                && empty($busyTime[$current][$kExact])
                && can_take_lunch_slot($lunchSlots, $current, $day, $startExact)
                && ($cooldownUntil[$current][$day] ?? 0) <= $iIdx) {

              $heats[$hi]['lignes'][$li][$role] = $current;
              $cAssigned[$current] = (int)($cAssigned[$current]??0)+1;
              $busyTime[$current][$kExact] = true;
              if ($b = lunch_bucket($startExact)) $lunchSlots[$current][$day][$b] = true;

              $runLen[$runKey] = $len + 1;
              continue; // lane prolongée
            } else {
              // ne peut pas prolonger → clôture du run (si longueur < RUN_MIN, tant pis)
              if ($len >= RUN_MIN) {
                $cooldownUntil[$current][$day] = max($cooldownUntil[$current][$day] ?? 0, $iIdx + COOLDOWN);
              }
              unset($runEmail[$runKey], $runLen[$runKey], $runStartIdx[$runKey]);
            }
          }
        }

        // 2) Démarrer un run (nouvelle série) : candidats valides & respect cooldown
        //   - On exige que le bénévole puisse raisonnablement viser >= RUN_MIN
        $cands=[];
        foreach ($pool as $e) {
          if (!is_available($availMap,$e,$day,$startExact)) continue;
          if (!empty($busyTime[$e][$kExact])) continue;
          if (!can_take_lunch_slot($lunchSlots, $e, $day, $startExact)) continue;
          if (($cooldownUntil[$e][$day] ?? 0) > $iIdx) continue; // encore en cooldown
          $cands[]=$e;
        }
        if (empty($cands)) continue;

        // Look-ahead pour estimer la longueur atteignable sur CETTE LANE
        $score=[];
        foreach ($cands as $e) {
          $len = 1; // inclut le slot courant
          $idx = $iIdx;
          $lunchTmp = $lunchSlots; // copie locale pour simulation
          // simuler les prochains slots jusqu’à RUN_MAX
          while ($len < RUN_MAX) {
            $idxNext = $idx + 1;
            $nextStart = $timeline[$day][$idxNext] ?? null;
            if ($nextStart === null) break;

            // vérifier qu’il existe un heat avec cette ligne (li) à cet horaire
            $key2 = "$day $nextStart";
            $list2 = $idxByKey[$key2] ?? [];
            $ok=false;
            foreach ($list2 as $hi2){
              if (isset($heats[$hi2]['lignes'][$li])) { $ok=true; break; }
            }
            if (!$ok) break;

            // contraintes pour e à nextStart
            if (!is_available($availMap,$e,$day,$nextStart)) break;
            if (!empty($busyTime[$e]["$day $nextStart"])) break;
            if (!can_take_lunch_slot($lunchTmp, $e, $day, $nextStart)) break;

            // simuler prise
            if ($b = lunch_bucket($nextStart)) { $lunchTmp[$e][$day][$b] = true; }
            $len++; $idx = $idxNext;
          }

          $score[$e] = [$len, (int)($cAssigned[$e]??0), $e];
        }
        // On veut prioriser : (1) longueur atteignable desc, (2) charge asc, (3) alpha
        usort($cands, function($a,$b) use($score){
          [$la,$ca,$ea] = $score[$a];
          [$lb,$cb,$eb] = $score[$b];
          if ($la!==$lb) return $lb<=>$la;
          if ($ca!==$cb) return $ca<=>$cb;
          return strcmp($ea,$eb);
        });

        // démarrage si len simulée >= RUN_MIN, sinon on tente quand même le mieux classé
        $pick = null;
        foreach ($cands as $cand) {
          $lenSim = $score[$cand][0];
          if ($lenSim >= RUN_MIN) { $pick = $cand; break; }
        }
        if ($pick===null) $pick = $cands[0] ?? null;

        if ($pick){
          // Assigne le slot courant et ouvre un run
          $heats[$hi]['lignes'][$li][$role] = $pick;
          $cAssigned[$pick] = (int)($cAssigned[$pick]??0)+1;
          $busyTime[$pick][$kExact] = true;
          if ($b = lunch_bucket($startExact)) $lunchSlots[$pick][$day][$b] = true;

          $runEmail[$runKey]   = $pick;
          $runLen[$runKey]     = 1;
          $runStartIdx[$runKey]= $iIdx;
        }
      }
    }
  }

  // Fin parcours : cooldown pour les runs restants avec longueur ≥ RUN_MIN
  foreach ($runEmail as $rk => $email) {
    $dayKey = strstr($rk, '#', true);
    $len = (int)($runLen[$rk] ?? 0);
    $startIdx = (int)($runStartIdx[$rk] ?? 0);
    $endIdx = $startIdx + max(0,$len-1);
    if ($len >= RUN_MIN) {
      $cooldownUntil[$email][$dayKey] = max($cooldownUntil[$email][$dayKey] ?? 0, $endIdx + 1 + COOLDOWN);
    }
  }
}

/* =========================================================
 * Backfill "règles relaxées" pour combler les vides
 * ======================================================= */
// Liste (par start exact) des emplacements vides pour un rôle
function list_missing_slots(array $heats, string $role): array {
  $miss = []; // "day start" => [ [hi, li], ... ]
  foreach ($heats as $hi => $h) {
    $day = norm_day((string)($h['day'] ?? $h['jour'] ?? ''));
    $start = norm_time((string)($h['start'] ?? $h['heure'] ?? ''));
    if ($day===''||$start==='') continue;
    $k = "$day $start";
    $n = max(0, count($h['lignes'] ?? []));
    for ($li=0; $li<$n; $li++) {
      $v = trim((string)($h['lignes'][$li][$role] ?? ''));
      if ($v==='') $miss[$k][] = [$hi, $li];
    }
  }
  return $miss;
}

// Prépare un "pool global actif" (hors staff) pour le fallback
function build_global_active_pool(array $judges, array $builders, array $all, array $users): array {
  $staff = [];
  foreach ($users as $u) {
    if ((int)($u['is_active'] ?? 1) !== 1) continue;
    $email = strtolower(trim((string)($u['email'] ?? ''))); if ($email==='') continue;
    $p = strtolower(trim((string)($u['poste'] ?? '')));
    if ($p === 'staff') $staff[$email] = true;
  }
  $merged = array_values(array_unique(array_merge($judges, $builders)));
  // enlève staff
  $out = [];
  foreach ($merged as $e) if (empty($staff[$e])) $out[] = $e;
  // Si jamais vide, repli: tous actifs hors staff
  if (empty($out)) {
    $out = [];
    foreach ($all as $e) if (empty($staff[$e])) $out[] = $e;
  }
  return $out;
}

function backfill_role(array &$heats, array $poolPrimary, array $poolAll, string $role,
                       array &$assignedCount, array &$busyTime, array $availMap): void
{
  $idxByKey = index_heats_by_exact_start($heats);
  $missing = list_missing_slots($heats, $role);
  if (empty($missing)) return;

  // tri équitable du pool (les moins chargés d’abord)
  $sortByLoad = function(array $arr) use($assignedCount): array {
    $arr2 = $arr;
    usort($arr2, fn($a,$b)=> [ (int)($assignedCount[$a]??0), $a ] <=> [ (int)($assignedCount[$b]??0), $b ]);
    return $arr2;
  };

  foreach ($missing as $key => $slots) {
    [$day, $start] = explode(' ', $key, 2);
    $kExact = "$day $start";
    $heatIdxList = $idxByKey[$kExact] ?? [];
    if (empty($heatIdxList)) continue; // sécurité

    $candsOrdered = $sortByLoad($poolPrimary);
    $fallbackOrdered = $sortByLoad($poolAll);

    foreach ($slots as [$hi, $li]) {
      // sécurité multi-passe
      $cur = trim((string)($heats[$hi]['lignes'][$li][$role] ?? ''));
      if ($cur!=='') continue;

      // 1) pool du rôle
      $picked = null;
      foreach ($candsOrdered as $e) {
        if (!is_available($availMap, $e, $day, $start)) continue;
        if (!empty($busyTime[$e][$kExact])) continue; // pas double-book au même slot
        $picked = $e; break;
      }

      // 2) fallback: pool global (tous actifs hors staff)
      if (!$picked) {
        foreach ($fallbackOrdered as $e) {
          if (!is_available($availMap, $e, $day, $start)) continue;
          if (!empty($busyTime[$e][$kExact])) continue;
          $picked = $e; break;
        }
      }

      if ($picked) {
        $heats[$hi]['lignes'][$li][$role] = $picked;
        $assignedCount[$picked] = (int)($assignedCount[$picked]??0) + 1;
        $busyTime[$picked][$kExact] = true; // bloque ce slot pour l’autre rôle aussi
      }
    }
  }
}

/* =========================================================
 * Public API (engine)
 * ======================================================= */
function engine_assign_all(array &$heats): void {
  // purge J/B uniquement (NE PAS toucher à day/wod/heat/team)
  for ($i=0;$i<count($heats);$i++){
    if (!isset($heats[$i]['lignes']) || !is_array($heats[$i]['lignes'])) $heats[$i]['lignes']=[];
    for ($li=0;$li<count($heats[$i]['lignes']);$li++){
      $heats[$i]['lignes'][$li]['juge']='';
      $heats[$i]['lignes'][$li]['build']='';
    }
  }

  // scope = tous les starts triés (jour, heure)
  $scope=[]; $seen=[];
  foreach ($heats as $h){
    $d = norm_day((string)($h['day'] ?? $h['jour'] ?? ''));
    $s = norm_time((string)($h['start'] ?? $h['heure'] ?? ''));
    if ($d===''||$s==='') continue;
    $k="$d $s";
    if (!isset($seen[$k])) { $seen[$k]=true; $scope[]=['day'=>$d,'start'=>$s]; }
  }
  usort($scope, fn($a,$b)=> strcmp($a['day'].' '.$a['start'], $b['day'].' '.$b['start']));

  // pools & avail
  [$judges,$builders,$all,$availMap,$users] = build_pools_and_avail();

  // états globaux
  $assignedJ=[]; $assignedB=[];
  $busyTime=[];            // email => set("day startExact")
  $lunchSlotsJ=[];         // email => day => set(bucket => true)
  $lunchSlotsB=[];

  // build puis juges (mêmes règles)
  assign_over_scope($heats,$scope,$builders,'build',$assignedB,$busyTime,$lunchSlotsB,$availMap);
  assign_over_scope($heats,$scope,$judges,'juge',$assignedJ,$busyTime,$lunchSlotsJ,$availMap);

  // Backfill relaxé pour combler les trous restants
  $poolAll = build_global_active_pool($judges,$builders,$all,$users);
  backfill_role($heats, $builders, $poolAll, 'build', $assignedB, $busyTime, $availMap);
  backfill_role($heats, $judges,   $poolAll, 'juge',  $assignedJ, $busyTime, $availMap);
}

function engine_assign_one(array &$heats, string $day, string $start): void {
  // purge J/B pour ce start
  $d = norm_day($day); $s = norm_time($start);
  for ($i=0;$i<count($heats);$i++){
    $di = norm_day((string)($heats[$i]['day'] ?? $heats[$i]['jour'] ?? ''));
    $si = norm_time((string)($heats[$i]['start'] ?? $heats[$i]['heure'] ?? ''));
    if ($di===$d && $si===$s){
      if (!isset($heats[$i]['lignes']) || !is_array($heats[$i]['lignes'])) $heats[$i]['lignes']=[];
      for ($li=0;$li<count($heats[$i]['lignes']);$li++){
        $heats[$i]['lignes'][$li]['juge']='';
        $heats[$i]['lignes'][$li]['build']='';
      }
    }
  }

  // scope = seulement ce start
  $scope = [['day'=>$d,'start'=>$s]];

  // pools & avail
  [$judges,$builders,$all,$availMap,$users] = build_pools_and_avail();

  $assignedJ=[]; $assignedB=[];
  $busyTime=[]; $lunchSlotsJ=[]; $lunchSlotsB=[];

  assign_over_scope($heats,$scope,$builders,'build',$assignedB,$busyTime,$lunchSlotsB,$availMap);
  assign_over_scope($heats,$scope,$judges,'juge',$assignedJ,$busyTime,$lunchSlotsJ,$availMap);

  // Backfill relaxé pour le start si trou restant
  $poolAll = build_global_active_pool($judges,$builders,$all,$users);
  backfill_role($heats, $builders, $poolAll, 'build', $assignedB, $busyTime, $availMap);
  backfill_role($heats, $judges,   $poolAll, 'juge',  $assignedJ, $busyTime, $availMap);
}

/* =========================================================
 * Helpers pour wrappers
 * ======================================================= */
function engine_load_heats(): array {
  $heats = _read_json(__DIR__.'/_heats.json', []);
  return is_array($heats) ? $heats : [];
}
function engine_save_heats(array $heats): void {
  _write_json(__DIR__.'/_heats.json', $heats);
}
