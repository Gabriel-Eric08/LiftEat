<div class="input-group">
    <?= icon('lock', '', 18) ?>
    <input class="input <?= error_for($name) ? 'has-error' : '' ?>" type="password" id="<?= e($id ?? $name) ?>" name="<?= e($name) ?>"
           placeholder="<?= e($placeholder ?? '••••••••') ?>" autocomplete="<?= e($autocomplete ?? 'current-password') ?>"
           <?= !empty($required) ? 'required' : '' ?> <?= isset($minlength) ? 'minlength="' . (int) $minlength . '"' : '' ?>>
    <button type="button" class="input-action" data-toggle-password aria-label="Mostrar senha">
        <?= icon('eye', 'icon-eye', 18) ?><?= icon('eye-off', 'icon-eye-off', 18) ?>
    </button>
</div>
