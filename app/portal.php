<?php
// ---------------------------------------------------------------------------
// Interfaccia verso il portale NIU (garanzie, richiami, tagliandi).
// OGGI: dati SIMULATI e deterministici a partire dal telaio, solo per demo.
// DOMANI: sostituire il corpo di portal_lookup() con la chiamata alla API
// reale del portale NIU, mantenendo la stessa struttura di ritorno.
// ---------------------------------------------------------------------------

function portal_lookup(string $vin): array
{
    $vin = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $vin));
    $h = hash('sha256', $vin);
    $n = fn(int $i, int $mod) => hexdec(substr($h, $i * 2, 4)) % $mod;

    $year  = 2019 + $n(0, 7);                  // 2019–2025
    $month = 1 + $n(1, 12);
    $sold  = sprintf('%04d-%02d-%02d', $year, $month, 1 + $n(2, 28));
    $vehicleWarrantyEnd = date('Y-m-d', strtotime("$sold +2 years"));
    $batteryWarrantyEnd = date('Y-m-d', strtotime("$sold +3 years"));
    $today = date('Y-m-d');

    $campaigns = [];
    if ($n(3, 5) === 0) {
        $campaigns[] = ['code' => 'SC-' . (100 + $n(4, 900)), 'title' => 'Campagna di aggiornamento firmware', 'done' => (bool)$n(5, 2)];
    }

    return [
        'simulated'            => true,
        'vin'                  => $vin,
        'found'                => true,
        'first_sale_date'      => $sold,
        'country'              => ['IT', 'DE', 'FR', 'ES', 'NL'][$n(6, 5)],
        'vehicle_warranty_end' => $vehicleWarrantyEnd,
        'vehicle_warranty_ok'  => $vehicleWarrantyEnd >= $today,
        'battery_warranty_end' => $batteryWarrantyEnd,
        'battery_warranty_ok'  => $batteryWarrantyEnd >= $today,
        'last_service_date'    => min($today, date('Y-m-d', strtotime("$sold +" . (6 + $n(7, 30)) . ' months'))),
        'odometer_last_km'     => 500 + $n(8, 15000),
        'stolen_flag'          => false,
        'campaigns'            => $campaigns,
    ];
}
