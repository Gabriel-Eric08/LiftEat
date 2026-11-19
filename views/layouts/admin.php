<?php
use App\Core\Auth;
use App\Core\View;

$admin = Auth::admin();

$nav = [
    'Visão geral' => [
        ['/admin', 'layout-dashboard', 'Dashboard', true],
        ['/admin/financeiro', 'wallet', 'Financeiro', false],
    ],
    'Gestão' => [
        ['/admin/alunos', 'users', 'Alunos', false],
        ['/admin/treinos', 'dumbbell', 'Treinos', false],
        ['/admin/exercicios', 'list-checks', 'Exercícios', false],
        ['/admin/dietas', 'salad', 'Dietas', false],
    ],
    'Conta' => [
        ['/admin/perfil', 'user', 'Pessoal', false],
    ],
];
$soon = ['/admin/dietas'];
?>
<!doctype html>
<html lang="pt-BR" data-theme="<?= theme() ?>">
<head>
    <?= View::partial('head', ['title' => $title ?? '']) ?>
</head>
<body>
<?= View::partial('flash') ?>

<div class="admin">
    <aside class="sidebar" aria-label="Menu principal">
        <a href="/admin" class="brand">
            <span class="brand-mark"><?= icon('dumbbell', '', 18) ?></span>
            <span>Lift<em>Eat</em></span>
        </a>

        <nav class="nav">
            <?php foreach ($nav as $section => $links): ?>
                <div class="nav-label"><?= e($section) ?></div>
                <?php foreach ($links as [$href, $ic, $label, $exact]): ?>
                    <a href="<?= $href ?>" class="nav-link <?= nav_active($href, $exact) ? 'is-active' : '' ?>">
                        <?= icon($ic, '', 18) ?>
                        <span><?= e($label) ?></span>
                        <?php if (in_array($href, $soon, true)): ?><span class="soon">em breve</span><?php endif ?>
                    </a>
                <?php endforeach ?>
            <?php endforeach ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <span class="avatar avatar-sm"><?= e(initials($admin['name'] ?? 'P')) ?></span>
                <div class="grow">
                    <div class="small bold truncate"><?= e($admin['name'] ?? '') ?></div>
                    <div class="xsmall muted truncate"><?= e($admin['email'] ?? '') ?></div>
                </div>
                <form method="post" action="/admin/logout" data-no-loading>
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-icon btn-sm" title="Sair" aria-label="Sair"><?= icon('log-out', '', 16) ?></button>
                </form>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="admin-main">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon menu-toggle" data-sidebar-toggle aria-label="Abrir menu"><?= icon('menu') ?></button>
            <div class="crumbs grow truncate">
                <?php foreach ($breadcrumbs ?? [] as $label => $href): ?>
                    <?php if ($href): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?= icon('chevron-right', '', 14) ?><?php else: ?><span class="topbar-title"><?= e($label) ?></span><?php endif ?>
                <?php endforeach ?>
                <?php if (empty($breadcrumbs)): ?><span class="topbar-title"><?= e($title ?? '') ?></span><?php endif ?>
            </div>
            <?= View::partial('theme-toggle') ?>
            <a href="/admin/alunos/novo" class="btn btn-primary btn-sm hide-sm"><?= icon('user-plus', '', 16) ?><span>Novo aluno</span></a>
        </header>

        <main class="admin-content">
            <?= $content ?>
        </main>
    </div>
</div>

<?php foreach ($scripts ?? [] as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
<?php endforeach ?>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?= $inlineScript ?? '' ?>
</body>
</html>
