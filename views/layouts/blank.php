<!doctype html>
<html lang="pt-BR" data-theme="<?= theme() ?>">
<head>
    <?= \App\Core\View::partial('head', ['title' => $title ?? '']) ?>
</head>
<body>
<?= \App\Core\View::partial('theme-toggle', ['class' => 'theme-fab']) ?>
<?= \App\Core\View::partial('flash') ?>
<main class="auth">
    <?= $content ?>
</main>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
