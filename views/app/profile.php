<?php
$fieldError = static fn (string $key) => error_for($key)
    ? '<span class="field-error">' . icon('circle-x', '', 14) . e(error_for($key)) . '</span>'
    : '';
$sit = student_situation($student);
?>
<section class="card card-glow center reveal">
    <div class="stack" style="align-items:center;--gap:10px">
        <span class="avatar avatar-xl"><?= e(initials($student['name'])) ?></span>
        <div>
            <h2><?= e($student['name']) ?></h2>
            <p class="small muted"><?= e($student['email']) ?></p>
        </div>
        <span class="badge badge-<?= $sit['tone'] ?>"><?= e($sit['label']) ?></span>
    </div>
</section>

<section class="card reveal" style="--i:1">
    <dl class="kv">
        <dt>Acesso até</dt><dd><?= e(date_br($student['access_until'])) ?></dd>
        <dt>Renovação</dt><dd><?= $student['auto_renew'] ? 'Automática' : 'Manual' ?></dd>
        <?php if ($student['goal']): ?><dt>Objetivo</dt><dd><?= e($student['goal']) ?></dd><?php endif ?>
        <?php if ($student['height_cm']): ?><dt>Altura</dt><dd><?= (int) $student['height_cm'] ?> cm</dd><?php endif ?>
        <?php if ($student['weight_kg']): ?><dt>Peso</dt><dd><?= e(number_format((float) $student['weight_kg'], 1, ',', '')) ?> kg</dd><?php endif ?>
    </dl>
</section>

<section class="card reveal" style="--i:2;padding:6px 16px">
    <div class="list">
        <div class="list-item">
            <span class="stat-icon"><?= icon('moon', '', 18) ?></span>
            <span class="grow small bold">Tema escuro</span>
            <label class="switch" aria-label="Tema escuro">
                <input type="checkbox" <?= theme() === 'dark' ? 'checked' : '' ?> data-theme-switch>
                <span class="switch-track"></span>
            </label>
        </div>
        <button type="button" class="list-item" data-open="password-modal" style="background:none;border:0;border-bottom:1px solid var(--border);color:inherit;font:inherit;cursor:pointer;width:100%;text-align:left">
            <span class="stat-icon"><?= icon('lock', '', 18) ?></span>
            <span class="grow small bold">Alterar senha</span>
            <?= icon('chevron-right', 'muted', 18) ?>
        </button>
        <form method="post" action="/app/logout" class="list-item" data-no-loading style="border-bottom:0">
            <?= csrf_field() ?>
            <span class="stat-icon" style="background:var(--danger-soft);color:var(--danger)"><?= icon('log-out', '', 18) ?></span>
            <button class="grow small bold text-danger" style="background:none;border:0;font:inherit;text-align:left;cursor:pointer;padding:0">Sair</button>
        </form>
    </div>
</section>

<p class="xsmall muted center reveal" style="--i:3">Dica: no celular, use "Adicionar à tela inicial" para abrir o LiftEat como um app.</p>

<dialog class="modal" id="password-modal" <?= errors() ? 'data-autoopen' : '' ?>>
    <form method="post" action="/app/perfil/senha" id="senha">
        <?= csrf_field() ?>
        <div class="modal-header">
            <h2>Alterar senha</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <div class="field">
                <label class="label" for="current_password">Senha atual</label>
                <?= \App\Core\View::partial('password-input', ['name' => 'current_password', 'required' => true]) ?>
                <?= $fieldError('current_password') ?>
            </div>
            <div class="field">
                <label class="label" for="password">Nova senha</label>
                <?= \App\Core\View::partial('password-input', ['name' => 'password', 'autocomplete' => 'new-password', 'placeholder' => 'Mínimo 6 caracteres', 'required' => true, 'minlength' => 6]) ?>
                <?= $fieldError('password') ?>
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">Confirmar nova senha</label>
                <?= \App\Core\View::partial('password-input', ['name' => 'password_confirmation', 'autocomplete' => 'new-password', 'required' => true]) ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
    </form>
</dialog>
