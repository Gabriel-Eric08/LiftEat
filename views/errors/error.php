<div class="auth-card center">
    <div class="empty" style="padding:0">
        <div class="empty-icon"><?= icon($icon, '', 28) ?></div>
        <div class="error-code"><?= (int) $status ?></div>
        <h1 style="font-size:1.4rem"><?= e($heading) ?></h1>
        <p><?= e($message) ?></p>
    </div>

    <div class="row" style="justify-content:center">
        <button type="button" class="btn btn-outline" onclick="history.length > 1 ? history.back() : location.assign('<?= e($home) ?>')">
            <?= icon('arrow-left', '', 16) ?><span>Voltar</span>
        </button>
        <a href="<?= e($home) ?>" class="btn btn-primary"><?= icon('house', '', 16) ?><span>Início</span></a>
    </div>

    <?php if ($debug): ?>
        <div class="error-debug"><strong><?= e($debug::class) ?>: <?= e($debug->getMessage()) ?></strong>
<?= e($debug->getFile()) ?>:<?= (int) $debug->getLine() ?>

<?= e($debug->getTraceAsString()) ?></div>
    <?php endif ?>
</div>
