<?php
$isEdit = $student !== null;
$v = static fn (string $key, mixed $default = '') => old($key, $student[$key] ?? $default);
$err = static fn (string $key) => error_for($key);
$cls = static fn (string $key) => error_for($key) ? 'has-error' : '';
$fieldError = static fn (string $key) => error_for($key)
    ? '<span class="field-error">' . icon('circle-x', '', 14) . e(error_for($key)) . '</span>'
    : '';
$action = $isEdit ? "/admin/alunos/{$student['id']}" : '/admin/alunos';
$fee = $v('monthly_fee', '');
$fee = $fee === '' ? '' : number_format((float) str_replace(',', '.', (string) $fee), 2, ',', '');
$autoRenew = old('_submitted') ? (bool) old('auto_renew') : (bool) ($student['auto_renew'] ?? false);
?>
<div class="page-header reveal">
    <div>
        <h1><?= $isEdit ? 'Editar aluno' : 'Novo aluno' ?></h1>
        <p><?= $isEdit ? 'Atualize os dados de ' . e(first_name($student['name'])) . '.' : 'Cadastre o acesso e defina a mensalidade. Treino e dieta vêm depois.' ?></p>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start" data-form-split novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="_submitted" value="1">

    <div class="stack">
        <section class="card stack reveal" style="--i:1">
            <div class="card-header" style="margin:0"><h2>Acesso</h2><span class="muted"><?= icon('lock', '', 18) ?></span></div>
            <div class="field">
                <label class="label" for="name">Nome completo</label>
                <input class="input <?= $cls('name') ?>" id="name" name="name" value="<?= e($v('name')) ?>" placeholder="Ex.: Ana Souza" required autofocus>
                <?= $fieldError('name') ?>
            </div>
            <div class="grid grid-2">
                <div class="field">
                    <label class="label" for="email">E-mail (login)</label>
                    <input class="input <?= $cls('email') ?>" id="email" name="email" type="email" value="<?= e($v('email')) ?>" placeholder="ana@email.com" autocomplete="off" required>
                    <?= $fieldError('email') ?>
                </div>
                <div class="field">
                    <label class="label" for="password"><?= $isEdit ? 'Nova senha' : 'Senha' ?></label>
                    <?= \App\Core\View::partial('password-input', [
                        'name' => 'password', 'autocomplete' => 'new-password', 'required' => !$isEdit,
                        'placeholder' => $isEdit ? 'Deixe em branco para manter' : 'Mínimo 6 caracteres',
                    ]) ?>
                    <?= $fieldError('password') ?>
                </div>
            </div>
            <div class="field">
                <label class="label" for="phone">WhatsApp / telefone</label>
                <input class="input <?= $cls('phone') ?>" id="phone" name="phone" type="tel" value="<?= e($v('phone')) ?>" placeholder="(11) 90000-0000">
            </div>
        </section>

        <section class="card stack reveal" style="--i:2">
            <div class="card-header" style="margin:0"><h2>Perfil físico</h2><span class="muted"><?= icon('ruler', '', 18) ?></span></div>
            <div class="grid grid-3">
                <div class="field">
                    <label class="label" for="birth_date">Nascimento</label>
                    <input class="input <?= $cls('birth_date') ?>" id="birth_date" name="birth_date" type="date" value="<?= e($v('birth_date')) ?>">
                    <?= $fieldError('birth_date') ?>
                </div>
                <div class="field">
                    <label class="label" for="height_cm">Altura (cm)</label>
                    <input class="input <?= $cls('height_cm') ?>" id="height_cm" name="height_cm" inputmode="numeric" value="<?= e($v('height_cm')) ?>" placeholder="175">
                    <?= $fieldError('height_cm') ?>
                </div>
                <div class="field">
                    <label class="label" for="weight_kg">Peso (kg)</label>
                    <input class="input <?= $cls('weight_kg') ?>" id="weight_kg" name="weight_kg" inputmode="decimal" value="<?= e($v('weight_kg')) ?>" placeholder="72,5">
                    <?= $fieldError('weight_kg') ?>
                </div>
            </div>
            <div class="field">
                <label class="label" for="goal">Objetivo</label>
                <input class="input" id="goal" name="goal" value="<?= e($v('goal')) ?>" placeholder="Hipertrofia, emagrecimento, condicionamento…">
            </div>
            <div class="field">
                <label class="label" for="notes">Observações</label>
                <textarea class="textarea" id="notes" name="notes" placeholder="Lesões, restrições, preferências… só você vê."><?= e($v('notes')) ?></textarea>
            </div>
        </section>
    </div>

    <aside class="stack" style="position:sticky;top:calc(var(--topbar-h) + 24px)">
        <section class="card card-glow stack reveal" style="--i:3">
            <div class="card-header" style="margin:0"><h2>Plano</h2><span class="text-primary"><?= icon('wallet', '', 18) ?></span></div>
            <div class="field">
                <label class="label" for="monthly_fee">Mensalidade</label>
                <div class="input-group">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.9rem">R$</span>
                    <input class="input <?= $cls('monthly_fee') ?>" id="monthly_fee" name="monthly_fee" inputmode="decimal" value="<?= e($fee) ?>" placeholder="150,00" style="padding-left:40px" required>
                </div>
                <?= $fieldError('monthly_fee') ?>
            </div>

            <label class="switch">
                <input type="checkbox" name="auto_renew" value="1" <?= $autoRenew ? 'checked' : '' ?>>
                <span class="switch-track"></span>
                <span class="grow">
                    <span class="small bold" style="display:block">Renovação automática</span>
                    <span class="xsmall muted">Ao vencer, libera +1 mês e lança a cobrança como pendente.</span>
                </span>
            </label>

            <div class="field">
                <label class="label" for="status">Situação</label>
                <select class="select" id="status" name="status">
                    <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= $v('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inativo (bloqueado)</option>
                </select>
            </div>

            <?php if (!$isEdit): ?>
                <hr class="divider">
                <label class="switch">
                    <input type="checkbox" name="first_payment" value="1" <?= old('_submitted') ? (old('first_payment') ? 'checked' : '') : 'checked' ?>>
                    <span class="switch-track"></span>
                    <span class="grow">
                        <span class="small bold" style="display:block">Já pagou o 1º mês</span>
                        <span class="xsmall muted">Registra o pagamento e libera o acesso até <?= e(date_br(\App\Services\Billing::period(new DateTimeImmutable('today'))[1]->format('Y-m-d'))) ?>.</span>
                    </span>
                </label>
                <div class="field">
                    <label class="label" for="method">Forma de pagamento</label>
                    <select class="select" id="method" name="method">
                        <?php foreach ($methods as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= old('method', 'pix') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
        </section>

        <div class="row reveal" style="--i:4">
            <a href="<?= $isEdit ? "/admin/alunos/{$student['id']}" : '/admin/alunos' ?>" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary grow"><?= icon('check', '', 18) ?><span><?= $isEdit ? 'Salvar alterações' : 'Cadastrar aluno' ?></span></button>
        </div>
    </aside>
</form>

<style>
    @media (max-width: 900px) {
        [data-form-split] { grid-template-columns: minmax(0, 1fr) !important; }
        [data-form-split] aside { position: static !important; }
    }
</style>
