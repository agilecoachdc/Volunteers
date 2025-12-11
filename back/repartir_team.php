<?php
// api/repartir_team.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Règles:
 * - Attribution des équipes CONSÉCUTIVE par ordre alphabétique:
 *   heat 1 ligne 1, ligne 2, ..., puis heat 2, etc.
 * - WOD1 & WOD2 : toutes les équipes (catégorie ignorée).
 * - WOD>=3 : par catégorie :
 *      Régular -> heats 1..3
 *      Inter   -> heats 4..5
 *      RX      -> heats 6..8
 * - On n’écrase que le champ `equipe` dans les lignes (on NE TOUCHE PAS à `juge` / `build`).
 * - On renvoie { ok:true, heats:[...] } et on persiste dans _heats.json
 */

// ---------- IO helpers ----------
function read_json(string $fn, $fallback) {
  if (!file_exists($fn)) return $fallback;
  $j = json_decode(@file_get_contents($fn), true);
  return is_array($j) ? $j : $fallback;
}
function write_json(string $fn, $data): void {
  @file_put_contents($fn, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

// ---------- Normalisation équipes ----------
function norm_cat($s) {
  $s = (string)$s;
  $sl = mb_strtolower(trim($s), 'UTF-8');
  if ($sl === '' || $sl === '—' || $sl === '-') return '';
  if (in_array($sl, ['regular','régular','regulat','reg'], true)) return 'Régular';
  if (in_array($sl, ['inter','intermediaire','intermédiaire'], true)) return 'Inter';
  if ($sl === 'rx' || $sl === 'r x') return 'RX';
  if (in_array($s, ['Régular','Inter','RX'], true)) return $s;
  return '';
}
function norm_team($t) {
  return [
    'id'   => (string)($t['id'] ?? ''),
    'name' => trim((string)($t['name'] ?? '')),
    'cat'  => norm_cat($t['cat'] ?? ''),
  ];
}

/** Tri alpha insensible à la casse (accents neutralisés grossièrement via strtolower) */
function cmp_team_name(array $a, array $b): int {
  $na = mb_strtolower($a['name'] ?? '', 'UTF-8');
  $nb = mb_strtolower($b['name'] ?? '', 'UTF-8');
  return $na <=> $nb;
}

// ---------- Chargement données ----------
$teamsWrap = read_json(__DIR__.'/teams.json', ['teams'=>[]]);
$teams = [];
if (isset($teamsWrap['teams']) && is_array($teamsWrap['teams'])) $teams = $teamsWrap['teams'];
elseif (array_is_list($teamsWrap)) $teams = $teamsWrap; // tolère un array direct

$teams = array_values(array_map('norm_team', array_filter($teams, fn($t)=> !!trim((string)($t['name']??'')) )));
usort($teams, 'cmp_team_name'); // tri global par nom (utilisé pour WOD1/2)

$heats = read_json(__DIR__.'/_heats.json', []);

// ---------- Index des heats par WOD ----------
$byWod = [];
foreach ($heats as $idx => $h) {
  $w = (string)($h['wod'] ?? '');
  if ($w === '') continue;
  if (!isset($heats[$idx]['lignes']) || !is_array($heats[$idx]['lignes'])) $heats[$idx]['lignes'] = [];
  $byWod[$w][] = $idx;
}
foreach ($byWod as $w => &$arrIdx) {
  // trier par numéro de heat croissant
  usort($arrIdx, function($a,$b) use ($heats){
    $ha = (int)($heats[$a]['heat'] ?? $heats[$a]['numero'] ?? 0);
    $hb = (int)($heats[$b]['heat'] ?? $heats[$b]['numero'] ?? 0);
    return $ha <=> $hb;
  });
}
unset($arrIdx);

// ---------- utilitaires ----------
/**
 * Construit la liste des slots (heat index + line index) dans l’ordre:
 *  heat1 ligne1, ligne2, ..., heat2 ligne1, ...
 */
function build_slots(array $heats, array $heatIdxList): array {
  $slots = [];
  foreach ($heatIdxList as $hi) {
    $n = count($heats[$hi]['lignes']);
    for ($li=0; $li<$n; $li++) $slots[] = [$hi,$li];
  }
  return $slots;
}

/** Attribue les teams (déjà triées) séquentiellement dans les slots fournis */
function assign_into(array &$heats, array $heatIdxList, array $teamsList): void {
  $slots = build_slots($heats, $heatIdxList);
  $S = count($slots);
  if ($S === 0) return;

  $k = 0;
  foreach ($teamsList as $t) {
    [$hi,$li] = $slots[$k];
    // NE PAS toucher juge/build
    $heats[$hi]['lignes'][$li]['equipe'] = $t['name'];
    $k++;
    if ($k >= $S) break; // si plus d'équipes que de slots, on s'arrête
  }
}

// ---------- Répartition ----------
foreach ($byWod as $wod => $idxList) {
  // 1) vider uniquement le champ equipe pour ce WOD
  foreach ($idxList as $hi) {
    foreach ($heats[$hi]['lignes'] as $li => $ln) {
      $heats[$hi]['lignes'][$li]['equipe'] = '';
    }
  }

  // 2) déterminer le mode (WOD1/2 vs WOD>=3)
  $isWod1or2 = false;
  if (preg_match('/^WOD\s*([0-9]+)/i', $wod, $m)) {
    $num = (int)$m[1];
    $isWod1or2 = ($num === 1 || $num === 2);
  }

  if ($isWod1or2) {
    // WOD1 & WOD2 : toutes les équipes triées par nom, attribution séquentielle
    assign_into($heats, $idxList, $teams);
  } else {
    // WOD>=3 : par catégorie, chacune triée par nom
    $reg = []; $inter = []; $rx = [];
    foreach ($teams as $t) {
      switch ($t['cat']) {
        case 'Régular': $reg[] = $t; break;
        case 'Inter':   $inter[] = $t; break;
        case 'RX':      $rx[] = $t; break;
        default: /* sans catégorie -> ignorée pour WOD>=3 */ break;
      }
    }
    usort($reg, 'cmp_team_name');
    usort($inter, 'cmp_team_name');
    usort($rx, 'cmp_team_name');

    // partitionner idxList par numéro de heat
    $h1_3 = []; $h4_5 = []; $h6_8 = [];
    foreach ($idxList as $hi) {
      $n = (int)($heats[$hi]['heat'] ?? $heats[$hi]['numero'] ?? 0);
      if ($n >= 1 && $n <= 3) $h1_3[] = $hi;
      elseif ($n >= 4 && $n <= 5) $h4_5[] = $hi;
      elseif ($n >= 6 && $n <= 8) $h6_8[] = $hi;
    }

    assign_into($heats, $h1_3, $reg);
    assign_into($heats, $h4_5, $inter);
    assign_into($heats, $h6_8, $rx);
  }
}

// ---------- Sauvegarde & sortie ----------
write_json(__DIR__.'/_heats.json', $heats);
echo json_encode(['ok'=>true, 'heats'=>$heats], JSON_UNESCAPED_UNICODE);
