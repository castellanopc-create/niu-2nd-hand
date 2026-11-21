# CLAUDE.md — NIU 2nd Hand

Regole per chi lavora su questo repository (persone e Claude). Seguono il corso
**Develop Yourself**: una sessione = un branch = una modifica.

## Cos'è
Prototipo NIU 2nd Hand: valutazione dell'usato NIU (dealer e cliente), extra NIU,
appuntamenti, annunci con foto, vetrina pubblica e feed JSON per global.niu.com.
PHP 8.1+ e SQLite, nessuna dipendenza esterna.

## Struttura
- `public/` — servito dal sandbox (`index.php` router + `assets/`)
- `app/` — logica non pubblica: `bootstrap.php`, `config.php` (importi, flag), `checklist.php`,
  `portal.php` (oggi SIMULATO), `models.php`, `i18n.php`, `lang/<codice>.php`
- `shared/` — creato a runtime sul server (database e foto). Mai nel repository.
- `tests/` — `smoke.php` (flussi end-to-end), `i18n.php` (chiavi di traduzione), `secret-scan.sh`

## La strada di ogni modifica
1. `git fetch origin` → parti da `main` aggiornato → `git switch -c feature/<nome>` (oppure `fix/`, `chore/`).
2. Un solo scopo per branch, circa **400 righe** al massimo (le traduzioni contano come file a sé).
3. Lavoro non finito → dietro un flag in `app/config.php`, **spento** di default.
4. Prima del push: `php tests/smoke.php && php tests/i18n.php && bash tests/secret-scan.sh`.
5. Push del branch e PR verso `main` con il template compilato.
6. Si unisce solo con **tutti i gate verdi**. Unire su `main` = deploy automatico sul sandbox.

## Gate su ogni PR (`.github/workflows/ci.yml`)
`up-to-date-with-main`, `php-lint`, `secret-scan`, `smoke-tests`, `i18n-keys`, `phpstan`.
Se uno è rosso si corregge il codice, mai il controllo.

## Deploy
`.github/workflows/sandbox.yml` spedisce `main` sul sandbox, poi il **health gate** controlla il sito;
se fallisce rispedisce la versione precedente e segnala l'errore.

## Multilingua
- Ogni testo visibile passa da `t('testo italiano')`; con segnaposto: `t('Ciao {nome}', ['nome' => $x])`.
- Ogni nuovo testo va aggiunto in **tutti** i file `app/lang/*.php` (EN, FR, DE, ES, NL):
  il gate `i18n-keys` blocca la PR se ne manca uno.
- Titoli con evidenziazione: una sola chiave, es. `t('NUOVA <em>VALUTAZIONE</em>')`.

## Mai
- Push diretto su `main`, modifiche a mano sul server, deploy fuori dalla pipeline.
- Dati reali di clienti (nomi, telefoni, telai veri) in codice, test, screenshot o chat.
- Segreti nel repository: chiavi e token solo nei secret di GitHub.
- Cambiare `.github/`, `CLAUDE.md` o `CODEOWNERS` senza l'owner.
- Unire una PR con un gate rosso, o due modifiche nello stesso branch.
