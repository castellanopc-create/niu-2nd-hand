<?php
// ---------------------------------------------------------------------------
// Checklist di valutazione NIU — BOZZA da validare con NIU After-Sales.
// Ogni voce: id => [testo dealer, peso, categorie (null = tutte), domanda cliente (null = solo in officina)]
// Esito: ok (0), usura (peso), difetto (peso x 3), na (esclusa)
// ---------------------------------------------------------------------------
return [
    'Documenti e identificazione' => [
        'doc_libretto'  => ['Carta di circolazione / certificato di conformità presente', 3, null, 'Hai la carta di circolazione del veicolo?'],
        'doc_telaio'    => ['Numero di telaio leggibile e corrispondente ai documenti', 5, null, null],
        'doc_chiavi'    => ['Chiavi / NFC card / accessi app consegnati', 2, null, 'Hai tutte le chiavi / NFC card?'],
        'doc_service'   => ['Libretto tagliandi o storico interventi disponibile', 2, null, 'Hai fatto i tagliandi presso un centro NIU?'],
    ],
    'Telaio e carrozzeria' => [
        'body_scratch'  => ['Carene e plastiche senza graffi evidenti', 1, null, 'Le carene sono senza graffi evidenti?'],
        'body_cracks'   => ['Nessuna crepa o rottura su carene, pedana, parafanghi', 3, null, 'Le carene sono senza crepe o parti rotte?'],
        'body_frame'    => ['Telaio senza deformazioni, saldature o segni di incidente', 6, null, 'Il veicolo non ha mai avuto incidenti?'],
    ],
    'Batteria e ricarica' => [
        'batt_case'     => ['Involucro batteria integro, nessun segno di urto o infiltrazione', 6, null, 'La batteria è integra, senza urti o tracce d\'acqua?'],
        'batt_charger'  => ['Caricabatterie originale presente e funzionante', 2, null, 'Hai il caricabatterie originale funzionante?'],
        'batt_connect'  => ['Connettori e porta di ricarica puliti e senza ossidazione', 2, null, null],
    ],
    'Motore e trasmissione' => [
        'mot_noise'     => ['Nessun rumore anomalo dal motore', 4, null, 'Il motore è silenzioso, senza rumori strani?'],
        'mot_power'     => ['Erogazione regolare, nessuna perdita di potenza', 4, null, 'Il veicolo accelera come da nuovo?'],
        'mot_errors'    => ['Nessun codice errore attivo (diagnosi app / tool NIU)', 4, null, 'L\'app NIU non segnala errori?'],
        'mot_belt'      => ['Cinghia / catena in buono stato', 2, ['moto'], null],
    ],
    'Freni e sospensioni' => [
        'brk_pads'      => ['Pastiglie / ganasce con spessore sufficiente', 3, null, null],
        'brk_discs'     => ['Dischi senza rigature profonde o deformazioni', 2, null, null],
        'brk_cbs'       => ['CBS / ABS funzionante (se presente)', 3, null, 'I freni funzionano bene?'],
        'susp_leak'     => ['Sospensioni senza perdite d\'olio e con ritorno corretto', 3, null, null],
    ],
    'Ruote e pneumatici' => [
        'tyre_tread'    => ['Battistrada sopra il limite legale, nessun taglio', 2, null, 'Le gomme sono in buono stato?'],
        'tyre_rims'     => ['Cerchi senza ammaccature o crepe', 3, null, 'I cerchi sono senza ammaccature?'],
    ],
    'Elettronica e connettività' => [
        'ele_display'   => ['Display / cruscotto funzionante', 3, null, 'Il display funziona correttamente?'],
        'ele_lights'    => ['Luci, frecce, stop e clacson funzionanti', 3, null, 'Luci, frecce e clacson funzionano?'],
        'ele_iot'       => ['Modulo IoT / app NIU collegabile al nuovo proprietario', 2, null, null],
        'ele_fw'        => ['Firmware aggiornato all\'ultima versione', 1, null, null],
    ],
    'Prova su strada' => [
        'road_test'     => ['Prova su strada senza anomalie (sterzo, freni, stabilità)', 5, null, null],
    ],
];
