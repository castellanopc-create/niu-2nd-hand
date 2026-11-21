<?php
// Smoke test end-to-end: avvia il sito su un server PHP locale con un database vuoto
// e percorre i flussi principali (admin, dealer, cliente, vetrina, feed).
// Uso: php tests/smoke.php   (exit 0 = verde)
declare(strict_types=1);

$root = dirname(__DIR__);
$shared = sys_get_temp_dir() . '/niu2h-smoke-' . bin2hex(random_bytes(4));
mkdir($shared, 0777, true);
$port = 18000 + random_int(0, 999);
$cmd = sprintf('NIU2H_SHARED=%s exec php -S 127.0.0.1:%d -t %s', escapeshellarg($shared), $port, escapeshellarg("$root/public"));
$proc = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', "$shared/server.log", 'w']], $pipes);
register_shutdown_function(function () use ($proc) { proc_terminate($proc); });
// aspetta che il server risponda (max 10 s)
for ($i = 0; $i < 100; $i++) {
    $fp = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
    if ($fp) { fclose($fp); break; }
    usleep(100000);
}

$base = "http://127.0.0.1:$port/";
$fails = 0;
function ok(bool $c, string $msg): void
{
    global $fails;
    echo ($c ? "  ok   " : "  FAIL ") . $msg . "\n";
    if (!$c) { $fails++; if (getenv('GITHUB_ACTIONS')) echo "::error title=smoke-tests::$msg\n"; }
}

final class Client {
    public array $cookies = [];
    public function __construct(private string $base, private string $lang = 'it') {}
    public function req(string $path, ?array $post = null): array {
        $h = ["Accept-Language: {$this->lang}"];
        if ($this->cookies) $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $opt = ['method' => $post === null ? 'GET' : 'POST', 'header' => $h, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10];
        if ($post !== null) { $opt['header'][] = 'Content-Type: application/x-www-form-urlencoded'; $opt['content'] = http_build_query($post); }
        $body = @file_get_contents($this->base . ltrim($path, '/'), false, stream_context_create(['http' => $opt]));
        $status = 0; $loc = null;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) $status = (int)$m[1];
            if (stripos($line, 'Location:') === 0) $loc = trim(substr($line, 9));
            if (stripos($line, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $this->cookies[$m[1]] = $m[2];
        }
        if ($loc !== null && $status >= 300 && $status < 400) return $this->req(ltrim($loc, '/'));
        return [$status, (string)$body];
    }
    public function csrf(string $html): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m); return $m[1] ?? ''; }
}

echo "Smoke test on $base\n";
$admin = new Client($base);
[$s, $b] = $admin->req('');
ok($s === 200 && str_contains($b, 'name="password"'), 'primo avvio mostra il form di configurazione');
[$s, $b] = $admin->req('?p=setup', ['csrf' => $admin->csrf($b), 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'smoke-password-123']);
ok($s === 200 && str_contains($b, 'name="company"'), 'account NIU creato, pagina dealer');
[$s, $b] = $admin->req('?p=dealer', ['csrf' => $admin->csrf($b), 'company' => 'Dealer Test', 'name' => 'Mario Prova', 'city' => 'Milano', 'address' => 'Via Finta 1', 'phone' => '000', 'email' => 'dealer@example.test']);
preg_match('/dealer@example\.test: (\S+) /', $b, $m);
$pw = $m[1] ?? '';
ok($pw !== '', 'dealer creato con password iniziale');

$dealer = new Client($base);
[, $b] = $dealer->req('?p=login');
[$s, $b] = $dealer->req('?p=login', ['csrf' => $dealer->csrf($b), 'email' => 'dealer@example.test', 'password' => $pw]);
ok($s === 200 && str_contains($b, 'name="confirm"'), 'primo login dealer: obbligo di cambiare la password temporanea');
[$s, $b] = $dealer->req('?p=valuta');
ok(str_contains($b, 'name="confirm"'), 'con password temporanea le altre pagine restano bloccate');
[$s, $b] = $dealer->req('?p=password', ['csrf' => $dealer->csrf($b), 'current' => $pw, 'new' => 'nuova-password-dealer', 'confirm' => 'nuova-password-dealer']);
ok($s === 200 && str_contains($b, '?p=valuta'), 'password cambiata, accesso alla dashboard');

// Password dimenticata -> richiesta visibile all'admin -> nuova password temporanea
$lost = new Client($base);
[, $b] = $lost->req('?p=password_dimenticata');
[$s, $b] = $lost->req('?p=password_dimenticata', ['csrf' => $lost->csrf($b), 'email' => 'dealer@example.test']);
ok($s === 200 && str_contains($b, 'name="password"'), 'richiesta password dimenticata inviata');
[, $b] = $lost->req('?p=password_dimenticata');
[, $b2] = $lost->req('?p=password_dimenticata', ['csrf' => $lost->csrf($b), 'email' => 'nessuno@example.test']);
ok(str_contains($b2, 'name="password"'), 'email sconosciuta: stessa risposta, nessuna informazione rivelata');
[, $b] = $admin->req('?p=dealer');
ok(str_contains($b, 'badge warn'), 'admin vede la richiesta di reset');
preg_match('/name="action" value="reset"><input type="hidden" name="id" value="(\d+)"/', $b, $m);
[, $b] = $admin->req('?p=dealer', ['csrf' => $admin->csrf($b), 'action' => 'reset', 'id' => $m[1] ?? 0]);
preg_match('/dealer@example\.test: (\S+) /', $b, $m);
$tmp = $m[1] ?? '';
ok($tmp !== '' && !str_contains($b, 'badge warn'), 'admin genera la nuova password temporanea');
$dealer2 = new Client($base);
[, $b] = $dealer2->req('?p=login');
[$s, $b] = $dealer2->req('?p=login', ['csrf' => $dealer2->csrf($b), 'email' => 'dealer@example.test', 'password' => 'nuova-password-dealer']);
ok(str_contains($b, 'name="password"') && !str_contains($b, '?p=valuta'), 'la vecchia password non funziona più');
[$s, $b] = $dealer2->req('?p=login', ['csrf' => $dealer2->csrf($b), 'email' => 'dealer@example.test', 'password' => $tmp]);
ok(str_contains($b, 'name="confirm"'), 'login con password temporanea → cambio obbligatorio');
[$s, $b] = $dealer2->req('?p=password', ['csrf' => $dealer2->csrf($b), 'current' => $tmp, 'new' => 'altra-password-dealer', 'confirm' => 'altra-password-dealer']);
ok(str_contains($b, '?p=valuta'), 'nuova password impostata dopo il reset');
$dealer = $dealer2;

// Impostazioni admin: modifica importi e checklist, verifica l'effetto sulla valutazione
[$s, $b] = $admin->req('?p=impostazioni');
ok($s === 200 && str_contains($b, 'name="ti[moped][A]"'), 'pagina impostazioni admin');
$form = [];
preg_match_all('/<input type="number"[^>]*name="([^"]+)" value="(\d+)"/', $b, $m, PREG_SET_ORDER);
foreach ($m as [, $n, $v]) $form[$n] = $v;
preg_match_all('/<input type="checkbox" name="([^"]+)" value="1" checked>/', $b, $m);
foreach ($m[1] as $n) $form[$n] = '1';
$form['ti[moped][A]'] = '777';
unset($form['cl[ele_fw][on]']);
parse_str(http_build_query($form), $post);
[$s, $b] = $admin->req('?p=impostazioni', ['csrf' => $admin->csrf($b)] + $post);
ok(str_contains($b, 'value="777"') && !str_contains($b, 'name="cl[ele_fw][on]" value="1" checked'), 'impostazioni salvate');
$bad = $post; $bad['gm']['B'] = 99;
[, $b] = $admin->req('?p=impostazioni', ['csrf' => $admin->csrf($b)] + $bad);
ok(str_contains($b, 'flash err'), 'soglie non decrescenti rifiutate');
[$s, $b] = $dealer->req('?p=valuta&vin=TESTVIN0000000001');
ok($s === 200 && str_contains($b, 'battery_soh'), 'form valutazione con dati portale');
ok(!str_contains($b, 'a[ele_fw]'), 'voce disattivata dall\'admin assente dalla checklist');
$answers = [];
preg_match_all('/name="a\[(\w+)\]"/', $b, $m);
foreach (array_unique($m[1]) as $id) $answers[$id] = 'ok';
[$s, $b] = $dealer->req('?p=valuta', ['csrf' => $dealer->csrf($b), 'vin' => 'TESTVIN0000000001', 'model' => 'NQi GTS MY26', 'year' => 2024, 'km' => 1200, 'battery_soh' => 95, 'a' => $answers, 'notes' => '']);
ok($s === 200 && str_contains($b, 'big-grade g-A'), 'valutazione calcolata (classe A)');
ok(str_contains($b, '777'), 'la valutazione usa l\'extra NIU impostato dall\'admin');

$cust = new Client($base);
[$s, $b] = $cust->req('?p=stima');
ok($s === 200 && str_contains($b, 'name="vin"'), 'pagina autovalutazione cliente');
[$s, $b] = $cust->req('?p=stima', ['csrf' => $cust->csrf($b), 'action' => 'calcola', 'vin' => 'TESTVIN0000000002', 'model' => 'NQi Sport', 'km' => 5000, 'wants_new' => 1, 'new_model' => 'NQi GTS MY26']);
ok($s === 200 && str_contains($b, 'big-grade'), 'stima cliente calcolata');
[$s, $b] = $cust->req('?p=stima&step=2&dealer=2');
preg_match('/name="slot" value="([^"]+)"/', $b, $m);
ok(isset($m[1]), 'orari disponibili del dealer');
[$s, $b] = $cust->req('?p=stima', ['csrf' => $cust->csrf($b), 'action' => 'prenota', 'dealer' => 2, 'slot' => $m[1] ?? '', 'name' => 'Cliente Prova', 'email' => 'cliente@example.test', 'phone' => '000000', 'privacy' => 1]);
ok($s === 200 && str_contains($b, 'name="action" value="annulla"'), 'prenotazione creata, pagina richiesta');

[$s, $b] = (new Client($base))->req('?p=usato');
ok($s === 200, 'vetrina pubblica');
[$s, $b] = (new Client($base))->req('?p=feed');
$j = json_decode($b, true);
ok($s === 200 && is_array($j) && array_key_exists('items', $j), 'feed JSON valido');
[$s] = (new Client($base))->req('?p=pagina-inesistente');
ok($s === 404, 'pagina inesistente = 404');

$log = (string)@file_get_contents("$shared/server.log");
ok(!preg_match('/PHP (Warning|Fatal|Parse|Deprecated)/', $log), 'nessun warning o errore PHP nel log del server');
if ($fails) {
    echo $log;
    if (getenv('GITHUB_ACTIONS')) foreach (array_slice(array_filter(explode("\n", $log), fn($l) => str_contains($l, 'PHP ')), 0, 5) as $l) echo "::error title=server log::" . trim($l) . "\n";
}
echo $fails ? "\n$fails test falliti\n" : "\nTutti i test verdi\n";
exit($fails ? 1 : 0);
