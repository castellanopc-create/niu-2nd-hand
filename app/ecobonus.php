<?php
// ---------------------------------------------------------------------------
// Vincolo Ecobonus (regole dal 2027): un veicolo immatricolato dal 1/1/2027
// e acquistato con Ecobonus non può essere rivenduto per 12 mesi.
// I veicoli acquistati con Ecobonus dal 2023 al 2026 non hanno vincolo.
// ---------------------------------------------------------------------------

/**
 * @return array{status: string, until: ?string}
 *   status: 'ok' (nessun vincolo), 'locked' (vincolo attivo fino a 'until'), 'missing' (manca la risposta Ecobonus)
 */
function ecobonus_check(?string $regDate, string $ecobonus, string $from, int $months, string $today): array
{
    if (!$regDate || $regDate < $from) return ['status' => 'ok', 'until' => null];
    if ($ecobonus === 'no') return ['status' => 'ok', 'until' => null];
    if ($ecobonus !== 'si') return ['status' => 'missing', 'until' => null];
    $until = date('Y-m-d', strtotime("$regDate +$months months"));
    return $today < $until ? ['status' => 'locked', 'until' => $until] : ['status' => 'ok', 'until' => $until];
}

/**
 * Ecobonus statale stimato sull'acquisto di un veicolo nuovo (senza rottamazione).
 * Il contributo è una % del prezzo IVA esclusa, con un tetto, ed è soggetto alla disponibilità dei fondi.
 */
function ecobonus_estimate(int $priceInclVat, string $date, array $rates): int
{
    foreach ($rates as $r) {
        if ($date >= $r['from'] && $date <= $r['until']) return (int)round(min($r['cap'], $priceInclVat / 1.22 * $r['pct'] / 100));
    }
    return 0;
}
