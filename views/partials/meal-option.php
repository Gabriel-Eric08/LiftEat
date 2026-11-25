<?php
/**
 * Card de uma opção de refeição no app.
 *
 * @var array  $opt    opção com itens
 * @var string $class  classes extras do card
 * @var string $action HTML opcional no rodapé (ex.: botão de check-in), já escapado
 */
$action ??= '';
?>
<article class="card <?= e($class) ?>" style="padding:16px<?= str_contains($class, 'is-muted') ? ';opacity:.55' : '' ?>">
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
    <?php if ($action !== ''): ?><div class="mt-3"><?= $action ?></div><?php endif ?>
</article>
