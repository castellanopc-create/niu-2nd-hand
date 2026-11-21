<?php
// ---------------------------------------------------------------------------
// Multilingua: il testo italiano è la chiave, app/lang/<code>.php la traduzione.
// Lingua: ?lang=xx  →  cookie  →  lingua del browser  →  inglese.
// Attivo solo con 'i18n_enabled' => true in config.php.
// ---------------------------------------------------------------------------
const LANGS = ['it' => 'Italiano', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español', 'nl' => 'Nederlands'];

function detect_lang(): string
{
    global $CFG;
    if (empty($CFG['i18n_enabled'])) return 'it';   // flag spento: solo italiano
    $q = $_GET['lang'] ?? null;
    if ($q && isset(LANGS[$q])) {
        setcookie('niu2h_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        return $q;
    }
    $c = $_COOKIE['niu2h_lang'] ?? null;
    if ($c && isset(LANGS[$c])) return $c;
    foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $part) {
        $code = strtolower(substr(trim($part), 0, 2));
        if (isset(LANGS[$code])) return $code;
    }
    return 'en';
}

$LANG = detect_lang();
$TR = $LANG === 'it' ? [] : (is_file(__DIR__ . "/lang/$LANG.php") ? require __DIR__ . "/lang/$LANG.php" : []);

/** Traduce un testo italiano. {segnaposto} sostituiti con $vars. */
function t(string $s, array $vars = []): string
{
    global $TR;
    $out = $TR[$s] ?? $s;
    foreach ($vars as $k => $v) $out = str_replace('{' . $k . '}', (string)$v, $out);
    return $out;
}

function lang(): string { global $LANG; return $LANG; }

/** URL della pagina corrente con un'altra lingua */
function lang_url(string $code): string
{
    $q = $_GET; $q['lang'] = $code;
    return '?' . http_build_query($q);
}
