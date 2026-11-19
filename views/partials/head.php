<?php $t = theme(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="<?= $t === 'light' ? '#f6f7f6' : '#070807' ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') !== '' ? $title . ' · LiftEat' : 'LiftEat') ?></title>
<link rel="icon" href="data:,">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="LiftEat">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400..800&family=Geist+Mono:wght@400..600&display=swap">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script>
    // Transição entre páginas: se for interrompida (ex.: girar a tela), não gera erro no console.
    addEventListener('pagereveal', (e) => { const v = e.viewTransition; if (v) { v.ready.catch(() => {}); v.finished.catch(() => {}); } });
</script>
