<?php
function page_start(string $title, bool $public = false): void
{
    global $CFG;
    $u = user();
    ?><!DOCTYPE html>
<html lang="it">
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
      <a href="<?= url('home') ?>">Dashboard</a>
      <a href="<?= url('appuntamenti') ?>">Appuntamenti</a>
      <a href="<?= url('valuta') ?>">Nuova valutazione</a>
      <a href="<?= url('annunci') ?>">I miei annunci</a>
    <?php elseif ($u && $u['role'] === 'niu'): ?>
      <a href="<?= url('home') ?>">Dashboard</a>
      <a href="<?= url('revisione') ?>">Revisione annunci</a>
      <a href="<?= url('valutazioni') ?>">Valutazioni</a>
      <a href="<?= url('richieste') ?>">Richieste clienti</a>
      <a href="<?= url('dealer') ?>">Dealer</a>
    <?php endif; ?>
    <?php if (!$u): ?><a href="<?= url('stima') ?>">Valuta il tuo NIU</a><?php endif; ?>
    <a href="<?= url('usato') ?>">Vetrina usato</a>
    <?php if ($u): ?>
      <form method="post" action="<?= url('logout') ?>" class="inline"><?= csrf() ?><button class="link">Esci</button></form>
    <?php else: ?>
      <a href="<?= url('login') ?>">Area dealer</a>
    <?php endif; ?>
  </nav>
</header>
<main>
<?php foreach (flash() as [$t, $m]): ?><div class="flash <?= h($t) ?>"><?= h($m) ?></div><?php endforeach;
}

function page_end(): void
{ ?>
</main>
<footer class="bottom"><span>niu.com</span><span>Prototipo — dati del portale simulati</span></footer>
</body></html>
<?php }

function status_badge(string $s): string
{
    $labels = ['bozza' => 'Bozza', 'in_revisione' => 'In revisione NIU', 'pubblicato' => 'Pubblicato',
               'rifiutato' => 'Da correggere', 'venduto' => 'Venduto', 'ritirato' => 'Ritirato'];
    return '<span class="badge s-' . h($s) . '">' . h($labels[$s] ?? $s) . '</span>';
}

function grade_badge(?string $g): string
{
    return $g ? '<span class="grade g-' . h($g) . '">' . h($g) . '</span>' : '';
}
