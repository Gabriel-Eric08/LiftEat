<?php
use App\Core\View;

$tabs = [
    ['/app', 'house', 'Hoje', true],
    ['/app/treinos', 'dumbbell', 'Treinos', false],
    ['/app/dieta', 'salad', 'Dieta', false],
    ['/app/historico', 'history', 'Histórico', false],
    ['/app/perfil', 'user', 'Perfil', false],
];
?>
<!doctype html>
<html lang="pt-BR" data-theme="<?= theme() ?>">
<head>
    <?= View::partial('head', ['title' => $title ?? '']) ?>
</head>
<body class="is-app">
<?= View::partial('flash') ?>

<div class="app">
    <header class="app-header">
        <div class="grow">
            <?php if (!empty($eyebrow)): ?><div class="eyebrow"><?= e($eyebrow) ?></div><?php endif ?>
            <h1 class="truncate"><?= e($heading ?? $title ?? '') ?></h1>
        </div>
        <?= $headerAction ?? View::partial('theme-toggle') ?>
    </header>

    <main class="app-content">
        <?= $content ?>
    </main>

    <?php if (!isset($hideNav) || !$hideNav): ?>
        <nav class="bottom-nav" aria-label="Navegação">
            <?php foreach ($tabs as [$href, $ic, $label, $exact]): ?>
                <a href="<?= $href ?>" class="<?= nav_active($href, $exact) ? 'is-active' : '' ?>" <?= nav_active($href, $exact) ? 'aria-current="page"' : '' ?>>
                    <?= icon($ic, '', 22) ?>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach ?>
        </nav>
    <?php endif ?>
</div>

<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
