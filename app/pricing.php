<?php
// ---------------------------------------------------------------------------
// Valore indicativo dell'usato NIU e scomposizione degli incentivi.
// Valori di partenza: listino Italia 2026 (fonte NIU Italia); curva di valore
// e fattori di classe sono STIME da tarare con i dati reali del pilot.
// Tutto è modificabile dall'admin in Impostazioni.
// ---------------------------------------------------------------------------

const DEFAULT_USED_VALUE = [
    // Prezzo di listino IVA inclusa del modello (o del suo successore) in €
    'prices' => [
        'FQi 500' => 3799, 'FQiX 150' => 2499, 'FQiX 300' => 2999, 'FQiX 500' => 4699,
        'MQi GT 100' => 2999, 'MQi GT 45' => 2999, 'MQi GT 70' => 2999, 'MQi+' => 2299,
        'NQi GTS' => 2499, 'NQi GTS MY26' => 2699, 'NQi Sport' => 1999, 'NQi Sport MY26' => 1999,
        'NQiX 150' => 2699, 'NQiX 150 MY26' => 2799, 'NQiX 300' => 3599, 'NQiX 300 MY26' => 3599,
        'NQiX 500' => 4499, 'NQiX 500 MY26' => 4599, 'UQi GT' => 2299, 'RQi Sport' => 6999,
        'XQi3 Street' => 3999, 'XQi3 Wild' => 3999,
    ],
    // Prezzo della batteria sostitutiva al pubblico, per la detrazione con SOH basso
    'battery' => [
        'FQi 500' => 600, 'FQiX 150' => 499, 'FQiX 300' => 499, 'FQiX 500' => 849,
        'MQi GT 100' => 650, 'MQi GT 45' => 650, 'MQi GT 70' => 650, 'MQi+' => 600,
        'NQi GTS' => 600, 'NQi GTS MY26' => 499, 'NQi Sport' => 600, 'NQi Sport MY26' => 499,
        'NQiX 150' => 499, 'NQiX 150 MY26' => 499, 'NQiX 300' => 499, 'NQiX 300 MY26' => 499,
        'NQiX 500' => 849, 'NQiX 500 MY26' => 849, 'UQi GT' => 600, 'RQi Sport' => 1200,
        'XQi3 Street' => 1200,
    ],
    // Valore di ritiro in % del listino, per età in anni (8 = 8 anni e oltre) — STIMA
    'curve' => [0 => 60, 1 => 55, 2 => 48, 3 => 42, 4 => 37, 5 => 33, 6 => 29, 7 => 25, 8 => 20],
    // Fattore per classe di valutazione, in % — STIMA
    'grade' => ['A' => 100, 'B' => 90, 'C' => 75, 'D' => 30],
    'km_per_year' => 4000,   // percorrenza attesa: +/- 1% ogni 1.000 km di scarto (da -15% a +5%)
    'soh_min'     => 80,     // sotto questo SOH si toglie metà del prezzo della batteria
    'spread'      => 10,     // ampiezza della forchetta mostrata, in % (+/-)
];

function used_value_cfg(): array
{
    global $USED_VALUE;
    return $USED_VALUE ?? DEFAULT_USED_VALUE;
}

/**
 * Valore indicativo di ritiro dell'usato.
 * @return array{low:int, mid:int, high:int, list:int}|null  null se il modello non ha un prezzo di listino
 */
function used_value(string $model, ?int $year, int $km, string $grade, ?int $soh, ?int $thisYear = null): ?array
{
    $c = used_value_cfg();
    $list = (int)($c['prices'][$model] ?? 0);
    if ($list <= 0 || !$year) return null;
    $age = max(0, ($thisYear ?? (int)date('Y')) - $year);
    $pct = (float)($c['curve'][min($age, 8)] ?? 20) / 100;
    $value = $list * $pct * (float)($c['grade'][$grade] ?? 100) / 100;
    $expected = $c['km_per_year'] * max(1, $age);
    $kmAdj = max(-0.15, min(0.05, -($km - $expected) / 1000 * 0.01));
    $value *= 1 + $kmAdj;
    if ($soh !== null && $soh < $c['soh_min']) $value -= ($c['battery'][$model] ?? 0) / 2;
    $value = max(0, $value);
    $s = $c['spread'] / 100;
    $r = fn($x) => (int)(round($x / 10) * 10);
    return ['low' => $r($value * (1 - $s)), 'mid' => $r($value), 'high' => $r($value * (1 + $s)), 'list' => $list];
}

/** Incentivi scomposti: chi paga cosa. */
function incentives_enabled(): bool
{
    global $CFG;
    return !empty($CFG['incentives_enabled']);
}

/** Fascia di extra permuta per il prezzo di listino del NIU nuovo. */
function trade_up_band(?int $newPrice): array
{
    global $CFG;
    if (!$newPrice) return ['niu' => 0, 'dealer' => 0];
    foreach ($CFG['incentive_trade_up'] as $b) if ($b['up_to'] === null || $newPrice <= $b['up_to']) return ['niu' => (int)$b['niu'], 'dealer' => (int)$b['dealer']];
    return ['niu' => 0, 'dealer' => 0];
}

function new_price(?string $model): ?int
{
    $p = $model ? (int)(used_value_cfg()['prices'][$model] ?? 0) : 0;
    return $p > 0 ? $p : null;
}

/**
 * Premi incrociati: premio sul ritiro per classe dell'usato + extra permuta per fascia di prezzo del NIU nuovo.
 */
function incentive_parts(string $cat, string $grade, ?string $newModel = null): array
{
    global $CFG;
    if (!incentives_enabled()) return ['premio_ritiro' => 0, 'permuta_niu' => 0, 'permuta_dealer' => 0];
    $band = $grade !== 'D' ? trade_up_band(new_price($newModel)) : ['niu' => 0, 'dealer' => 0];
    return [
        'premio_ritiro' => (int)($CFG['incentive_trade_in'][$cat][$grade] ?? 0),   // NIU, su ogni ritiro (anche senza permuta)
        'permuta_niu'   => $band['niu'],                                            // NIU, solo con acquisto NIU nuovo
        'permuta_dealer'=> $band['dealer'],                                         // dealer, solo con acquisto NIU nuovo
    ];
}

/**
 * Quotazione indicativa per il cliente: valore dell'usato + premio NIU e, se sceglie un NIU nuovo, prezzo finale.
 * @param array $s dati della stima (model, category, year, km, grade, soh)
 */
function build_quote(array $s, ?string $newModel, string $today): array
{
    global $CFG;
    $uv = used_value($s['model'], $s['year'] ?? null, (int)$s['km'], $s['grade'], $s['soh'] ?? null);
    $ip = incentive_parts($s['category'], $s['grade'], $newModel);
    $q = ['value_low' => $uv['low'] ?? null, 'value_mid' => $uv['mid'] ?? null, 'value_high' => $uv['high'] ?? null,
          'premio' => $ip['premio_ritiro'], 'perm_niu' => $ip['permuta_niu'], 'perm_dealer' => $ip['permuta_dealer'],
          'new_model' => null, 'list' => null, 'ecobonus' => 0, 'final' => null];
    $list = $newModel ? (int)(used_value_cfg()['prices'][$newModel] ?? 0) : 0;
    if ($list > 0 && $s['grade'] !== 'D') {
        $eco = ecobonus_estimate($list, $today, $CFG['ecobonus_rates']);
        $q = array_merge($q, ['new_model' => $newModel, 'list' => $list, 'ecobonus' => $eco,
            'final' => max(0, $list - (int)$q['value_mid'] - $q['premio'] - $q['perm_niu'] - $q['perm_dealer'] - $eco)]);
    }
    return $q;
}
