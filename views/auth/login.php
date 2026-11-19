<div class="auth-card">
    <div class="brand">
        <span class="brand-mark"><?= icon('dumbbell', '', 22) ?></span>
        <span>Lift<em>Eat</em></span>
    </div>

    <div class="auth-title">
        <h1><?= e($heading) ?></h1>
        <p><?= e($subtitle) ?></p>
    </div>

    <form method="post" action="<?= e($action) ?>" class="card stack" novalidate>
        <?= csrf_field() ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= icon('triangle-alert', '', 18) ?><span><?= e($error) ?></span></div>
        <?php endif ?>

        <div class="field">
            <label class="label" for="email">E-mail</label>
            <div class="input-group">
                <?= icon('mail', '', 18) ?>
                <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>"
                       placeholder="<?= e($emailPlaceholder) ?>" autocomplete="username" inputmode="email" required autofocus>
            </div>
        </div>

        <div class="field">
            <label class="label" for="password">Senha</label>
            <?= \App\Core\View::partial('password-input', ['name' => 'password', 'required' => true]) ?>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block mt-2">
            <span>Entrar</span><?= icon('arrow-up-right', '', 18) ?>
        </button>
    </form>

    <p class="auth-foot"><?= $foot ?></p>
</div>
