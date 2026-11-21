<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/layout.php';

check_csrf();
$p = $_GET['p'] ?? (user() ? 'home' : 'usato');
$POST = $_SERVER['REQUEST_METHOD'] === 'POST';

// Primo avvio: nessun utente -> crea l'account amministratore NIU
$hasUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
if (!$hasUsers && !in_array($p, ['setup', 'img'], true)) redirect('setup');

switch ($p) {

// ===========================================================================
case 'setup':
    if ($hasUsers) redirect('login');
    if ($POST) {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) {
            flash(t('Email valida e password di almeno 10 caratteri.'), 'err'); redirect('setup');
        }
        db()->prepare('INSERT INTO users (email, pass, role, name, company) VALUES (?,?,?,?,?)')
            ->execute([$email, password_hash($pw, PASSWORD_DEFAULT), 'niu', trim($_POST['name'] ?? 'NIU Admin'), 'NIU']);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        flash(t('Account NIU creato. Ora aggiungi i dealer.'));
        redirect('dealer');
    }
    page_start(t('Configurazione iniziale')); ?>
    <section class="card narrow">
      <h1><?= t('PRIMO <em>AVVIO</em>') ?></h1>
      <p><?= t('Crea l\'account amministratore NIU. Potrà creare i dealer e approvare gli annunci.') ?></p>
      <form method="post"><?= csrf() ?>
        <label><?= t('Nome') ?> <input name="name" required value="NIU Admin"></label>
        <label><?= t('Email') ?> <input type="email" name="email" required></label>
        <label><?= t('Password (min. 10 caratteri)') ?> <input type="password" name="password" minlength="10" required></label>
        <button class="btn"><?= t('Crea account') ?></button>
      </form>
    </section>
    <?php page_end(); break;

// ===========================================================================
case 'login':
    if ($POST) {
        $st = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
        $st->execute([trim(strtolower($_POST['email'] ?? ''))]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['pass'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            redirect($u['must_change'] ? 'password' : 'home');
        }
        usleep(400000);
        flash(t('Credenziali non valide.'), 'err'); redirect('login');
    }
    page_start(t('Accesso')); ?>
    <section class="card narrow">
      <h1><?= t('AREA <em>DEALER</em>') ?></h1>
      <form method="post"><?= csrf() ?>
        <label><?= t('Email') ?> <input type="email" name="email" required autofocus></label>
        <label><?= t('Password') ?> <input type="password" name="password" required></label>
        <button class="btn"><?= t('Accedi') ?></button>
      </form>
      <p><a href="<?= url('password_dimenticata') ?>"><?= t('Password dimenticata?') ?></a></p>
    </section>
    <?php page_end(); break;

// ===========================================================================
case 'password_dimenticata':
    if ($POST) {
        if (empty($_POST['website'])) {   // honeypot anti-spam
            db()->prepare("UPDATE users SET reset_requested_at = datetime('now') WHERE email = ? AND role = 'dealer' AND active = 1")
                ->execute([trim(strtolower($_POST['email'] ?? ''))]);
        }
        usleep(300000);
        // Stesso messaggio in ogni caso: non rivela quali email sono registrate
        flash(t('Se l\'email è registrata, il team NIU ti invierà una nuova password temporanea.'));
        redirect('login');
    }
    page_start(t('Password dimenticata?')); ?>
    <section class="card narrow">
      <h1><?= t('PASSWORD <em>DIMENTICATA</em>') ?></h1>
      <p><?= t('Inserisci l\'email del tuo account dealer: il team NIU riceverà la richiesta e ti invierà una password temporanea.') ?></p>
      <form method="post"><?= csrf() ?>
        <input name="website" class="hidden" tabindex="-1" autocomplete="off">
        <label><?= t('Email') ?> <input type="email" name="email" required autofocus></label>
        <button class="btn"><?= t('Invia richiesta') ?></button>
      </form>
      <p><a href="<?= url('login') ?>"><?= t('← Torna all\'accesso') ?></a></p>
    </section>
    <?php page_end(); break;

case 'password':
    $u = user();
    if (!$u) redirect('login');
    if ($POST) {
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $u['pass'])) { flash(t('La password attuale non è corretta.'), 'err'); redirect('password'); }
        if (strlen($new) < 10 || $new !== (string)($_POST['confirm'] ?? '')) { flash(t('La nuova password deve avere almeno 10 caratteri e coincidere nei due campi.'), 'err'); redirect('password'); }
        if (password_verify($new, $u['pass'])) { flash(t('La nuova password deve essere diversa da quella attuale.'), 'err'); redirect('password'); }
        db()->prepare('UPDATE users SET pass = ?, must_change = 0, reset_requested_at = NULL WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash(t('Password aggiornata.')); redirect('home');
    }
    page_start(t('Cambia password')); ?>
    <section class="card narrow">
      <h1><?= t('CAMBIA <em>PASSWORD</em>') ?></h1>
      <?php if ($u['must_change']): ?><div class="flash"><?= t('Stai usando una password temporanea: scegline una nuova per continuare.') ?></div><?php endif; ?>
      <form method="post"><?= csrf() ?>
        <label><?= t('Password attuale') ?> <input type="password" name="current" required autocomplete="current-password"></label>
        <label><?= t('Nuova password (min. 10 caratteri)') ?> <input type="password" name="new" minlength="10" required autocomplete="new-password"></label>
        <label><?= t('Ripeti la nuova password') ?> <input type="password" name="confirm" minlength="10" required autocomplete="new-password"></label>
        <button class="btn"><?= t('Salva password') ?></button>
      </form>
    </section>
    <?php page_end(); break;

case 'logout':
    if ($POST) { $_SESSION = []; session_destroy(); }
    redirect('usato');

// ===========================================================================
case 'home':
    $u = require_login();
    page_start(t('Dashboard'));
    if ($u['role'] === 'dealer') {
        $st = db()->prepare('SELECT * FROM valuations WHERE dealer_id = ? ORDER BY id DESC LIMIT 10');
        $st->execute([$u['id']]); $vals = $st->fetchAll();
        $st = db()->prepare('SELECT status, COUNT(*) n FROM listings WHERE dealer_id = ? GROUP BY status');
        $st->execute([$u['id']]); $counts = array_column($st->fetchAll(), 'n', 'status');
        $st = db()->prepare("SELECT COALESCE(SUM(incentive_niu),0) FROM valuations WHERE dealer_id = ? AND status = 'ritirato'");
        $st->execute([$u['id']]); $extra = $st->fetchColumn();
        $st = db()->prepare("SELECT * FROM requests WHERE dealer_id = ? AND status IN ('richiesto','confermato') ORDER BY appt_at LIMIT 10");
        $st->execute([$u['id']]); $appts = $st->fetchAll(); ?>
        <h1><?= t('CIAO') ?> <em><?= h($u['company'] ?: $u['name']) ?></em></h1>
        <div class="kpis">
          <div><b><?= count($appts) ?></b><span><?= t('Appuntamenti clienti') ?></span></div>
          <div><b><?= count($vals) ?></b><span><?= t('Valutazioni recenti') ?></span></div>
          <div><b><?= (int)($counts['pubblicato'] ?? 0) ?></b><span><?= t('Annunci online') ?></span></div>
          <div><b><?= (int)($counts['in_revisione'] ?? 0) ?></b><span><?= t('In revisione NIU') ?></span></div>
          <div><b><?= eur($extra) ?></b><span><?= t('Extra NIU maturati') ?></span></div>
        </div>
        <p><a class="btn" href="<?= url('valuta') ?>"><?= t('+ Nuova valutazione') ?></a></p>
        <?php if ($appts): ?><h2><?= t('Prossimi appuntamenti') ?></h2><?php render_requests($appts, false); endif; ?>
        <h2><?= t('Ultime valutazioni') ?></h2>
        <?php render_valuations($vals, false);
    } else {
        $q = fn($sql) => db()->query($sql)->fetchColumn(); ?>
        <h1><?= t('DASHBOARD <em>NIU</em>') ?></h1>
        <div class="kpis">
          <div><b><?= $q("SELECT COUNT(*) FROM listings WHERE status='in_revisione'") ?></b><span><?= t('Annunci da revisionare') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM listings WHERE status='pubblicato'") ?></b><span><?= t('Annunci online') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM valuations") ?></b><span><?= t('Valutazioni totali') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM requests") ?></b><span><?= t('Autovalutazioni clienti') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM requests WHERE wants_new = 1") ?></b><span><?= t('Clienti interessati a NIU nuovo') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM valuations WHERE new_purchase=1 AND status='ritirato'") ?></b><span><?= t('Permute su NIU nuovo') ?></span></div>
          <div><b><?= eur($q("SELECT COALESCE(SUM(incentive_niu),0) FROM valuations WHERE status='ritirato'")) ?></b><span><?= t('Extra NIU impegnati') ?></span></div>
          <div><b><?= $q("SELECT COUNT(*) FROM users WHERE role='dealer' AND active=1") ?></b><span><?= t('Dealer attivi') ?></span></div>
        </div>
        <p><a class="btn" href="<?= url('revisione') ?>"><?= t('Vai alla revisione annunci') ?></a></p>
        <h2><?= t('Ultime valutazioni') ?></h2>
        <?php render_valuations(db()->query('SELECT v.*, u.company FROM valuations v JOIN users u ON u.id=v.dealer_id ORDER BY v.id DESC LIMIT 10')->fetchAll(), true);
    }
    page_end(); break;

// ===========================================================================
case 'valuta':
    $u = require_login('dealer');
    if ($POST) {
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['vin'] ?? ''));
        $model = (string)($_POST['model'] ?? '');
        if ($model === '__other') {
            $model = trim($_POST['other_model'] ?? '');
            $cat = array_key_exists($_POST['other_cat'] ?? '', $CFG['categories']) ? $_POST['other_cat'] : 'moped';
        } else {
            $cat = category_of($model);
        }
        $soh = max(0, min(100, (int)($_POST['battery_soh'] ?? 0)));
        $answers = array_map(fn($a) => in_array($a, ['ok', 'usura', 'difetto', 'na'], true) ? $a : 'na', (array)($_POST['a'] ?? []));
        if (strlen($vin) < 8 || $model === '') { flash(t('Telaio e modello sono obbligatori.'), 'err'); redirect('valuta', ['vin' => $vin]); }

        [$score, $grade, $blockers] = evaluate($answers, $cat, $soh);
        $portal = portal_lookup($vin);
        $reqId = null;
        if ($rid = (int)($_POST['req'] ?? 0)) {
            $st = db()->prepare("SELECT id FROM requests WHERE id = ? AND dealer_id = ? AND status IN ('richiesto','confermato')");
            $st->execute([$rid, $u['id']]); $reqId = $st->fetchColumn() ?: null;
        }
        db()->prepare('INSERT INTO valuations (dealer_id, vin, category, model, year, km, battery_soh, portal_json, answers_json, score, grade, blockers, notes, request_id)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$u['id'], $vin, $cat, $model, (int)($_POST['year'] ?? 0) ?: null, (int)($_POST['km'] ?? 0), $soh,
                       json_encode($portal), json_encode($answers), $score, $grade, implode("\n", $blockers), trim($_POST['notes'] ?? ''), $reqId]);
        $vid = db()->lastInsertId();
        if ($reqId) db()->prepare("UPDATE requests SET status = 'completato', valuation_id = ? WHERE id = ?")->execute([$vid, $reqId]);
        redirect('valutazione', ['id' => $vid]);
    }

    $R = null;
    if ($rid = (int)($_GET['req'] ?? 0)) {
        $st = db()->prepare("SELECT * FROM requests WHERE id = ? AND dealer_id = ? AND status IN ('richiesto','confermato')");
        $st->execute([$rid, $u['id']]); $R = $st->fetch() ?: null;
    }
    $vin = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_GET['vin'] ?? ($R['vin'] ?? '')));
    $pre = $R ? json_decode($R['answers_json'] ?? '{}', true) : [];
    page_start(t('Nuova valutazione')); ?>
    <h1><?= t('NUOVA <em>VALUTAZIONE</em>') ?></h1>
    <?php if ($R): ?><div class="flash"><?= t('Valutazione in officina per la richiesta di <b>{name}</b> ({date}). Le risposte del cliente sono precompilate: <b>verificale una per una</b>.', ['name' => h($R['name']), 'date' => h(it_date($R['appt_at']))]) ?></div><?php endif; ?>
    <section class="card">
      <h2><?= t('1 · Telaio') ?></h2>
      <form method="get" class="row">
        <input type="hidden" name="p" value="valuta"><?php if ($R): ?><input type="hidden" name="req" value="<?= $R['id'] ?>"><?php endif; ?>
        <input name="vin" value="<?= h($vin) ?>" placeholder="<?= h(t('Numero di telaio (VIN)')) ?>" minlength="8" maxlength="20" required>
        <button class="btn"><?= t('Recupera dati dal portale') ?></button>
      </form>
    </section>
    <?php if (strlen($vin) >= 8):
        $d = portal_lookup($vin); ?>
    <section class="card">
      <h2><?= t('2 · Dati dal portale NIU') ?> <?php if ($d['simulated']): ?><span class="badge warn"><?= t('SIMULATI') ?></span><?php endif; ?></h2>
      <?php render_portal($d); ?>
    </section>
    <form method="post" class="card"><?= csrf() ?>
      <input type="hidden" name="vin" value="<?= h($vin) ?>"><?php if ($R): ?><input type="hidden" name="req" value="<?= $R['id'] ?>"><?php endif; ?>
      <h2><?= t('3 · Veicolo') ?></h2>
      <div class="grid3">
        <label><?= t('Modello') ?>
          <select name="model" id="model" required>
            <option value=""><?= t('— scegli —') ?></option>
            <?php foreach ($CFG['categories'] as $c => $cl): ?>
              <optgroup label="<?= h(t($cl)) ?>"><?php foreach ($MODELS[$c] as $m): ?><option data-cat="<?= $c ?>" <?= ($R['model'] ?? '') === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></optgroup>
            <?php endforeach; ?>
            <option value="__other"><?= t('Altro modello NIU (fuori listino)…') ?></option>
          </select>
        </label>
        <label><?= t('Anno') ?> <input type="number" name="year" min="2015" max="<?= date('Y') ?>" value="<?= $R['year'] ?? substr($d['first_sale_date'], 0, 4) ?>"></label>
        <label><?= t('Chilometri') ?> <input type="number" name="km" min="0" required value="<?= h((string)($R['km'] ?? '')) ?>"></label>
      </div>
      <div class="grid3 hidden" id="other">
        <label><?= t('Nome modello') ?> <input name="other_model"></label>
        <label><?= t('Categoria') ?> <select name="other_cat"><?php foreach ($CFG['categories'] as $c => $cl): ?><option value="<?= $c ?>"><?= h(t($cl)) ?></option><?php endforeach; ?></select></label>
      </div>

      <h2><?= t('4 · Checklist NIU') ?></h2>
      <p class="muted"><?= t('Per ogni voce: <b>OK</b>, <b>Usura</b> (normale per l\'età), <b>Difetto</b> (da riparare), <b>N/A</b>. Le voci non pertinenti al tipo di veicolo vengono ignorate.') ?></p>
      <label class="soh"><?= t('Stato di salute batteria (SOH, %) — da app NIU o tool diagnostico') ?>
        <input type="number" name="battery_soh" min="0" max="100" required value="<?= h((string)($R['battery_soh'] ?? '')) ?>">
      </label>
      <?php foreach ($CHECKLIST as $section => $items): ?>
        <fieldset><legend><?= h(t($section)) ?></legend>
        <?php foreach ($items as $id => [$label, $w, $cats]): ?>
          <div class="chk" data-cats="<?= h(implode(',', $cats ?? [])) ?>">
            <span><?= h(t($label)) ?><?php if (in_array($id, blockers(), true)): ?> <small class="red"><?= t('• bloccante') ?></small><?php endif; ?></span>
            <div class="seg">
              <?php foreach (['ok' => t('OK'), 'usura' => t('Usura'), 'difetto' => t('Difetto'), 'na' => t('N/A')] as $v => $l): ?>
                <label><input type="radio" name="a[<?= $id ?>]" value="<?= $v ?>" <?= $v === (isset($pre[$id]) && $pre[$id] !== 'na' ? $pre[$id] : 'ok') ? 'checked' : '' ?>><span><?= $l ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
      <label><?= t('Note del tecnico') ?> <textarea name="notes" rows="3"></textarea></label>
      <button class="btn"><?= t('Calcola valutazione') ?></button>
    </form>
    <script>
    const sel = document.getElementById('model'), other = document.getElementById('other');
    function sync() {
      const o = sel.selectedOptions[0];
      other.classList.toggle('hidden', sel.value !== '__other');
      const cat = sel.value === '__other' ? document.querySelector('[name=other_cat]').value : (o && o.dataset.cat);
      document.querySelectorAll('.chk').forEach(el => {
        const cats = el.dataset.cats; el.classList.toggle('hidden', !!cats && !!cat && !cats.split(',').includes(cat));
      });
    }
    sel.addEventListener('change', sync); document.querySelector('[name=other_cat]').addEventListener('change', sync); sync();
    </script>
    <?php endif;
    page_end(); break;

// ===========================================================================
case 'valutazione':
    $u = require_login();
    $st = db()->prepare('SELECT v.*, u.company FROM valuations v JOIN users u ON u.id = v.dealer_id WHERE v.id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $v = $st->fetch();
    if (!$v || ($u['role'] === 'dealer' && (int)$v['dealer_id'] !== (int)$u['id'])) { http_response_code(404); exit(t('Non trovato')); }

    if ($POST && $u['role'] === 'dealer') {
        $new = !empty($_POST['new_purchase']);
        [$niu, $dealer] = incentives($v['category'], $v['grade'], $new);
        $status = ($_POST['action'] ?? '') === 'ritira' ? 'ritirato' : 'non_ritirato';
        db()->prepare('UPDATE valuations SET trade_in_price=?, new_purchase=?, new_model=?, incentive_niu=?, incentive_dealer=?, status=? WHERE id=?')
            ->execute([(int)($_POST['trade_in_price'] ?? 0) ?: null, $new ? 1 : 0, $new ? ($_POST['new_model'] ?? null) : null,
                       $status === 'ritirato' ? $niu : 0, $status === 'ritirato' ? $dealer : 0, $status, $v['id']]);
        flash($status === 'ritirato' ? t('Ritiro registrato.') : t('Valutazione archiviata senza ritiro.'));
        redirect('valutazione', ['id' => $v['id']]);
    }

    $portal = json_decode($v['portal_json'] ?? '{}', true);
    $answers = json_decode($v['answers_json'] ?? '{}', true);
    [$niuPrev, $dealerPrev] = incentives($v['category'], $v['grade'], false);
    [$niuNew, $dealerNew]   = incentives($v['category'], $v['grade'], true);
    page_start(t('Valutazione') . ' #' . $v['id']); ?>
    <h1><?= t('VALUTAZIONE') ?> <em>#<?= $v['id'] ?></em></h1>
    <div class="result">
      <div class="big-grade g-<?= h($v['grade']) ?>"><?= h($v['grade']) ?></div>
      <div>
        <h2><?= h($v['model']) ?> · <?= h((string)$v['year']) ?> · <?= nf((int)$v['km']) ?> <?= t('km') ?></h2>
        <p><b><?= (int)$v['score'] ?>/100</b> — <?= h(t($CFG['grades'][$v['grade']]['label'])) ?></p>
        <p class="muted"><?= t('Telaio') ?> <?= h($v['vin']) ?> <?= t('· SOH batteria') ?> <?= (int)$v['battery_soh'] ?><?= t('% · Dealer') ?> <?= h($v['company']) ?> · <?= h($v['created_at']) ?></p>
        <?php if ($v['blockers']): ?><div class="flash err"><b><?= t('Motivi di non idoneità:') ?></b><br><?= nl2br(h(implode("\n", array_map('t', explode("\n", $v['blockers']))))) ?></div><?php endif; ?>
      </div>
    </div>

    <?php if ($v['status'] === 'valutato' && $u['role'] === 'dealer'): ?>
    <form method="post" class="card"><?= csrf() ?>
      <h2><?= t('Ritiro e supporto NIU') ?></h2>
      <div class="split">
        <div class="offer"><span><?= t('Ritiro semplice') ?></span><b><?= eur($niuPrev) ?></b><small><?= t('extra NIU al dealer') ?></small></div>
        <div class="offer hl"><span><?= t('Ritiro + acquisto NIU nuovo') ?></span><b><?= eur($niuNew) ?></b><small><?= t('extra NIU + {amount} suggeriti dal dealer al cliente', ['amount' => eur($dealerNew)]) ?></small></div>
      </div>
      <p class="muted"><?= t('Importi definiti dal team NIU in Impostazioni.') ?></p>
      <div class="grid3">
        <label><?= t('Prezzo di ritiro offerto al cliente (€)') ?> <input type="number" name="trade_in_price" min="0"></label>
        <label class="check"><input type="checkbox" name="new_purchase" value="1" id="np"> <?= t('Il cliente acquista un NIU nuovo') ?></label>
        <label><?= t('Modello nuovo') ?>
          <select name="new_model"><option value="">—</option><?php foreach (array_keys($CFG['categories']) as $c) foreach ($MODELS[$c] as $m): ?><option><?= h($m) ?></option><?php endforeach; ?></select>
        </label>
      </div>
      <div class="row">
        <button class="btn" name="action" value="ritira" ><?= t('Registra ritiro') ?></button>
        <button class="btn ghost" name="action" value="no"><?= t('Non ritirato') ?></button>
      </div>
    </form>
    <?php else: ?>
    <section class="card">
      <h2><?= t('Esito') ?></h2>
      <p><?= t('Stato:') ?> <b><?= $v['status'] === 'ritirato' ? t('Ritirato dal dealer') : ($v['status'] === 'valutato' ? t('In attesa di decisione') : t('Non ritirato')) ?></b></p>
      <?php if ($v['status'] === 'ritirato'): ?>
        <p><?= t('Prezzo di ritiro:') ?> <b><?= $v['trade_in_price'] ? eur($v['trade_in_price']) : '—' ?></b>
        <?= t('· Acquisto NIU nuovo:') ?> <b><?= $v['new_purchase'] ? 'Sì — ' . h($v['new_model'] ?: t('modello n.d.')) : t('No') ?></b></p>
        <p><?= t('Extra NIU:') ?> <b><?= eur($v['incentive_niu']) ?></b> <?= t('· Supporto dealer:') ?> <b><?= eur($v['incentive_dealer']) ?></b></p>
        <?php if ($u['role'] === 'dealer' && $v['grade'] !== 'D'):
            $st = db()->prepare('SELECT id FROM listings WHERE valuation_id = ?'); $st->execute([$v['id']]); $lid = $st->fetchColumn(); ?>
          <a class="btn" href="<?= $lid ? url('annuncio_edit', ['id' => $lid]) : url('annuncio_edit', ['from' => $v['id']]) ?>"><?= $lid ? t('Apri annuncio') : t('Crea annuncio 2nd Hand') ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <details class="card"><summary><?= t('Dati portale e checklist compilata') ?></summary>
      <?php render_portal($portal); ?>
      <table class="tbl">
      <?php foreach ($CHECKLIST as $section => $items): foreach ($items as $id => [$label]): if (!isset($answers[$id])) continue; ?>
        <tr><td class="muted"><?= h(t($section)) ?></td><td><?= h(t($label)) ?></td><td><span class="ans a-<?= h($answers[$id]) ?>"><?= h(mb_strtoupper(t(['ok' => 'OK', 'usura' => 'Usura', 'difetto' => 'Difetto', 'na' => 'N/A'][$answers[$id]] ?? $answers[$id]))) ?></span></td></tr>
      <?php endforeach; endforeach; ?>
      </table>
      <?php if ($v['notes']): ?><p><b><?= t('Note:') ?></b> <?= nl2br(h($v['notes'])) ?></p><?php endif; ?>
    </details>
    <?php page_end(); break;

// ===========================================================================
case 'annuncio_edit':
    $u = require_login('dealer');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $st = db()->prepare('SELECT * FROM listings WHERE id = ? AND dealer_id = ?');
        $st->execute([$id, $u['id']]); $L = $st->fetch();
        if (!$L) { http_response_code(404); exit(t('Non trovato')); }
    } else {
        $st = db()->prepare("SELECT * FROM valuations WHERE id = ? AND dealer_id = ? AND status = 'ritirato' AND grade != 'D'");
        $st->execute([(int)($_GET['from'] ?? 0), $u['id']]); $v = $st->fetch();
        if (!$v) { flash(t('Puoi creare un annuncio solo da una valutazione con ritiro registrato e classe A–C.'), 'err'); redirect('home'); }
        $st = db()->prepare('SELECT id FROM listings WHERE valuation_id = ?'); $st->execute([$v['id']]);
        if ($ex = $st->fetchColumn()) redirect('annuncio_edit', ['id' => $ex]);
        db()->prepare('INSERT INTO listings (dealer_id, valuation_id, model, category, year, km, grade, title, description, price, city, contact) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$u['id'], $v['id'], $v['model'], $v['category'], $v['year'], $v['km'], $v['grade'],
                       t('NIU {model} usato — classe {grade}', ['model' => $v['model'], 'grade' => $v['grade']]), '', 0, $u['city'] ?? '', $u['company'] ?? '']);
        redirect('annuncio_edit', ['id' => db()->lastInsertId()]);
    }
    $editable = in_array($L['status'], ['bozza', 'rifiutato'], true);

    if ($POST) {
        $act = $_POST['action'] ?? 'save';
        if ($act === 'del_photo' && $editable) {
            $st = db()->prepare('SELECT file FROM photos WHERE id = ? AND listing_id = ?');
            $st->execute([(int)$_POST['photo'], $L['id']]);
            if ($f = $st->fetchColumn()) { @unlink("$SHARED/uploads/" . basename($f)); db()->prepare('DELETE FROM photos WHERE id = ?')->execute([(int)$_POST['photo']]); }
            redirect('annuncio_edit', ['id' => $L['id']]);
        }
        if (in_array($act, ['venduto', 'ritirato'], true) && $L['status'] === 'pubblicato') {
            db()->prepare("UPDATE listings SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$act, $L['id']]);
            flash(t('Annuncio aggiornato.')); redirect('annunci');
        }
        if (!$editable) redirect('annuncio_edit', ['id' => $L['id']]);

        db()->prepare("UPDATE listings SET title=?, description=?, price=?, city=?, province=?, contact=?, km=?, updated_at=datetime('now') WHERE id=?")
            ->execute([mb_substr(trim($_POST['title'] ?? ''), 0, 120), trim($_POST['description'] ?? ''), max(0, (int)($_POST['price'] ?? 0)),
                       trim($_POST['city'] ?? ''), strtoupper(mb_substr(trim($_POST['province'] ?? ''), 0, 4)), trim($_POST['contact'] ?? ''), max(0, (int)($_POST['km'] ?? 0)), $L['id']]);

        $n = count(listing_photos($L['id'])); $bad = 0;
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $i => $_) {
                if ($n >= $CFG['photos']['max']) break;
                $f = array_combine(array_keys($_FILES['photos']), array_column($_FILES['photos'], $i));
                if ($name = save_photo($f)) {
                    db()->prepare('INSERT INTO photos (listing_id, file, sort) VALUES (?,?,?)')->execute([$L['id'], $name, $n++]);
                } else $bad++;
            }
        }
        if ($bad) flash(t('{n} foto non accettate (formati JPG/PNG/WEBP, max {mb} MB).', ['n' => $bad, 'mb' => $CFG['photos']['max_bytes'] >> 20]), 'err');

        if ($act === 'submit') {
            $err = [];
            if ($n < $CFG['photos']['min']) $err[] = t('almeno {n} foto', ['n' => $CFG['photos']['min']]);
            if (mb_strlen(trim($_POST['description'] ?? '')) < 80) $err[] = t('una descrizione di almeno 80 caratteri');
            if ((int)($_POST['price'] ?? 0) <= 0) $err[] = t('un prezzo');
            if (trim($_POST['city'] ?? '') === '') $err[] = t('la località');
            if ($err) { flash(t('Per inviare a NIU servono: {list}.', ['list' => implode(', ', $err)]), 'err'); redirect('annuncio_edit', ['id' => $L['id']]); }
            db()->prepare("UPDATE listings SET status='in_revisione', review_note=NULL WHERE id=?")->execute([$L['id']]);
            flash(t('Annuncio inviato al team NIU per l\'approvazione.')); redirect('annunci');
        }
        flash(t('Bozza salvata.')); redirect('annuncio_edit', ['id' => $L['id']]);
    }

    $photos = listing_photos($L['id']);
    page_start(t('Annuncio')); ?>
    <h1><?= t('ANNUNCIO') ?> <em><?= h($L['model']) ?></em> <?= grade_badge($L['grade']) ?> <?= status_badge($L['status']) ?></h1>
    <?php if ($L['status'] === 'rifiutato' && $L['review_note']): ?><div class="flash err"><b><?= t('Nota del team NIU:') ?></b> <?= nl2br(h($L['review_note'])) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card" id="lform"><?= csrf() ?>
      <fieldset <?= $editable ? '' : 'disabled' ?>>
      <label><?= t('Titolo') ?> <input name="title" maxlength="120" required value="<?= h($L['title']) ?>"></label>
      <label><?= t('Descrizione') ?> <small class="muted"><?= t('— stato, interventi eseguiti, accessori inclusi, garanzia residua') ?></small>
        <textarea name="description" rows="7"><?= h($L['description']) ?></textarea></label>
      <div class="grid3">
        <label><?= t('Prezzo (€)') ?> <input type="number" name="price" min="0" value="<?= (int)$L['price'] ?: '' ?>"></label>
        <label><?= t('Chilometri') ?> <input type="number" name="km" min="0" value="<?= (int)$L['km'] ?>"></label>
        <label><?= t('Contatto mostrato') ?> <input name="contact" value="<?= h($L['contact']) ?>" placeholder="<?= h(t('Nome concessionaria, telefono')) ?>"></label>
        <label><?= t('Città') ?> <input name="city" value="<?= h($L['city']) ?>"></label>
        <label><?= t('Provincia') ?> <input name="province" maxlength="4" value="<?= h($L['province']) ?>" placeholder="MI"></label>
      </div>

      <h2><?= t('Foto') ?> <small class="muted"><?= count($photos) ?>/<?= $CFG['photos']['max'] ?> <?= t('— minimo {n}. La prima è la copertina.', ['n' => $CFG['photos']['min']]) ?></small></h2>
      <div class="thumbs">
        <?php foreach ($photos as $ph): ?>
          <div class="thumb"><img src="<?= url('img', ['f' => $ph['file']]) ?>" alt="">
            <?php if ($editable): ?><button class="x" form="del<?= $ph['id'] ?>" title="<?= h(t('Elimina')) ?>"><?= t('×') ?></button><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ($editable && count($photos) < $CFG['photos']['max']): ?>
        <label class="drop">
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple id="files">
          <span><?= t('Trascina qui le foto o <u>sfoglia</u>') ?><br><small><?= t('Suggerite: 3/4 anteriore, laterale dx e sx, posteriore, cruscotto, batteria, eventuali difetti') ?></small></span>
        </label>
        <div class="thumbs" id="preview"></div>
      <?php endif; ?>
      </fieldset>
      <div class="row">
        <?php if ($editable): ?>
          <button class="btn ghost" name="action" value="save"><?= t('Salva bozza') ?></button>
          <button class="btn" name="action" value="submit"><?= t('Invia a NIU per approvazione') ?></button>
        <?php elseif ($L['status'] === 'pubblicato'): ?>
          <a class="btn ghost" href="<?= url('annuncio', ['id' => $L['id']]) ?>" target="_blank"><?= t('Vedi online') ?></a>
          <button class="btn" name="action" value="venduto"><?= t('Segna come venduto') ?></button>
          <button class="btn ghost" name="action" value="ritirato"><?= t('Ritira annuncio') ?></button>
        <?php else: ?>
          <p class="muted"><?= t('L\'annuncio non è modificabile in questo stato.') ?></p>
        <?php endif; ?>
      </div>
    </form>
    <?php foreach ($photos as $ph): ?><form method="post" id="del<?= $ph['id'] ?>" class="hidden"><?= csrf() ?><input type="hidden" name="action" value="del_photo"><input type="hidden" name="photo" value="<?= $ph['id'] ?>"></form><?php endforeach; ?>
    <script>
    const fi = document.getElementById('files');
    fi && fi.addEventListener('change', () => {
      const pv = document.getElementById('preview'); pv.innerHTML = '';
      [...fi.files].forEach(f => { const d = document.createElement('div'); d.className = 'thumb new';
        const i = new Image(); i.src = URL.createObjectURL(f); d.append(i); pv.append(d); });
    });
    </script>
    <?php page_end(); break;

// ===========================================================================
case 'annunci':
    $u = require_login('dealer');
    $st = db()->prepare('SELECT l.*, (SELECT file FROM photos WHERE listing_id = l.id ORDER BY sort, id LIMIT 1) cover FROM listings l WHERE dealer_id = ? ORDER BY id DESC');
    $st->execute([$u['id']]);
    page_start(t('I miei annunci')); ?>
    <h1><?= t('I MIEI <em>ANNUNCI</em>') ?></h1>
    <?php render_listing_table($st->fetchAll(), 'annuncio_edit');
    page_end(); break;

// ===========================================================================
case 'revisione':
    $u = require_login('niu');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $st = db()->prepare('SELECT l.*, u.company, u.email FROM listings l JOIN users u ON u.id = l.dealer_id WHERE l.id = ?');
        $st->execute([$id]); $L = $st->fetch();
        if (!$L) { http_response_code(404); exit(t('Non trovato')); }
        if ($POST) {
            $act = $_POST['action'] ?? '';
            if ($act === 'approva') {
                db()->prepare("UPDATE listings SET status='pubblicato', review_note=NULL, published_at=datetime('now') WHERE id=?")->execute([$id]);
                flash(t('Annuncio pubblicato nella vetrina 2nd Hand.'));
            } elseif ($act === 'rifiuta') {
                $note = trim($_POST['note'] ?? '');
                if ($note === '') { flash(t('Scrivi al dealer cosa correggere.'), 'err'); redirect('revisione', ['id' => $id]); }
                db()->prepare("UPDATE listings SET status='rifiutato', review_note=? WHERE id=?")->execute([$note, $id]);
                flash(t('Annuncio rimandato al dealer.'));
            } elseif ($act === 'sospendi') {
                db()->prepare("UPDATE listings SET status='ritirato' WHERE id=?")->execute([$id]);
                flash(t('Annuncio rimosso dalla vetrina.'));
            }
            redirect('revisione');
        }
        page_start(t('Revisione annuncio'));
        render_listing_detail($L, true); ?>
        <form method="post" class="card"><?= csrf() ?>
          <h2><?= t('Decisione NIU') ?></h2>
          <p class="muted"><?= t('Dealer:') ?> <?= h($L['company']) ?> (<?= h($L['email']) ?>)<?php if ($L['valuation_id']): ?> · <a href="<?= url('valutazione', ['id' => $L['valuation_id']]) ?>"><?= t('valutazione #') ?><?= $L['valuation_id'] ?></a><?php endif; ?></p>
          <label><?= t('Nota per il dealer (obbligatoria se rimandi indietro)') ?> <textarea name="note" rows="3"></textarea></label>
          <div class="row">
            <?php if ($L['status'] === 'in_revisione'): ?>
              <button class="btn" name="action" value="approva"><?= t('Approva e pubblica') ?></button>
              <button class="btn ghost" name="action" value="rifiuta"><?= t('Rimanda al dealer') ?></button>
            <?php elseif ($L['status'] === 'pubblicato'): ?>
              <button class="btn ghost" name="action" value="sospendi"><?= t('Rimuovi dalla vetrina') ?></button>
            <?php endif; ?>
          </div>
        </form>
        <?php page_end(); break;
    }
    $rows = db()->query("SELECT l.*, u.company, (SELECT file FROM photos WHERE listing_id = l.id ORDER BY sort, id LIMIT 1) cover
                         FROM listings l JOIN users u ON u.id = l.dealer_id
                         WHERE l.status IN ('in_revisione','pubblicato','rifiutato')
                         ORDER BY CASE l.status WHEN 'in_revisione' THEN 0 ELSE 1 END, l.id DESC")->fetchAll();
    page_start(t('Revisione annunci')); ?>
    <h1><?= t('REVISIONE <em>ANNUNCI</em>') ?></h1>
    <?php render_listing_table($rows, 'revisione', true);
    page_end(); break;

// ===========================================================================
case 'valutazioni':
    require_login('niu');
    page_start(t('Valutazioni')); ?>
    <h1><?= t('TUTTE LE <em>VALUTAZIONI</em>') ?></h1>
    <p><a class="btn ghost" href="<?= url('export') ?>"><?= t('Scarica CSV') ?></a></p>
    <?php render_valuations(db()->query('SELECT v.*, u.company FROM valuations v JOIN users u ON u.id = v.dealer_id ORDER BY v.id DESC')->fetchAll(), true);
    page_end(); break;

case 'export':
    require_login('niu');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="valutazioni-2nd-hand-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', t('Data'), t('Dealer'), t('Telaio'), t('Modello'), t('Anno'), t('Km'), t('SOH %'), t('Punteggio'), t('Classe'), t('Stato'), t('Prezzo ritiro'), t('Acquisto nuovo'), t('Modello nuovo'), t('Extra NIU'), t('Supporto dealer')], ';');
    foreach (db()->query('SELECT v.*, u.company FROM valuations v JOIN users u ON u.id = v.dealer_id ORDER BY v.id') as $r) {
        fputcsv($out, [$r['id'], $r['created_at'], $r['company'], $r['vin'], $r['model'], $r['year'], $r['km'], $r['battery_soh'], $r['score'], $r['grade'],
                       $r['status'], $r['trade_in_price'], $r['new_purchase'] ? t('Sì') : t('No'), $r['new_model'], $r['incentive_niu'], $r['incentive_dealer']], ';');
    }
    exit;

// ===========================================================================
case 'dealer':
    require_login('niu');
    if ($POST) {
        if (($_POST['action'] ?? '') === 'toggle') {
            db()->prepare("UPDATE users SET active = 1 - active WHERE id = ? AND role = 'dealer'")->execute([(int)$_POST['id']]);
            redirect('dealer');
        }
        if (($_POST['action'] ?? '') === 'reset') {
            $st = db()->prepare("SELECT email FROM users WHERE id = ? AND role = 'dealer'"); $st->execute([(int)$_POST['id']]);
            if ($email = $st->fetchColumn()) {
                $pw = temp_password();
                db()->prepare('UPDATE users SET pass = ?, must_change = 1, reset_requested_at = NULL WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['id']]);
                flash(t('Nuova password temporanea per {email}: {pw} — comunicala al dealer, non verrà più mostrata. Al primo accesso dovrà cambiarla.', ['email' => $email, 'pw' => $pw]));
            }
            redirect('dealer');
        }
        $email = trim(strtolower($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($_POST['company'] ?? '') === '') { flash(t('Email e ragione sociale obbligatorie.'), 'err'); redirect('dealer'); }
        $pw = temp_password();
        try {
            db()->prepare("INSERT INTO users (email, pass, role, name, company, city, address, phone, must_change) VALUES (?,?,'dealer',?,?,?,?,?,1)")
                ->execute([$email, password_hash($pw, PASSWORD_DEFAULT), trim($_POST['name'] ?? ''), trim($_POST['company']), trim($_POST['city'] ?? ''),
                           trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? '')]);
            flash(t('Dealer creato. Password iniziale per {email}: {pw} — comunicala al dealer, non verrà più mostrata.', ['email' => $email, 'pw' => $pw]));
        } catch (PDOException) { flash(t('Esiste già un utente con questa email.'), 'err'); }
        redirect('dealer');
    }
    $pending = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'dealer' AND reset_requested_at IS NOT NULL")->fetchColumn();
    page_start(t('Dealer')); ?>
    <h1><?= t('DEALER <em>AUTORIZZATI</em>') ?></h1>
    <?php if ($pending): ?><div class="flash err"><?= t('{n} dealer hanno chiesto una nuova password: usa «Nuova password» nella tabella.', ['n' => $pending]) ?></div><?php endif; ?>
    <form method="post" class="card"><?= csrf() ?>
      <h2><?= t('Aggiungi dealer') ?></h2>
      <div class="grid3">
        <label><?= t('Ragione sociale') ?> <input name="company" required></label>
        <label><?= t('Referente') ?> <input name="name"></label>
        <label><?= t('Città') ?> <input name="city"></label>
        <label><?= t('Indirizzo') ?> <input name="address" placeholder="<?= h(t('Via, civico, CAP')) ?>"></label>
        <label><?= t('Telefono') ?> <input name="phone"></label>
        <label><?= t('Email di accesso') ?> <input type="email" name="email" required></label>
      </div>
      <button class="btn"><?= t('Crea dealer') ?></button>
    </form>
    <table class="tbl">
      <tr><th><?= t('Dealer') ?></th><th><?= t('Referente') ?></th><th><?= t('Città') ?></th><th><?= t('Email') ?></th><th><?= t('Valutazioni') ?></th><th><?= t('Annunci online') ?></th><th></th></tr>
      <?php foreach (db()->query("SELECT u.*, (SELECT COUNT(*) FROM valuations WHERE dealer_id=u.id) nv, (SELECT COUNT(*) FROM listings WHERE dealer_id=u.id AND status='pubblicato') nl FROM users u WHERE role='dealer' ORDER BY company") as $d): ?>
        <tr class="<?= $d['active'] ? '' : 'off' ?>"><td><b><?= h($d['company']) ?></b></td><td><?= h($d['name']) ?></td><td><?= h($d['city']) ?></td><td><?= h($d['email']) ?><?php if ($d['reset_requested_at']): ?><br><span class="badge warn"><?= t('Reset password richiesto') ?></span><?php endif; ?></td><td><?= $d['nv'] ?></td><td><?= $d['nl'] ?></td>
          <td><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="link"><?= t('Nuova password') ?></button></form>
            · <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="link"><?= $d['active'] ? t('Disattiva') : t('Riattiva') ?></button></form></td></tr>
      <?php endforeach; ?>
    </table>
    <?php page_end(); break;

// ===========================================================================
case 'impostazioni':
    $u = require_login('niu');
    $cats = array_keys($CFG['categories']);
    if ($POST) {
        $eur = fn($v) => max(0, min(100000, (int)$v));
        $ti = []; $np = [];
        foreach ($cats as $c) {
            foreach (array_keys($CFG['grades']) as $g) $ti[$c][$g] = $eur($_POST['ti'][$c][$g] ?? 0);
            $np[$c] = ['niu' => $eur($_POST['np'][$c]['niu'] ?? 0), 'dealer' => $eur($_POST['np'][$c]['dealer'] ?? 0)];
        }
        $gm = ['A' => (int)($_POST['gm']['A'] ?? 90), 'B' => (int)($_POST['gm']['B'] ?? 75), 'C' => (int)($_POST['gm']['C'] ?? 55), 'D' => 0];
        if (!(100 >= $gm['A'] && $gm['A'] > $gm['B'] && $gm['B'] > $gm['C'] && $gm['C'] > 0)) {
            flash(t('Le soglie devono essere decrescenti: 100 ≥ A > B > C > 0.'), 'err'); redirect('impostazioni');
        }
        $cl = [];
        foreach ($CHECKLIST_ALL as $items) foreach ($items as $id => $it) {
            $p = $_POST['cl'][$id] ?? [];
            $cl[$id] = ['on' => !empty($p['on']), 'w' => max(0, min(20, (int)($p['w'] ?? $it[1]))),
                        'block' => !empty($p['block']), 'cust' => !empty($p['cust']) && !empty($it[3])];
        }
        setting_put('incentive_trade_in', $ti, (int)$u['id']);
        setting_put('incentive_new_purchase', $np, (int)$u['id']);
        setting_put('grade_min', $gm, (int)$u['id']);
        setting_put('checklist', $cl, (int)$u['id']);
        flash(t('Impostazioni salvate. Valgono per le nuove valutazioni; quelle già fatte non cambiano.'));
        redirect('impostazioni');
    }
    $saved = setting_get('checklist') ?? [];
    $last = settings_last_change();
    page_start(t('Impostazioni')); ?>
    <h1><?= t('IMPOSTAZIONI <em>2ND HAND</em>') ?></h1>
    <p class="muted"><?= t('Le modifiche valgono per le nuove valutazioni e stime; quelle già fatte non cambiano.') ?>
      <?php if ($last): ?><?= t('Ultima modifica: {date} — {name}.', ['date' => h($last['updated_at']), 'name' => h($last['name'] ?? '')]) ?><?php endif; ?></p>
    <form method="post"><?= csrf() ?>
      <section class="card">
        <h2><?= t('Extra NIU per il ritiro (€)') ?></h2>
        <p class="muted"><?= t('Importo che NIU riconosce al dealer per ogni ritiro registrato, per categoria e classe.') ?></p>
        <table class="tbl"><tr><th></th><?php foreach (array_keys($CFG['grades']) as $g): ?><th><?= t('Classe') ?> <?= $g ?></th><?php endforeach; ?></tr>
          <?php foreach ($cats as $c): ?><tr><td><b><?= h(strtok(t($CFG['categories'][$c]), ' (')) ?></b></td>
            <?php foreach (array_keys($CFG['grades']) as $g): ?><td><input type="number" min="0" step="10" name="ti[<?= $c ?>][<?= $g ?>]" value="<?= (int)$CFG['incentive_trade_in'][$c][$g] ?>"></td><?php endforeach; ?></tr><?php endforeach; ?>
        </table>
      </section>
      <section class="card">
        <h2><?= t('Supporto se il cliente acquista un NIU nuovo (€)') ?></h2>
        <p class="muted"><?= t('Si aggiunge all\'extra per il ritiro (non per la classe D). La quota dealer è quella suggerita al dealer verso il cliente.') ?></p>
        <table class="tbl"><tr><th></th><th><?= t('Quota NIU') ?></th><th><?= t('Quota dealer') ?></th></tr>
          <?php foreach ($cats as $c): ?><tr><td><b><?= h(strtok(t($CFG['categories'][$c]), ' (')) ?></b></td>
            <td><input type="number" min="0" step="10" name="np[<?= $c ?>][niu]" value="<?= (int)$CFG['incentive_new_purchase'][$c]['niu'] ?>"></td>
            <td><input type="number" min="0" step="10" name="np[<?= $c ?>][dealer]" value="<?= (int)$CFG['incentive_new_purchase'][$c]['dealer'] ?>"></td></tr><?php endforeach; ?>
        </table>
      </section>
      <section class="card">
        <h2><?= t('Soglie delle classi (punteggio minimo)') ?></h2>
        <div class="grid3"><?php foreach (['A', 'B', 'C'] as $g): ?>
          <label><?= t('Classe') ?> <?= $g ?> — <?= h(t($CFG['grades'][$g]['label'])) ?><input type="number" min="1" max="100" name="gm[<?= $g ?>]" value="<?= (int)$CFG['grades'][$g]['min'] ?>"></label>
        <?php endforeach; ?></div>
        <p class="muted"><?= t('Sotto la soglia C il veicolo è in classe D (non idoneo).') ?></p>
      </section>
      <section class="card">
        <h2><?= t('Checklist di valutazione') ?></h2>
        <p class="muted"><?= t('Peso = punti tolti per «Usura» (il triplo per «Difetto»). «Bloccante»: un difetto rende il veicolo non idoneo. «Cliente»: la domanda compare nell\'autovalutazione online.') ?></p>
        <table class="tbl"><tr><th><?= t('Attiva') ?></th><th><?= t('Voce') ?></th><th><?= t('Peso') ?></th><th><?= t('Bloccante') ?></th><th><?= t('Cliente') ?></th></tr>
          <?php foreach ($CHECKLIST_ALL as $sec => $items): ?><tr><td colspan="5" class="muted"><b><?= h(t($sec)) ?></b></td></tr>
            <?php foreach ($items as $id => $it): $s = $saved[$id] ?? ['on' => true, 'w' => $it[1], 'block' => in_array($id, DEFAULT_BLOCKERS, true), 'cust' => !empty($it[3])]; ?>
            <tr><td><input type="checkbox" name="cl[<?= $id ?>][on]" value="1" <?= $s['on'] ? 'checked' : '' ?>></td>
              <td><?= h(t($it[0])) ?></td>
              <td style="width:90px"><input type="number" min="0" max="20" name="cl[<?= $id ?>][w]" value="<?= (int)$s['w'] ?>"></td>
              <td><input type="checkbox" name="cl[<?= $id ?>][block]" value="1" <?= $s['block'] ? 'checked' : '' ?>></td>
              <td><?php if (!empty($it[3])): ?><input type="checkbox" name="cl[<?= $id ?>][cust]" value="1" <?= $s['cust'] ? 'checked' : '' ?>><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
          <?php endforeach; endforeach; ?>
        </table>
      </section>
      <button class="btn"><?= t('Salva impostazioni') ?></button>
    </form>
    <?php page_end(); break;

// ===========================================================================
// CLIENTE — autovalutazione, extra supporto, scelta dealer e appuntamento
// ===========================================================================
case 'stima':
    $S = $_SESSION['stima'] ?? null;
    if ($POST && ($_POST['action'] ?? '') === 'calcola') {
        if (!empty($_POST['website'])) redirect('stima');           // honeypot anti-spam
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['vin'] ?? ''));
        $model = (string)($_POST['model'] ?? '');
        if (strlen($vin) < 8 || strlen($vin) > 20 || category_of($model) === '' || !in_array($model, array_merge(...array_values($MODELS)), true)) {
            flash(t('Inserisci un numero di telaio valido e scegli il modello.'), 'err'); redirect('stima');
        }
        $cat = category_of($model);
        $soh = ($_POST['battery_soh'] ?? '') === '' ? null : max(0, min(100, (int)$_POST['battery_soh']));
        $ans = [];
        foreach ($CHECKLIST as $items) foreach ($items as $id => $it) {
            if (($it[3] ?? null) === null || ($it[2] && !in_array($cat, $it[2], true))) continue;
            $a = $_POST['a'][$id] ?? 'na';
            $ans[$id] = in_array($a, ['ok', 'usura', 'difetto', 'na'], true) ? $a : 'na';
        }
        [$score, $grade, $blockers] = evaluate($ans, $cat, $soh);
        $wants = !empty($_POST['wants_new']);
        [$niu, $dealer] = incentives($cat, $grade, true);
        $_SESSION['stima'] = [
            'vin' => $vin, 'model' => $model, 'category' => $cat, 'year' => (int)($_POST['year'] ?? 0) ?: null,
            'km' => max(0, (int)($_POST['km'] ?? 0)), 'soh' => $soh, 'answers' => $ans, 'score' => $score, 'grade' => $grade,
            'blockers' => $blockers, 'wants_new' => $wants, 'new_model' => $wants ? ($_POST['new_model'] ?? '') : '',
            'support_niu' => $niu - ($CFG['incentive_trade_in'][$cat][$grade] ?? 0), 'support_dealer' => $dealer,
        ];
        redirect('stima', ['step' => 2]);
    }
    if ($POST && ($_POST['action'] ?? '') === 'prenota' && $S) {
        $dealerId = (int)($_POST['dealer'] ?? 0); $slot = (string)($_POST['slot'] ?? '');
        $name = trim($_POST['name'] ?? ''); $email = trim(strtolower($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? '');
        $slots = $dealerId ? free_slots($dealerId) : [];
        [$day, $time] = array_pad(explode(' ', $slot, 2), 2, '');
        $err = [];
        if (!in_array($dealerId, array_map('intval', array_column(dealers(), 'id')), true)) $err[] = t('scegli un dealer');
        if (!in_array($time, $slots[$day] ?? [], true)) $err[] = t('scegli un orario disponibile');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen(preg_replace('/\D/', '', $phone)) < 6) $err[] = t('nome, email e telefono validi');
        if (empty($_POST['privacy'])) $err[] = t('il consenso privacy');
        if ($err) { flash(t('Per prenotare servono: {list}.', ['list' => implode(', ', $err)]), 'err'); redirect('stima', ['step' => 2, 'dealer' => $dealerId]); }
        $token = bin2hex(random_bytes(16));
        try {
            db()->prepare("INSERT INTO requests (token, dealer_id, name, email, phone, vin, category, model, year, km, battery_soh, answers_json, score, grade, blockers,
                           wants_new, new_model, est_niu, est_dealer, appt_at, consent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now'))")
                ->execute([$token, $dealerId, $name, $email, $phone, $S['vin'], $S['category'], $S['model'], $S['year'], $S['km'], $S['soh'],
                           json_encode($S['answers']), $S['score'], $S['grade'], implode("\n", $S['blockers']),
                           $S['wants_new'] ? 1 : 0, $S['new_model'], $S['support_niu'], $S['support_dealer'], $slot]);
        } catch (PDOException) { flash(t('Quell\'orario è appena stato prenotato. Scegline un altro.'), 'err'); redirect('stima', ['step' => 2, 'dealer' => $dealerId]); }
        unset($_SESSION['stima']);
        redirect('richiesta', ['t' => $token, 'new' => 1]);
    }

    page_start(t('Valuta il tuo NIU'), true);
    if (($_GET['step'] ?? '') === '2' && $S):
        $dealerId = (int)($_GET['dealer'] ?? 0);
        $D = dealers(); ?>
        <h1><?= t('LA TUA <em>STIMA</em>') ?></h1>
        <div class="result">
          <div class="big-grade g-<?= h($S['grade']) ?>"><?= h($S['grade']) ?></div>
          <div>
            <h2><?= h($S['model']) ?><?= $S['year'] ? ' · ' . $S['year'] : '' ?> · <?= nf($S['km']) ?> <?= t('km') ?></h2>
            <p><b><?= t('Classe stimata') ?> <?= h($S['grade']) ?></b> — <?= h(t($CFG['grades'][$S['grade']]['label'])) ?></p>
            <p class="muted"><?= t('È una stima basata sulle tue risposte. La valutazione definitiva la fa il dealer in officina, con la diagnosi NIU.') ?></p>
            <?php if ($S['blockers']): ?><div class="flash err"><?= t('Da quanto indichi, il veicolo potrebbe non rientrare nel programma 2nd Hand. Il dealer verificherà di persona.') ?></div><?php endif; ?>
          </div>
        </div>
        <?php if ($S['grade'] !== 'D'): ?>
        <div class="split">
          <div class="offer hl"><span><?= t('Se acquisti un NIU nuovo') ?></span><b><?= eur($S['support_niu'] + $S['support_dealer']) ?></b>
            <small><?= t('di supporto extra NIU + dealer, oltre alla valutazione del tuo usato') ?><?= $S['new_model'] ? ' · ' . h($S['new_model']) : '' ?></small></div>
          <div class="offer"><span><?= t('Solo ritiro dell\'usato') ?></span><b><?= t('In officina') ?></b><small><?= t('il valore di ritiro lo propone il dealer dopo la verifica') ?></small></div>
        </div>
        <p class="muted"><?= t('Importi indicativi, soggetti a verifica del veicolo e alle condizioni della promozione in corso.') ?></p>
        <?php endif; ?>

        <section class="card">
          <h2><?= t('Prenota la valutazione in concessionaria') ?></h2>
          <?php if (!$D): ?><p class="muted"><?= t('Nessun dealer disponibile al momento.') ?></p><?php else: ?>
          <form method="get" class="row"><input type="hidden" name="p" value="stima"><input type="hidden" name="step" value="2">
            <select name="dealer" onchange="this.form.submit()" style="flex:1;min-width:260px;margin:0">
              <option value=""><?= t('— scegli il dealer —') ?></option>
              <?php foreach ($D as $d): ?><option value="<?= $d['id'] ?>" <?= $d['id'] == $dealerId ? 'selected' : '' ?>><?= h($d['city'] . ' — ' . $d['company']) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn ghost"><?= t('Mostra orari') ?></button></noscript>
          </form>
          <?php if ($dealerId && ($dd = array_values(array_filter($D, fn($d) => $d['id'] == $dealerId))[0] ?? null)):
              $slots = free_slots($dealerId); ?>
            <p class="muted"><?= h($dd['address'] ?: $dd['city']) ?><?= $dd['phone'] ? ' · ' . h($dd['phone']) : '' ?></p>
            <form method="post"><?= csrf() ?><input type="hidden" name="action" value="prenota"><input type="hidden" name="dealer" value="<?= $dealerId ?>">
              <div class="slots">
                <?php foreach ($slots as $day => $times): ?>
                  <div class="day"><b><?= h(it_date($day, false)) ?></b>
                    <div class="seg wrap"><?php foreach ($times as $t): ?><label><input type="radio" name="slot" value="<?= h("$day $t") ?>" required><span><?= h($t) ?></span></label><?php endforeach; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="grid3">
                <label><?= t('Nome e cognome') ?> <input name="name" required autocomplete="name"></label>
                <label><?= t('Email') ?> <input type="email" name="email" required autocomplete="email"></label>
                <label><?= t('Telefono') ?> <input type="tel" name="phone" required autocomplete="tel"></label>
              </div>
              <label class="check"><input type="checkbox" name="privacy" value="1" required> <?= t('Acconsento al trattamento dei miei dati da parte di NIU e del dealer scelto per gestire la valutazione e l\'appuntamento.') ?></label>
              <button class="btn"><?= t('Prenota appuntamento') ?></button>
            </form>
          <?php endif; endif; ?>
        </section>
        <p><a href="<?= url('stima') ?>"><?= t('← Modifica le risposte') ?></a></p>
    <?php else: ?>
        <section class="hero">
          <h1><?= t('VALUTA IL TUO <em>NIU</em>') ?></h1>
          <p><?= t('Rispondi a qualche domanda: ottieni la stima della classe del tuo usato e scopri il supporto extra se passi a un NIU nuovo. Poi prenoti la verifica dal dealer.') ?></p>
        </section>
        <form method="post" class="card"><?= csrf() ?><input type="hidden" name="action" value="calcola">
          <input name="website" class="hidden" tabindex="-1" autocomplete="off">
          <h2><?= t('1 · Il tuo veicolo') ?></h2>
          <div class="grid3">
            <label><?= t('Numero di telaio') ?> <small class="muted"><?= t('— sulla carta di circolazione') ?></small><input name="vin" required minlength="8" maxlength="20" value="<?= h($S['vin'] ?? '') ?>"></label>
            <label><?= t('Modello') ?>
              <select name="model" id="model" required>
                <option value=""><?= t('— scegli —') ?></option>
                <?php foreach ($CFG['categories'] as $c => $cl): ?><optgroup label="<?= h(t($cl)) ?>"><?php foreach ($MODELS[$c] as $m): ?><option data-cat="<?= $c ?>" <?= ($S['model'] ?? '') === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
              </select></label>
            <label><?= t('Anno di acquisto') ?> <input type="number" name="year" min="2015" max="<?= date('Y') ?>" value="<?= h((string)($S['year'] ?? '')) ?>"></label>
            <label><?= t('Chilometri') ?> <input type="number" name="km" min="0" required value="<?= h((string)($S['km'] ?? '')) ?>"></label>
            <label><?= t('Salute batteria (%)') ?> <small class="muted"><?= t('— facoltativo, dall\'app NIU') ?></small><input type="number" name="battery_soh" min="0" max="100" value="<?= h((string)($S['soh'] ?? '')) ?>"></label>
          </div>
          <h2><?= t('2 · Com\'è il veicolo?') ?></h2>
          <?php foreach ($CHECKLIST as $items) foreach ($items as $id => $it): if (($it[3] ?? null) === null) continue; $cur = $S['answers'][$id] ?? 'ok'; ?>
            <div class="chk" data-cats="<?= h(implode(',', $it[2] ?? [])) ?>"><span><?= h(t($it[3])) ?></span>
              <div class="seg"><?php foreach (['ok' => t('Sì'), 'usura' => t('In parte'), 'difetto' => t('No'), 'na' => t('Non so')] as $v => $l): ?>
                <label><input type="radio" name="a[<?= $id ?>]" value="<?= $v ?>" <?= $cur === $v ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div>
            </div>
          <?php endforeach; ?>
          <h2><?= t('3 · Passi a un NIU nuovo?') ?></h2>
          <div class="grid3">
            <label class="check"><input type="checkbox" name="wants_new" value="1" <?= !empty($S['wants_new']) ? 'checked' : '' ?>> <?= t('Sono interessato a un NIU nuovo') ?></label>
            <label><?= t('Quale modello?') ?> <select name="new_model"><option value=""><?= t('Non ho ancora deciso') ?></option><?php foreach (array_keys($CFG['categories']) as $c) foreach ($MODELS[$c] as $m): ?><option <?= ($S['new_model'] ?? '') === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></select></label>
          </div>
          <button class="btn"><?= t('Calcola la stima') ?></button>
        </form>
        <script>
        const sel = document.getElementById('model');
        function sync() { const c = sel.selectedOptions[0] && sel.selectedOptions[0].dataset.cat;
          document.querySelectorAll('.chk').forEach(el => { const k = el.dataset.cats; el.classList.toggle('hidden', !!k && !!c && !k.split(',').includes(c)); }); }
        sel.addEventListener('change', sync); sync();
        </script>
    <?php endif;
    page_end(); break;

case 'richiesta':
    $st = db()->prepare('SELECT r.*, u.company, u.city dcity, u.address, u.phone dphone FROM requests r JOIN users u ON u.id = r.dealer_id WHERE r.token = ?');
    $st->execute([(string)($_GET['t'] ?? '')]); $R = $st->fetch();
    if (!$R) { http_response_code(404); page_start(t('Non trovato'), true); echo '<p>' . t('Richiesta non trovata.') . '</p>'; page_end(); break; }
    if ($POST && ($_POST['action'] ?? '') === 'annulla' && in_array($R['status'], ['richiesto', 'confermato'], true)) {
        db()->prepare("UPDATE requests SET status = 'annullato', dealer_note = ? WHERE id = ?")->execute([t('Annullato dal cliente'), $R['id']]);
        flash(t('Appuntamento annullato.')); redirect('richiesta', ['t' => $R['token']]);
    }
    $labels = ['richiesto' => t('In attesa di conferma dal dealer'), 'confermato' => t('Confermato'), 'annullato' => t('Annullato'), 'completato' => t('Valutazione eseguita')];
    page_start(t('La tua richiesta'), true); ?>
    <h1><?= t('LA TUA <em>RICHIESTA</em>') ?></h1>
    <?php if (!empty($_GET['new'])): ?><div class="flash"><b><?= t('Prenotazione inviata.') ?></b> <?= t('Salva questa pagina nei preferiti: è il link per seguire la tua richiesta.') ?></div><?php endif; ?>
    <section class="card">
      <dl class="portal">
        <div><dt><?= t('Stato') ?></dt><dd><?= h($labels[$R['status']]) ?></dd></div>
        <div><dt><?= t('Appuntamento') ?></dt><dd><?= h(it_date($R['appt_at'])) ?></dd></div>
        <div><dt><?= t('Dealer') ?></dt><dd><?= h($R['company']) ?><br><?= h($R['address'] ?: $R['dcity']) ?><?= $R['dphone'] ? '<br>' . h($R['dphone']) : '' ?></dd></div>
        <div><dt><?= t('Veicolo') ?></dt><dd><?= h($R['model']) ?> · <?= nf((int)$R['km']) ?> <?= t('km') ?></dd></div>
        <div><dt><?= t('Classe stimata') ?></dt><dd><?= grade_badge($R['grade']) ?></dd></div>
        <?php if ($R['grade'] !== 'D'): ?><div><dt><?= t('Supporto se acquisti un NIU nuovo') ?></dt><dd><?= eur($R['est_niu'] + $R['est_dealer']) ?> <small class="muted"><?= t('(indicativo)') ?></small></dd></div><?php endif; ?>
      </dl>
      <?php if ($R['dealer_note']): ?><p><b><?= t('Nota:') ?></b> <?= h($R['dealer_note']) ?></p><?php endif; ?>
      <p class="muted"><?= t('Porta con te carta di circolazione, chiavi / NFC card e caricabatterie.') ?></p>
      <?php if (in_array($R['status'], ['richiesto', 'confermato'], true)): ?>
        <form method="post"><?= csrf() ?><input type="hidden" name="action" value="annulla"><button class="btn ghost"><?= t('Annulla appuntamento') ?></button></form>
      <?php endif; ?>
    </section>
    <?php page_end(); break;

// ===========================================================================
case 'appuntamenti':
    $u = require_login('dealer');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $st = db()->prepare('SELECT * FROM requests WHERE id = ? AND dealer_id = ?');
        $st->execute([$id, $u['id']]); $R = $st->fetch();
        if (!$R) { http_response_code(404); exit(t('Non trovato')); }
        if ($POST) {
            $act = $_POST['action'] ?? ''; $note = trim($_POST['note'] ?? '') ?: null;
            if ($act === 'conferma' && $R['status'] === 'richiesto') db()->prepare("UPDATE requests SET status='confermato', dealer_note=? WHERE id=?")->execute([$note, $id]);
            if ($act === 'annulla' && in_array($R['status'], ['richiesto', 'confermato'], true)) {
                if (!$note) { flash(t('Indica al cliente il motivo o un orario alternativo.'), 'err'); redirect('appuntamenti', ['id' => $id]); }
                db()->prepare("UPDATE requests SET status='annullato', dealer_note=? WHERE id=?")->execute([$note, $id]);
            }
            flash(t('Appuntamento aggiornato.')); redirect('appuntamenti');
        }
        $ans = json_decode($R['answers_json'] ?? '{}', true);
        page_start(t('Appuntamento')); ?>
        <h1><?= t('APPUNTAMENTO') ?> <em><?= h(it_date($R['appt_at'])) ?></em></h1>
        <section class="card">
          <dl class="portal">
            <div><dt><?= t('Cliente') ?></dt><dd><?= h($R['name']) ?><br><a href="tel:<?= h($R['phone']) ?>"><?= h($R['phone']) ?></a><br><a href="mailto:<?= h($R['email']) ?>"><?= h($R['email']) ?></a></dd></div>
            <div><dt><?= t('Veicolo') ?></dt><dd><?= h($R['model']) ?> · <?= h((string)$R['year']) ?> · <?= nf((int)$R['km']) ?> <?= t('km') ?><br><span class="mono"><?= h($R['vin']) ?></span></dd></div>
            <div><dt><?= t('Autovalutazione cliente') ?></dt><dd><?= grade_badge($R['grade']) ?> <?= (int)$R['score'] ?>/100<?= $R['battery_soh'] !== null ? ' · SOH ' . (int)$R['battery_soh'] . '%' : '' ?></dd></div>
            <div><dt><?= t('Interesse NIU nuovo') ?></dt><dd><?= $R['wants_new'] ? '<b class="red">Sì</b>' . ($R['new_model'] ? ' — ' . h($R['new_model']) : '') : t('No') ?></dd></div>
          </dl>
          <table class="tbl"><?php foreach ($CHECKLIST as $items) foreach ($items as $k => $it): if (!isset($ans[$k])) continue; ?>
            <tr><td><?= h(t($it[3])) ?></td><td><span class="ans a-<?= h($ans[$k]) ?>"><?= h(['ok' => t('Sì'), 'usura' => t('In parte'), 'difetto' => t('No'), 'na' => t('Non so')][$ans[$k]]) ?></span></td></tr>
          <?php endforeach; ?></table>
        </section>
        <?php if (in_array($R['status'], ['richiesto', 'confermato'], true)): ?>
        <form method="post" class="card"><?= csrf() ?>
          <label><?= t('Nota per il cliente') ?> <textarea name="note" rows="2" placeholder="<?= h(t('Obbligatoria se annulli: motivo o orario alternativo')) ?>"></textarea></label>
          <div class="row">
            <a class="btn" href="<?= url('valuta', ['req' => $R['id']]) ?>"><?= t('Avvia valutazione in officina') ?></a>
            <?php if ($R['status'] === 'richiesto'): ?><button class="btn ghost" name="action" value="conferma"><?= t('Conferma appuntamento') ?></button><?php endif; ?>
            <button class="btn ghost" name="action" value="annulla"><?= t('Annulla') ?></button>
          </div>
        </form>
        <?php elseif ($R['valuation_id']): ?><p><a class="btn" href="<?= url('valutazione', ['id' => $R['valuation_id']]) ?>"><?= t('Apri valutazione #') ?><?= $R['valuation_id'] ?></a></p><?php endif;
        page_end(); break;
    }
    $st = db()->prepare("SELECT * FROM requests WHERE dealer_id = ? ORDER BY CASE WHEN status IN ('richiesto','confermato') THEN 0 ELSE 1 END, appt_at");
    $st->execute([$u['id']]);
    page_start(t('Appuntamenti')); ?>
    <h1><?= t('APPUNTAMENTI <em>CLIENTI</em>') ?></h1>
    <?php render_requests($st->fetchAll(), false);
    page_end(); break;

case 'richieste':
    require_login('niu');
    page_start(t('Richieste clienti')); ?>
    <h1><?= t('RICHIESTE <em>CLIENTI</em>') ?></h1>
    <?php render_requests(db()->query('SELECT r.*, u.company FROM requests r JOIN users u ON u.id = r.dealer_id ORDER BY r.id DESC')->fetchAll(), true);
    page_end(); break;

// ===========================================================================
// PUBBLICO — vetrina, dettaglio, feed per global.niu.com
// ===========================================================================
case 'usato':
    $cat = $_GET['cat'] ?? '';
    $sql = "SELECT l.*, (SELECT file FROM photos WHERE listing_id = l.id ORDER BY sort, id LIMIT 1) cover FROM listings l WHERE status = 'pubblicato'";
    $args = [];
    if (isset($CFG['categories'][$cat])) { $sql .= ' AND category = ?'; $args[] = $cat; }
    $st = db()->prepare($sql . ' ORDER BY published_at DESC'); $st->execute($args);
    $rows = $st->fetchAll();
    page_start(t('Usato NIU certificato'), true); ?>
    <section class="hero">
      <h1><em>2ND HAND</em> NIU</h1>
      <p><?= t('Veicoli NIU usati, controllati dai dealer autorizzati con la checklist ufficiale NIU.') ?></p>
      <p><a class="btn" href="<?= url('stima') ?>"><?= t('Valuta il tuo NIU') ?></a></p>
      <div class="filters">
        <a href="<?= url('usato') ?>" class="<?= $cat === '' ? 'on' : '' ?>"><?= t('Tutti') ?></a>
        <?php foreach ($CFG['categories'] as $c => $l): ?><a href="<?= url('usato', ['cat' => $c]) ?>" class="<?= $cat === $c ? 'on' : '' ?>"><?= h(strtok(t($l), ' (')) ?></a><?php endforeach; ?>
      </div>
    </section>
    <div class="cards">
      <?php foreach ($rows as $r): ?>
        <a class="lcard" href="<?= url('annuncio', ['id' => $r['id']]) ?>">
          <div class="ph"><?php if ($r['cover']): ?><img src="<?= url('img', ['f' => $r['cover']]) ?>" alt="" loading="lazy"><?php endif; ?><?= grade_badge($r['grade']) ?></div>
          <div class="bd"><h3><?= h($r['title']) ?></h3>
            <p class="muted"><?= h((string)$r['year']) ?> · <?= nf((int)$r['km']) ?> <?= t('km ·') ?> <?= h($r['city']) ?><?= $r['province'] ? ' (' . h($r['province']) . ')' : '' ?></p>
            <p class="price"><?= eur($r['price']) ?></p></div>
        </a>
      <?php endforeach; ?>
      <?php if (!$rows): ?><p class="muted"><?= t('Nessun veicolo disponibile al momento.') ?></p><?php endif; ?>
    </div>
    <?php page_end(); break;

case 'annuncio':
    $st = db()->prepare("SELECT * FROM listings WHERE id = ?");
    $st->execute([(int)($_GET['id'] ?? 0)]); $L = $st->fetch();
    $u = user();
    $canSee = $L && ($L['status'] === 'pubblicato' || ($u && ($u['role'] === 'niu' || (int)$u['id'] === (int)$L['dealer_id'])));
    if (!$canSee) { http_response_code(404); page_start(t('Non trovato'), true); echo '<p>' . t('Annuncio non disponibile.') . '</p>'; page_end(); break; }
    page_start($L['title'], true);
    render_listing_detail($L, false);
    page_end(); break;

case 'feed':
    // Feed JSON degli annunci pubblicati, da consumare dalla sezione 2nd Hand di global.niu.com
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $base = base_url();
    $items = [];
    foreach (db()->query("SELECT * FROM listings WHERE status = 'pubblicato' ORDER BY published_at DESC")->fetchAll() as $r) {
        $items[] = [
            'id' => (int)$r['id'], 'title' => $r['title'], 'model' => $r['model'], 'category' => $r['category'],
            'year' => $r['year'] ? (int)$r['year'] : null, 'km' => (int)$r['km'], 'grade' => $r['grade'],
            'price' => ['amount' => (int)$r['price'], 'currency' => 'EUR'],
            'location' => ['city' => $r['city'], 'province' => $r['province'], 'country' => 'IT'],
            'description' => $r['description'], 'dealer_contact' => $r['contact'],
            'photos' => array_map(fn($ph) => $base . url('img', ['f' => $ph['file']]), listing_photos((int)$r['id'])),
            'url' => $base . url('annuncio', ['id' => $r['id']]), 'published_at' => $r['published_at'],
        ];
    }
    echo json_encode(['generated_at' => date('c'), 'count' => count($items), 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

case 'img':
    $f = basename((string)($_GET['f'] ?? ''));
    $path = "$SHARED/uploads/$f";
    if (!preg_match('/^[a-f0-9]{24}\.jpg$/', $f) || !is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($path));
    readfile($path); exit;

default:
    http_response_code(404);
    page_start(t('Non trovato')); echo '<p>' . t('Pagina non trovata.') . '</p>'; page_end();
}

// ===========================================================================
// Componenti
// ===========================================================================
function render_portal(array $d): void
{
    $yn = fn($ok, $date) => '<span class="' . ($ok ? 'ok' : 'red') . '">' . ($ok ? t('Attiva') . '</span> ' . t('fino al') . ' ' : t('Scaduta') . '</span> ' . t('il') . ' ') . h(date('d/m/Y', strtotime($date)));
    ?>
    <dl class="portal">
      <div><dt><?= t('Prima vendita') ?></dt><dd><?= h(date('d/m/Y', strtotime($d['first_sale_date']))) ?> · <?= h($d['country']) ?></dd></div>
      <div><dt><?= t('Garanzia veicolo') ?></dt><dd><?= $yn($d['vehicle_warranty_ok'], $d['vehicle_warranty_end']) ?></dd></div>
      <div><dt><?= t('Garanzia batteria') ?></dt><dd><?= $yn($d['battery_warranty_ok'], $d['battery_warranty_end']) ?></dd></div>
      <div><dt><?= t('Ultimo tagliando') ?></dt><dd><?= h(date('d/m/Y', strtotime($d['last_service_date']))) ?></dd></div>
      <div><dt><?= t('Km ultima connessione IoT') ?></dt><dd><?= nf((int)$d['odometer_last_km']) ?> <?= t('km') ?></dd></div>
      <div><dt><?= t('Segnalazione furto') ?></dt><dd><?= $d['stolen_flag'] ? '<span class="red">' . t('SÌ — non procedere') . '</span>' : t('Nessuna') ?></dd></div>
      <div><dt><?= t('Campagne tecniche') ?></dt><dd><?php if (!$d['campaigns']) echo t('Nessuna');
        foreach ($d['campaigns'] as $c) echo h($c['code'] . ' ' . $c['title']) . ' — ' . ($c['done'] ? '<span class="ok">' . t('eseguita') . '</span>' : '<span class="red">' . t('da eseguire') . '</span>') . '<br>'; ?></dd></div>
    </dl>
<?php }

function render_requests(array $rows, bool $niu): void
{
    if (!$rows) { echo '<p class="muted">' . t('Nessuna richiesta dai clienti.') . '</p>'; return; }
    $lab = ['richiesto' => t('Da confermare'), 'confermato' => t('Confermato'), 'annullato' => t('Annullato'), 'completato' => t('Valutato')];
    $badge = ['richiesto' => 'in_revisione', 'confermato' => 'pubblicato', 'annullato' => 'rifiutato', 'completato' => 'venduto']; ?>
    <table class="tbl">
      <tr><th><?= t('Appuntamento') ?></th><?php if ($niu): ?><th><?= t('Dealer') ?></th><?php endif; ?><th><?= t('Cliente') ?></th><th><?= t('Veicolo') ?></th><th><?= t('Stima') ?></th><th><?= t('NIU nuovo') ?></th><th><?= t('Stato') ?></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= $niu ? h(it_date($r['appt_at'])) : '<a href="' . url('appuntamenti', ['id' => $r['id']]) . '"><b>' . h(it_date($r['appt_at'])) . '</b></a>' ?></td>
          <?php if ($niu): ?><td><?= h($r['company']) ?></td><?php endif; ?>
          <td><?= h($r['name']) ?></td><td><?= h($r['model']) ?> · <?= nf((int)$r['km']) ?> <?= t('km') ?></td>
          <td><?= grade_badge($r['grade']) ?></td><td><?= $r['wants_new'] ? t('Sì') . ($r['new_model'] ? ' — ' . h($r['new_model']) : '') : '—' ?></td>
          <td><span class="badge s-<?= $badge[$r['status']] ?>"><?= h($lab[$r['status']]) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
<?php }

function render_valuations(array $rows, bool $showDealer): void
{
    if (!$rows) { echo '<p class="muted">' . t('Nessuna valutazione.') . '</p>'; return; } ?>
    <table class="tbl">
      <tr><th>#</th><th><?= t('Data') ?></th><?php if ($showDealer): ?><th><?= t('Dealer') ?></th><?php endif; ?><th><?= t('Modello') ?></th><th><?= t('Telaio') ?></th><th><?= t('Classe') ?></th><th><?= t('Stato') ?></th><th><?= t('Extra NIU') ?></th></tr>
      <?php foreach ($rows as $v): ?>
        <tr><td><a href="<?= url('valutazione', ['id' => $v['id']]) ?>">#<?= $v['id'] ?></a></td><td><?= h(substr($v['created_at'], 0, 10)) ?></td>
          <?php if ($showDealer): ?><td><?= h($v['company']) ?></td><?php endif; ?>
          <td><?= h($v['model']) ?></td><td class="mono"><?= h($v['vin']) ?></td><td><?= grade_badge($v['grade']) ?> <?= (int)$v['score'] ?></td>
          <td><?= h(['valutato' => t('Da decidere'), 'ritirato' => t('Ritirato'), 'non_ritirato' => t('Non ritirato')][$v['status']] ?? $v['status']) ?><?= $v['new_purchase'] ? ' + ' . t('nuovo') : '' ?></td>
          <td><?= $v['incentive_niu'] ? eur($v['incentive_niu']) : '—' ?></td></tr>
      <?php endforeach; ?>
    </table>
<?php }

function render_listing_table(array $rows, string $target, bool $showDealer = false): void
{
    if (!$rows) { echo '<p class="muted">' . t('Nessun annuncio. Gli annunci si creano da una valutazione con ritiro registrato.') . '</p>'; return; } ?>
    <table class="tbl">
      <tr><th></th><th><?= t('Annuncio') ?></th><?php if ($showDealer): ?><th><?= t('Dealer') ?></th><?php endif; ?><th><?= t('Prezzo') ?></th><th><?= t('Località') ?></th><th><?= t('Stato') ?></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr><td class="tcover"><?php if ($r['cover']): ?><img src="<?= url('img', ['f' => $r['cover']]) ?>" alt=""><?php endif; ?></td>
          <td><a href="<?= url($target, ['id' => $r['id']]) ?>"><b><?= h($r['title']) ?></b></a><br><span class="muted"><?= h($r['model']) ?> <?= grade_badge($r['grade']) ?></span></td>
          <?php if ($showDealer): ?><td><?= h($r['company']) ?></td><?php endif; ?>
          <td><?= $r['price'] ? eur($r['price']) : '—' ?></td><td><?= h($r['city']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
      <?php endforeach; ?>
    </table>
<?php }

function render_listing_detail(array $L, bool $admin): void
{
    global $CFG;
    $photos = listing_photos((int)$L['id']); ?>
    <article class="detail">
      <div class="gallery">
        <?php if ($photos): ?>
          <img id="main" src="<?= url('img', ['f' => $photos[0]['file']]) ?>" alt="">
          <div class="strip"><?php foreach ($photos as $ph): ?><img src="<?= url('img', ['f' => $ph['file']]) ?>" alt="" onclick="document.getElementById('main').src=this.src"><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
      <div class="info">
        <?php if ($admin) echo status_badge($L['status']); ?>
        <h1><?= h($L['title']) ?></h1>
        <p class="price"><?= eur($L['price']) ?></p>
        <dl class="specs">
          <div><dt><?= t('Modello') ?></dt><dd><?= h($L['model']) ?></dd></div>
          <div><dt><?= t('Anno') ?></dt><dd><?= h((string)$L['year']) ?></dd></div>
          <div><dt><?= t('Chilometri') ?></dt><dd><?= nf((int)$L['km']) ?></dd></div>
          <div><dt><?= t('Classe NIU') ?></dt><dd><?= grade_badge($L['grade']) ?> <?= h(t($CFG['grades'][$L['grade']]['label'] ?? '')) ?></dd></div>
          <div><dt><?= t('Dove') ?></dt><dd><?= h($L['city']) ?><?= $L['province'] ? ' (' . h($L['province']) . ')' : '' ?></dd></div>
          <div><dt><?= t('Venduto da') ?></dt><dd><?= h($L['contact']) ?></dd></div>
        </dl>
        <div class="desc"><?= nl2br(h($L['description'])) ?></div>
      </div>
    </article>
<?php }
