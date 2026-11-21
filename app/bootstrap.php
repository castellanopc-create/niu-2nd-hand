<?php
declare(strict_types=1);

$CFG       = require __DIR__ . '/config.php';
$MODELS    = require __DIR__ . '/models.php';
$CHECKLIST = require __DIR__ . '/checklist.php';
require __DIR__ . '/portal.php';
require __DIR__ . '/i18n.php';

// --- Sessione -------------------------------------------------------------
$https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_name('niu2h');
session_start();

// --- Storage persistente --------------------------------------------------
$SHARED = $CFG['shared_dir'];
foreach ([$SHARED, "$SHARED/uploads"] as $d) {
    if (!is_dir($d)) @mkdir($d, 0775, true);
}
// Impedisce l'accesso diretto se shared/ finisse sotto la document root
if (!file_exists("$SHARED/.htaccess")) @file_put_contents("$SHARED/.htaccess", "Require all denied\n");

function db(): PDO
{
    static $pdo = null;
    global $SHARED;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . $SHARED . '/app.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, pass TEXT NOT NULL,
        role TEXT NOT NULL CHECK (role IN ('niu','dealer')),
        name TEXT NOT NULL, company TEXT, city TEXT, active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS valuations (
        id INTEGER PRIMARY KEY, dealer_id INTEGER NOT NULL REFERENCES users(id),
        vin TEXT NOT NULL, category TEXT NOT NULL, model TEXT NOT NULL, year INTEGER, km INTEGER,
        battery_soh INTEGER, portal_json TEXT, answers_json TEXT,
        score INTEGER, grade TEXT, blockers TEXT,
        trade_in_price INTEGER, new_purchase INTEGER NOT NULL DEFAULT 0, new_model TEXT,
        incentive_niu INTEGER NOT NULL DEFAULT 0, incentive_dealer INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'valutato', notes TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS listings (
        id INTEGER PRIMARY KEY, dealer_id INTEGER NOT NULL REFERENCES users(id),
        valuation_id INTEGER REFERENCES valuations(id),
        model TEXT NOT NULL, category TEXT NOT NULL, year INTEGER, km INTEGER, grade TEXT,
        title TEXT NOT NULL, description TEXT NOT NULL, price INTEGER NOT NULL,
        city TEXT NOT NULL, province TEXT, contact TEXT,
        status TEXT NOT NULL DEFAULT 'bozza'
            CHECK (status IN ('bozza','in_revisione','pubblicato','rifiutato','venduto','ritirato')),
        review_note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT, published_at TEXT);
    CREATE TABLE IF NOT EXISTS photos (
        id INTEGER PRIMARY KEY, listing_id INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
        file TEXT NOT NULL, sort INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS requests (
        id INTEGER PRIMARY KEY, token TEXT UNIQUE NOT NULL,
        dealer_id INTEGER NOT NULL REFERENCES users(id),
        name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL,
        vin TEXT NOT NULL, category TEXT NOT NULL, model TEXT NOT NULL, year INTEGER, km INTEGER,
        battery_soh INTEGER, answers_json TEXT, score INTEGER, grade TEXT, blockers TEXT,
        wants_new INTEGER NOT NULL DEFAULT 0, new_model TEXT,
        est_niu INTEGER NOT NULL DEFAULT 0, est_dealer INTEGER NOT NULL DEFAULT 0,
        appt_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'richiesto'
            CHECK (status IN ('richiesto','confermato','annullato','completato')),
        dealer_note TEXT, valuation_id INTEGER REFERENCES valuations(id),
        consent_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
    CREATE UNIQUE INDEX IF NOT EXISTS req_slot ON requests(dealer_id, appt_at) WHERE status IN ('richiesto','confermato');
    SQL);
    // Migrazioni leggere per database già esistenti
    $add = function (string $table, string $col, string $def) use ($pdo) {
        $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    };
    $add('users', 'address', 'TEXT');
    $add('users', 'phone', 'TEXT');
    $add('valuations', 'request_id', 'INTEGER');
    $add('users', 'must_change', 'INTEGER NOT NULL DEFAULT 0');   // password temporanea da cambiare
    $add('users', 'reset_requested_at', 'TEXT');                   // richiesta "password dimenticata"
    return $pdo;
}

// --- Helper ---------------------------------------------------------------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nf($n): string { return lang() === 'en' ? number_format((float)$n, 0, '.', ',') : number_format((float)$n, 0, ',', (lang() === 'fr' ? "\u{202F}" : '.')); }
function eur($n): string { return match (lang()) { 'en', 'nl' => '€' . (lang() === 'nl' ? ' ' : '') . nf($n), 'it' => '€ ' . nf($n), default => nf($n) . ' €' }; }
function url(string $p, array $q = []): string { return '?' . http_build_query(['p' => $p] + $q); }
function redirect(string $p, array $q = []): never { header('Location: ' . url($p, $q)); exit; }
function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function csrf(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400); exit('Sessione scaduta, ricarica la pagina.');
    }
}
function user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
            $st->execute([$_SESSION['uid']]);
            $u = $st->fetch() ?: null;
        }
    }
    return $u;
}
function require_login(?string $role = null): array
{
    $u = user();
    if (!$u) redirect('login');
    if (!empty($u['must_change'])) redirect('password');   // prima va cambiata la password temporanea
    if ($role && $u['role'] !== $role) { http_response_code(403); exit('Accesso non consentito.'); }
    return $u;
}
function temp_password(): string
{
    return substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14);
}
function base_url(): string
{
    global $https;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return ($https ? 'https' : 'http') . "://$host$path/";
}
function category_of(string $model): string
{
    global $MODELS;
    foreach ($MODELS as $cat => $list) if (in_array($model, $list, true)) return $cat;
    return 'moped';
}
function grade_for(int $score): string
{
    global $CFG;
    foreach ($CFG['grades'] as $g => $def) if ($score >= $def['min']) return $g;
    return 'D';
}

// Esiti che, se "difetto", rendono il veicolo non idoneo a prescindere dal punteggio
const BLOCKERS = ['doc_telaio', 'body_frame', 'batt_case'];

function evaluate(array $answers, string $cat, ?int $soh): array
{
    global $CHECKLIST;
    $penalty = 0; $blockers = [];
    foreach ($CHECKLIST as $items) {
        foreach ($items as $id => [$label, $w, $cats]) {
            if ($cats && !in_array($cat, $cats, true)) continue;
            $a = $answers[$id] ?? 'na';
            if ($a === 'usura')   $penalty += $w;
            if ($a === 'difetto') { $penalty += $w * 3; if (in_array($id, BLOCKERS, true)) $blockers[] = $label; }
        }
    }
    // Stato di salute batteria (SOH)
    if ($soh !== null) {
        $penalty += match (true) { $soh >= 90 => 0, $soh >= 80 => 4, $soh >= 70 => 10, default => 20 };
        if ($soh < 70) $blockers[] = 'Stato di salute batteria sotto il 70%';
    }

    $score = max(0, 100 - $penalty);
    $grade = $blockers ? 'D' : grade_for($score);
    return [$score, $grade, $blockers];
}

function incentives(string $cat, string $grade, bool $newPurchase): array
{
    global $CFG;
    $niu = $CFG['incentive_trade_in'][$cat][$grade] ?? 0;
    $dealer = 0;
    if ($newPurchase && $grade !== 'D') {
        $niu    += $CFG['incentive_new_purchase'][$cat]['niu'];
        $dealer  = $CFG['incentive_new_purchase'][$cat]['dealer'];
    }
    return [$niu, $dealer];
}

// --- Foto: validazione, ridimensionamento, rimozione EXIF/GPS -------------
function save_photo(array $f): ?string
{
    global $CFG, $SHARED;
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($f['size'] > $CFG['photos']['max_bytes']) return null;
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;

    $name = bin2hex(random_bytes(12)) . '.jpg';
    $dest = "$SHARED/uploads/$name";

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring(file_get_contents($f['tmp_name']));
        if (!$img) return null;
        // Corregge l'orientamento delle foto da smartphone
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($f['tmp_name'])['Orientation'] ?? 1;
            $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        $w = imagesx($img); $hgt = imagesy($img); $max = $CFG['photos']['max_side'];
        if (max($w, $hgt) > $max) {
            $r = $max / max($w, $hgt);
            $img = imagescale($img, (int)round($w * $r), (int)round($hgt * $r));
        }
        $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagejpeg($bg, $dest, 85);   // la ricodifica elimina EXIF e coordinate GPS
        return $name;
    }
    // Senza GD: salva solo JPEG così come sono
    if ($info[2] !== IMAGETYPE_JPEG) return null;
    return move_uploaded_file($f['tmp_name'], $dest) ? $name : null;
}

function listing_photos(int $id): array
{
    $st = db()->prepare('SELECT * FROM photos WHERE listing_id = ? ORDER BY sort, id');
    $st->execute([$id]);
    return $st->fetchAll();
}

// --- Appuntamenti -----------------------------------------------------------
function free_slots(int $dealerId): array
{
    global $CFG;
    $a = $CFG['appointments'];
    $st = db()->prepare("SELECT appt_at FROM requests WHERE dealer_id = ? AND status IN ('richiesto','confermato') AND appt_at >= date('now')");
    $st->execute([$dealerId]);
    $taken = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $out = [];
    for ($d = $a['min_days']; $d <= $a['max_days']; $d++) {
        $ts = strtotime("+$d day");
        if (!in_array((int)date('N', $ts), $a['weekdays'], true)) continue;
        $day = date('Y-m-d', $ts);
        foreach ($a['slots'] as $t) if (!isset($taken["$day $t"])) $out[$day][] = $t;
    }
    return $out;
}
function it_date(string $dt, bool $time = true): string
{
    $g = ['', t('lun'), t('mar'), t('mer'), t('gio'), t('ven'), t('sab'), t('dom')];
    $ts = strtotime($dt);
    return $g[(int)date('N', $ts)] . ' ' . date('d/m/Y', $ts) . ($time ? ' ' . t('ore') . ' ' . date('H:i', $ts) : '');
}
function dealers(): array
{
    return db()->query("SELECT id, company, city, address, phone FROM users WHERE role = 'dealer' AND active = 1 ORDER BY city, company")->fetchAll();
}
