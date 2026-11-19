<div class="auth-card" style="max-width:460px">
    <div class="brand">
        <span class="brand-mark"><?= icon('dumbbell', '', 22) ?></span>
        <span>Lift<em>Eat</em></span>
    </div>
    <div class="auth-title">
        <h1>Instalação</h1>
        <p>Banco de dados, administradores e dados de teste.</p>
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
                <div class="row-between"><h2>2. Administradores</h2><?= $admins ? '<span class="badge badge-success">' . count($admins) . '</span>' : '<span class="badge badge-warning">nenhum</span>' ?></div>
                <?php if ($admins): ?>
                    <div class="list">
                        <?php foreach ($admins as $a): ?>
                            <div class="list-item">
                                <span class="avatar avatar-sm"><?= e(initials($a['name'])) ?></span>
                                <div class="grow">
                                    <div class="small bold"><?= e($a['name']) ?></div>
                                    <div class="xsmall muted"><?= e($a['email']) ?> · <?= $a['last_login_at'] ? 'último login ' . e(date_br($a['last_login_at'], 'd/m H:i')) : 'nunca entrou' ?></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                <form method="post" action="/install/admin" class="stack">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="field"><label class="label">Nome</label><input class="input" name="name" required></div>
                    <div class="field"><label class="label">E-mail</label><input class="input" name="email" type="email" required></div>
                    <div class="field"><label class="label">Senha (mín. 8 caracteres)</label><input class="input" name="password" type="password" minlength="8" required></div>
                    <button type="submit" class="btn btn-primary btn-block"><?= $admins ? 'Adicionar administrador' : 'Criar administrador' ?></button>
                </form>
            </section>

            <section class="card stack">
                <div class="row-between"><h2>3. Dados de teste</h2><?= $demo ? '<span class="badge badge-info">inseridos</span>' : '<span class="badge badge-neutral">vazio</span>' ?></div>
                <p class="small muted">
                    6 alunos fictícios (e-mails <code class="mono"><?= e(\App\Services\DemoSeeder::DOMAIN) ?></code>, senha <code class="mono"><?= e(\App\Services\DemoSeeder::PASSWORD) ?></code>)
                    em situações diferentes, com mensalidades, treinos, dieta e histórico. Teste o app com <code class="mono">ana<?= e(\App\Services\DemoSeeder::DOMAIN) ?></code>.
                </p>
                <?php if ($demo): ?>
                    <form method="post" action="/install/demo/remover" onsubmit="return confirm('Remover todos os alunos de teste e o que pertence a eles?')">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button type="submit" class="btn btn-danger btn-block"><?= icon('trash-2', '', 16) ?><span>Remover dados de teste</span></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/install/demo">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button type="submit" class="btn btn-outline btn-block"><?= icon('sparkles', '', 16) ?><span>Inserir dados de teste</span></button>
                    </form>
                <?php endif ?>
            </section>

            <p class="xsmall muted center">Terminou? Remova <code class="mono">INSTALL_TOKEN</code> do <code class="mono">.env</code> para desativar esta página.</p>
        <?php endif ?>
    <?php endif ?>
</div>
