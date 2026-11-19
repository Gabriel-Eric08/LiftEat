<?php
$fieldError = static fn (string $key) => error_for($key)
    ? '<span class="field-error">' . icon('circle-x', '', 14) . e(error_for($key)) . '</span>'
    : '';
?>
<div class="page-header reveal">
    <div>
        <h1>Pessoal</h1>
        <p>Seus dados de acesso ao painel.</p>
    </div>
</div>

<div class="grid grid-2" style="align-items:start">
    <form method="post" action="/admin/perfil" class="card stack reveal" style="--i:1">
        <?= csrf_field() ?>
        <div class="row" style="--gap:14px">
            <span class="avatar avatar-lg"><?= e(initials($admin['name'])) ?></span>
            <div>
                <h2><?= e($admin['name']) ?></h2>
                <p class="small muted">Personal · último acesso <?= $admin['last_login_at'] ? e(date_br($admin['last_login_at'], 'd/m H:i')) : '—' ?></p>
            </div>
        </div>
        <hr class="divider">
        <div class="field">
            <label class="label" for="name">Nome</label>
            <input class="input <?= error_for('name') ? 'has-error' : '' ?>" id="name" name="name" value="<?= e(old('name', $admin['name'])) ?>" required>
            <?= $fieldError('name') ?>
        </div>
        <div class="field">
            <label class="label" for="email">E-mail de login</label>
            <input class="input <?= error_for('email') ? 'has-error' : '' ?>" id="email" name="email" type="email" value="<?= e(old('email', $admin['email'])) ?>" required>
            <?= $fieldError('email') ?>
        </div>
        <div class="row" style="justify-content:flex-end">
            <button class="btn btn-primary"><?= icon('check', '', 18) ?><span>Salvar</span></button>
        </div>
    </form>

    <form method="post" action="/admin/perfil/senha" class="card stack reveal" style="--i:2" id="senha">
        <?= csrf_field() ?>
        <div class="card-header" style="margin:0"><h2>Alterar senha</h2><span class="muted"><?= icon('lock', '', 18) ?></span></div>
        <div class="field">
            <label class="label" for="current_password">Senha atual</label>
            <?= \App\Core\View::partial('password-input', ['name' => 'current_password', 'required' => true]) ?>
            <?= $fieldError('current_password') ?>
        </div>
        <div class="field">
            <label class="label" for="password">Nova senha</label>
            <?= \App\Core\View::partial('password-input', ['name' => 'password', 'autocomplete' => 'new-password', 'placeholder' => 'Mínimo 8 caracteres', 'required' => true, 'minlength' => 8]) ?>
            <?= $fieldError('password') ?>
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirmar nova senha</label>
            <?= \App\Core\View::partial('password-input', ['name' => 'password_confirmation', 'autocomplete' => 'new-password', 'required' => true]) ?>
        </div>
        <div class="row" style="justify-content:flex-end">
            <button class="btn btn-outline"><?= icon('lock', '', 16) ?><span>Alterar senha</span></button>
        </div>
    </form>
</div>
