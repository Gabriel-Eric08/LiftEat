<div class="auth-card">
    <div class="brand">
        <span class="brand-mark"><?= icon('dumbbell', '', 22) ?></span>
        <span>Lift<em>Eat</em></span>
    </div>
    <div class="auth-title">
        <h1>Instalação</h1>
        <p>Banco de dados e administrador.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= icon('circle-check', '', 18) ?><span><?= e($success) ?></span></div>
    <?php endif ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= icon('triangle-alert', '', 18) ?><span><?= e($error) ?></span></div>
    <?php endif ?>

    <?php if ($dbError): ?>
        <div class="alert alert-danger"><?= icon('server-crash', '', 18) ?><span>Não foi possível acessar o banco: <?= e($dbError) ?></span></div>
    <?php else: ?>
        <section class="card stack">
            <div class="row-between"><h2>1. Banco de dados</h2><?= $pending === [] ? '<span class="badge badge-success">ok</span>' : '<span class="badge badge-warning">pendente</span>' ?></div>
            <?php if ($pending === []): ?>
                <p class="small muted">Banco atualizado. Nenhuma migração pendente.</p>
            <?php else: ?>
                <ul class="small" style="margin:0;padding-left:18px">
                    <?php foreach ($pending as $migration): ?>
                        <li><code class="mono"><?= e($migration) ?></code></li>
                    <?php endforeach ?>
                </ul>
                <form method="post" action="/install/migrate">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <button type="submit" class="btn btn-primary btn-block">Aplicar migrações</button>
                </form>
            <?php endif ?>
        </section>

        <?php if ($pending === []): ?>
            <section class="card stack">
                <div class="row-between"><h2>2. Administrador</h2><?= $adminExists ? '<span class="badge badge-success">ok</span>' : '<span class="badge badge-warning">pendente</span>' ?></div>
                <?php if ($adminExists): ?>
                    <p class="small muted">Administrador já cadastrado. Remova <code class="mono">INSTALL_TOKEN</code> do <code class="mono">.env</code> para desativar esta página.</p>
                    <a href="/admin/login" class="btn btn-outline btn-block">Ir para o painel</a>
                <?php else: ?>
                    <form method="post" action="/install/admin" class="stack">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="field"><label class="label">Nome</label><input class="input" name="name" required></div>
                        <div class="field"><label class="label">E-mail</label><input class="input" name="email" type="email" required></div>
                        <div class="field"><label class="label">Senha (mín. 8 caracteres)</label><input class="input" name="password" type="password" minlength="8" required></div>
                        <button type="submit" class="btn btn-primary btn-block">Criar administrador</button>
                    </form>
                <?php endif ?>
            </section>
        <?php endif ?>
    <?php endif ?>
</div>
