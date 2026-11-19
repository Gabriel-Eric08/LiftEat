<?php
$messages = array_filter([
    'success' => flash('success'),
    'error'   => flash('error'),
    'info'    => flash('info'),
]);
$icons = ['success' => 'circle-check', 'error' => 'circle-x', 'info' => 'info'];
?>
<div class="toasts" role="status" aria-live="polite">
    <?php foreach ($messages as $type => $text): ?>
        <div class="toast toast-<?= $type ?>">
            <?= icon($icons[$type]) ?>
            <span><?= e($text) ?></span>
        </div>
    <?php endforeach ?>
</div>
