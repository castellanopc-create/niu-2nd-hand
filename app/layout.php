<?php
function page_start(string $title, bool $public = false): void
{
    global $CFG;
    $u = user();
    ?><!DOCTYPE html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · <?= h($CFG['app_name']) ?></title>
<link rel="icon" href="assets/niu-bull-red.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= $public ? 'public' : 'app' ?>">
<header class="top">
  <a class="brand" href="<?= $u ? url('home') : url('usato') ?>">
    <img src="assets/niu-logo-white.png" alt="NIU">
    <span>2ND <b>HAND</b></span>
  </a>
  <nav>
    <?php if ($u && $u['role'] === 'dealer'): ?>
      <a href="<?= url('home') ?>"><?= t('Dashboard') ?></a>
      <a href="<?= url('appuntamenti') ?>"><?= t('Appuntamenti') ?></a>
      <a href="<?= url('valuta') ?>"><?= t('Nuova valutazione') ?></a>
      <a href="<?= url('annunci') ?>"><?= t('I miei annunci') ?></a>
    <?php elseif ($u && $u['role'] === 'niu'): ?>
      <a href="<?= url('home') ?>"><?= t('Dashboard') ?></a>
      <a href="<?= url('revisione') ?>"><?= t('Revisione annunci') ?></a>
      <a href="<?= url('valutazioni') ?>"><?= t('Valutazioni') ?></a>
      <a href="<?= url('richieste') ?>"><?= t('Richieste clienti') ?></a>
      <a href="<?= url('dealer') ?>"><?= t('Dealer') ?></a>
    <?php endif; ?>
    <?php if (!$u): ?><a href="<?= url('stima') ?>"><?= t('Valuta il tuo NIU') ?></a><?php endif; ?>
    <a href="<?= url('usato') ?>"><?= t('Vetrina usato') ?></a>
    <?php if ($u): ?>
      <form method="post" action="<?= url('logout') ?>" class="inline"><?= csrf() ?><button class="link"><?= t('Esci') ?></button></form>
    <?php else: ?>
      <a href="<?= url('login') ?>"><?= t('Area dealer') ?></a>
    <?php endif; ?>
    <?php if (!empty($CFG['i18n_enabled'])): ?><details class="lang"><summary><?= strtoupper(lang()) ?></summary>
      <div><?php foreach (LANGS as $c => $n): ?><a href="<?= h(lang_url($c)) ?>" class="<?= $c === lang() ? 'on' : '' ?>" hreflang="<?= $c ?>"><?= h($n) ?></a><?php endforeach; ?></div>
    </details><?php endif; ?>
  </nav>
</header>
<main>
<?php foreach (flash() as [$t, $m]): ?><div class="flash <?= h($t) ?>"><?= h($m) ?></div><?php endforeach;
}

function page_end(): void
{ ?>
</main>
<footer class="bottom"><span>niu.com</span><span><?= t('Prototipo — dati del portale simulati') ?></span></footer>
</body></html>
<?php }

function status_badge(string $s): string
{
    $labels = ['bozza' => t('Bozza'), 'in_revisione' => t('In revisione NIU'), 'pubblicato' => t('Pubblicato'),
               'rifiutato' => t('Da correggere'), 'venduto' => t('Venduto'), 'ritirato' => t('Ritirato')];
    return '<span class="badge s-' . h($s) . '">' . h($labels[$s] ?? $s) . '</span>';
}

function grade_badge(?string $g): string
{
    return $g ? '<span class="grade g-' . h($g) . '">' . h($g) . '</span>' : '';
}
