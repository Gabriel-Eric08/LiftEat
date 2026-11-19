<?php use App\Core\View; ?>

<?php if ($plan === null): ?>
    <section class="card card-glow reveal">
        <?= View::partial('empty', [
            'icon'    => 'salad',
            'heading' => 'Seu cardápio está no forno',
            'text'    => 'Seu personal ainda não montou seu plano alimentar. Assim que montar, suas refeições aparecem aqui.',
        ]) ?>
    </section>
<?php else: ?>
    <section class="card card-glow reveal">
        <div class="row-between">
            <div>
                <div class="xsmall muted">Plano atual</div>
                <h2 class="mt-1"><?= e($plan['name']) ?></h2>
            </div>
            <span class="stat-icon"><?= icon('apple', '', 18) ?></span>
        </div>
        <?php $targets = array_filter([
            'kcal' => $plan['target_kcal'], 'prot' => $plan['target_protein_g'],
            'carb' => $plan['target_carbs_g'], 'gord' => $plan['target_fat_g'],
        ]); ?>
        <?php if ($targets): ?>
            <div class="grid grid-4 mt-3" style="--gap:8px">
                <?php foreach ($targets as $label => $val): ?>
                    <div class="center" style="padding:10px 4px;border-radius:12px;background:var(--surface-2)">
                        <div class="bold num"><?= (int) $val ?><span class="xsmall muted"><?= $label === 'kcal' ? '' : 'g' ?></span></div>
                        <div class="xsmall muted"><?= e($label) ?></div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <?php if ($plan['notes']): ?><p class="small soft mt-3" style="white-space:pre-line"><?= e($plan['notes']) ?></p><?php endif ?>
    </section>

    <?php if ($plan['meals'] === []): ?>
        <div class="card"><?= View::partial('empty', ['icon' => 'utensils', 'heading' => 'Nenhuma refeição ainda', 'text' => 'O plano foi criado, mas ainda não tem refeições.']) ?></div>
    <?php endif ?>

    <?php foreach ($plan['meals'] as $i => $meal): ?>
        <section class="stack reveal" style="--i:<?= $i + 1 ?>;--gap:10px">
            <div class="row-between">
                <div class="row" style="--gap:10px">
                    <h2><?= e($meal['name']) ?></h2>
                    <?php if ($meal['meal_time']): ?><span class="meal-time"><?= e(substr($meal['meal_time'], 0, 5)) ?></span><?php endif ?>
                </div>
                <?php if (count($meal['options']) > 1): ?><span class="xsmall muted"><?= count($meal['options']) ?> opções <?= icon('chevron-right', '', 12) ?></span><?php endif ?>
            </div>
            <?php if ($meal['notes']): ?><p class="xsmall muted"><?= e($meal['notes']) ?></p><?php endif ?>

            <?php if (count($meal['options']) > 1): ?>
                <div class="h-scroll">
                    <?php foreach ($meal['options'] as $opt): ?>
                        <?= View::partial('meal-option', ['opt' => $opt, 'class' => 'meal-card']) ?>
                    <?php endforeach ?>
                </div>
            <?php elseif ($meal['options']): ?>
                <?= View::partial('meal-option', ['opt' => $meal['options'][0], 'class' => '']) ?>
            <?php endif ?>
        </section>
    <?php endforeach ?>
<?php endif ?>
