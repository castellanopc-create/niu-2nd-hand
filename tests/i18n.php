<?php
// Controlla le traduzioni: ogni testo passato a t() deve esistere in ogni file app/lang/*.php,
// con gli stessi {segnaposto} e gli stessi tag HTML. Senza file di lingua il test viene saltato.
declare(strict_types=1);
$root = dirname(__DIR__);
$langs = glob("$root/app/lang/*.php");
if (!$langs) { echo "Nessun file di lingua: test saltato\n"; exit(0); }

$keys = [];
foreach (['public/index.php', 'app/layout.php', 'app/bootstrap.php'] as $f) {
    $toks = token_get_all(file_get_contents("$root/$f")); $n = count($toks);
    for ($i = 0; $i < $n - 2; $i++) {
        if (is_array($toks[$i]) && $toks[$i][0] === T_STRING && $toks[$i][1] === 't' && $toks[$i + 1] === '('
            && is_array($toks[$i + 2]) && $toks[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
            $raw = $toks[$i + 2][1];
            $keys[$raw[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($raw, 1, -1)) : stripcslashes(substr($raw, 1, -1))] = $f;
        }
    }
}
$cfg = require "$root/app/config.php";
foreach ($cfg['categories'] as $v) $keys[$v] = 'config';
foreach ($cfg['grades'] as $g) $keys[$g['label']] = 'config';
foreach (require "$root/app/checklist.php" as $sec => $items) {
    $keys[$sec] = 'checklist';
    foreach ($items as $it) { $keys[$it[0]] = 'checklist'; if (!empty($it[3])) $keys[$it[3]] = 'checklist'; }
}
$keys['Stato di salute batteria sotto il 70%'] = 'bootstrap';
$keys['Vincolo Ecobonus: non rivendibile per 12 mesi dall\'immatricolazione'] = 'index';
// Testi che restano uguali in tutte le lingue
foreach (['NIU', 'km', 'OK', 'Email', 'Dashboard', 'Dealer', 'DEALER', '×', 'app/config.php'] as $same) unset($keys[$same]);

$fails = 0;
$sig = function (string $s): array {
    preg_match_all('/\{\w+\}/', $s, $a); preg_match_all('#</?\w+>#', $s, $b);
    sort($a[0]); sort($b[0]);
    return [$a[0], $b[0]];
};
foreach ($langs as $file) {
    $code = basename($file, '.php'); $tr = require $file; $miss = [];
    foreach ($keys as $k => $where) {
        if (!array_key_exists($k, $tr)) { $miss[] = "$k  ($where)"; continue; }
        if ($sig($k) != $sig($tr[$k])) { echo "  FAIL [$code] segnaposto o tag diversi: $k\n"; $fails++; }
    }
    foreach ($miss as $m) echo "  FAIL [$code] manca la traduzione: $m\n";
    $fails += count($miss);
    echo "  " . ($miss ? 'FAIL' : 'ok  ') . " $code: " . (count($keys) - count($miss)) . "/" . count($keys) . " testi tradotti\n";
}
exit($fails ? 1 : 0);
