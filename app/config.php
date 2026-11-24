<?php
// ---------------------------------------------------------------------------
// NIU 2nd Hand — configurazione
// Tutti i valori economici qui sotto sono ESEMPI da validare con il team NIU.
// ---------------------------------------------------------------------------

return [
    // Cartella persistente: sul sandbox "shared/" sopravvive ai deploy.
    // Ordine di ricerca: variabile d'ambiente, ../shared accanto a public/.
    'shared_dir' => getenv('NIU2H_SHARED') ?: dirname(__DIR__) . '/shared',

    'app_name'   => 'NIU 2nd Hand',

    // Multilingua (IT, EN, FR, DE, ES, NL). Spento = solo italiano, nessun selettore lingua.
    'i18n_enabled' => true,

    // Foto annunci
    'photos' => [
        'min'       => 3,
        'max'       => 10,
        'max_bytes' => 8 * 1024 * 1024,
        'max_side'  => 1600,   // px, lato lungo dopo il ridimensionamento
    ],

    // Categorie veicolo (usate per incentivi e checklist)
    'categories' => [
        'moped' => 'Scooter (NQi / NQiX / MQi / UQi / FQi)',
        'moto'  => 'Moto (RQi / XQi)',
    ],

    // Appuntamenti di valutazione in concessionaria
    'appointments' => [
        'weekdays'  => [1, 2, 3, 4, 5, 6],                 // 1 = lunedì … 7 = domenica
        'slots'     => ['09:30', '10:30', '11:30', '14:30', '15:30', '16:30', '17:30'],
        'min_days'  => 1,                                  // prenotabile da domani
        'max_days'  => 21,
    ],

    // Vincolo Ecobonus: immatricolati da 'from' e acquistati con Ecobonus non rivendibili per 'months' mesi
    'ecobonus_lock' => ['from' => '2027-01-01', 'months' => 12],

    // Ecobonus statale senza rottamazione: % del prezzo IVA esclusa, con tetto (soggetto a disponibilità dei fondi)
    'ecobonus_rates' => [
        ['from' => '2026-01-01', 'until' => '2026-12-31', 'pct' => 30, 'cap' => 3000],
        ['from' => '2027-01-01', 'until' => '2030-12-31', 'pct' => 20, 'cap' => 2000],
    ],

    // Valutazione: soglie di punteggio -> classe
    'grades' => [
        'A' => ['min' => 90, 'label' => 'Ottimo — certificabile NIU'],
        'B' => ['min' => 75, 'label' => 'Buono — certificabile con piccoli interventi'],
        'C' => ['min' => 55, 'label' => 'Discreto — richiede ricondizionamento'],
        'D' => ['min' => 0,  'label' => 'Non idoneo al programma 2nd Hand'],
    ],

    // Extra NIU al dealer per il ritiro, per categoria e classe (EUR) — ESEMPIO
    'incentive_trade_in' => [
        'moped' => ['A' => 150, 'B' => 100, 'C' => 50,  'D' => 0],
        'moto'  => ['A' => 300, 'B' => 200, 'C' => 100, 'D' => 0],
    ],

    // Supporto aggiuntivo se il cliente acquista un NIU nuovo (EUR) — ESEMPIO
    // niu = quota NIU, dealer = quota suggerita al dealer
    'incentive_new_purchase' => [
        'moped' => ['niu' => 200, 'dealer' => 100],
        'moto'  => ['niu' => 400, 'dealer' => 200],
    ],
];
