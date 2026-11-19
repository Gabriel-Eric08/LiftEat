<?php /** @var string $icon @var string $heading @var string $text */ ?>
<div class="empty">
    <div class="empty-icon"><?= icon($icon ?? 'sparkles', '', 28) ?></div>
    <h3><?= e($heading) ?></h3>
    <?php if (!empty($text)): ?><p><?= e($text) ?></p><?php endif ?>
    <?php if (!empty($action)): ?><div class="mt-2"><?= $action ?></div><?php endif ?>
</div>
