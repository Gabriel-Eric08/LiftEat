<article class="card <?= e($class) ?>" style="padding:16px">
    <div class="row-between">
        <span class="small bold"><?= e($opt['name']) ?></span>
        <span class="xsmall muted"><?= count($opt['items']) ?> itens</span>
    </div>
    <div class="mt-2">
        <?php foreach ($opt['items'] as $item): ?>
            <div class="food-item">
                <span><?= e($item['food']) ?><?php if ($item['notes']): ?><span class="xsmall muted" style="display:block"><?= e($item['notes']) ?></span><?php endif ?></span>
                <span class="qty"><?= e($item['quantity']) ?></span>
            </div>
        <?php endforeach ?>
        <?php if ($opt['items'] === []): ?><p class="xsmall muted">Sem itens.</p><?php endif ?>
    </div>
    <?php if ($opt['notes']): ?><p class="xsmall soft mt-2"><?= e($opt['notes']) ?></p><?php endif ?>
</article>
