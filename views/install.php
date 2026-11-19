<h1>Instalação</h1>

<?php if ($success): ?>
    <p style="color:#1a7f37"><strong><?= e($success) ?></strong></p>
<?php endif ?>
<?php if ($error): ?>
    <p style="color:#cf222e"><strong><?= e($error) ?></strong></p>
<?php endif ?>

<?php if ($dbError): ?>
    <p style="color:#cf222e">Não foi possível acessar o banco: <?= e($dbError) ?></p>
<?php else: ?>

    <h2>1. Banco de dados</h2>
    <?php if ($pending === []): ?>
        <p>Banco atualizado. Nenhuma migração pendente.</p>
    <?php else: ?>
        <p>Migrações pendentes:</p>
        <ul>
            <?php foreach ($pending as $migration): ?>
                <li><code><?= e($migration) ?></code></li>
            <?php endforeach ?>
        </ul>
        <form method="post" action="/install/migrate">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <button type="submit">Aplicar migrações</button>
        </form>
    <?php endif ?>

    <?php if ($pending === []): ?>
        <h2>2. Administrador</h2>
        <?php if ($adminExists): ?>
            <p>Administrador já cadastrado. Remova <code>INSTALL_TOKEN</code> do <code>.env</code> para desativar esta página.</p>
        <?php else: ?>
            <form method="post" action="/install/admin">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <p><label>Nome<br><input name="name" required></label></p>
                <p><label>E-mail<br><input name="email" type="email" required></label></p>
                <p><label>Senha (mín. 8 caracteres)<br><input name="password" type="password" minlength="8" required></label></p>
                <button type="submit">Criar administrador</button>
            </form>
        <?php endif ?>
    <?php endif ?>

<?php endif ?>
