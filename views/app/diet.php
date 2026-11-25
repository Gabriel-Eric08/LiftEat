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
    <?php elseif ($progress !== null): ?>
        <section class="card stack reveal" style="--gap:8px;padding:16px">
            <div class="row-between">
                <span class="small bold">Hoje</span>
                <span class="small num"><strong><?= $progress['done'] ?></strong> <span class="muted">de <?= $progress['total'] ?> refeições</span></span>
            </div>
            <div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $progress['total'] ?>" aria-valuenow="<?= $progress['done'] ?>" aria-label="Refeições feitas hoje">
                <span style="width:<?= $progress['percent'] ?>%"></span>
            </div>
            <?php if ($progress['done'] === $progress['total']): ?><span class="xsmall text-primary"><?= icon('check', '', 12) ?> Dieta de hoje completa. Mandou bem!</span><?php endif ?>
        </section>
    <?php endif ?>

    <?php foreach ($plan['meals'] as $i => $meal):
        $key = \App\Services\DietCheckins::key($meal['name']);
        $isDone = $progress !== null && array_key_exists($key, $progress['byMeal']);
        $doneOption = $isDone ? $progress['byMeal'][$key] : null;
        $multi = count($meal['options']) > 1;
        // Formulário de check-in: em refeições com várias opções, o aluno marca a opção que seguiu.
        $checkForm = static function (?string $option, string $label, string $class) use ($meal): string {
            return '<form method="post" action="/app/dieta/feito">' . csrf_field()
                . '<input type="hidden" name="meal" value="' . e($meal['name']) . '">'
                . ($option !== null ? '<input type="hidden" name="option" value="' . e($option) . '">' : '')
                . '<button class="btn ' . $class . '">' . icon('check', '', 16) . '<span>' . e($label) . '</span></button></form>';
        };
        ?>
        <section class="stack reveal" id="<?= e(\App\Services\DietCheckins::anchor($meal['name'])) ?>" style="--i:<?= $i + 1 ?>;--gap:10px;scroll-margin-top:80px">
            <div class="row-between">
                <div class="row" style="--gap:10px">
                    <h2><?= e($meal['name']) ?></h2>
                    <?php if ($meal['meal_time']): ?><span class="meal-time"><?= e(substr($meal['meal_time'], 0, 5)) ?></span><?php endif ?>
                    <?php if ($isDone): ?><span class="badge badge-success"><?= icon('check', '', 12) ?>feito</span><?php endif ?>
                </div>
                <?php if ($multi && !$isDone): ?><span class="xsmall muted"><?= count($meal['options']) ?> opções <?= icon('chevron-right', '', 12) ?></span><?php endif ?>
            </div>
            <?php if ($meal['notes']): ?><p class="xsmall muted"><?= e($meal['notes']) ?></p><?php endif ?>

            <?php if ($multi): ?>
                <div class="h-scroll">
                    <?php foreach ($meal['options'] as $opt):
                        $chosen = $isDone && $doneOption !== null && \App\Services\DietCheckins::key($doneOption) === \App\Services\DietCheckins::key($opt['name']);
                        $action = $progress === null ? '' : ($chosen
                            ? '<span class="badge badge-success">' . icon('check', '', 12) . 'você seguiu esta</span>'
                            : $checkForm($opt['name'], $isDone ? 'Trocar para esta' : 'Segui esta', 'btn-soft btn-sm btn-block'));
                        ?>
                        <?= View::partial('meal-option', ['opt' => $opt, 'class' => 'meal-card' . ($isDone && !$chosen ? ' is-muted' : ''), 'action' => $action]) ?>
                    <?php endforeach ?>
                </div>
            <?php elseif ($meal['options']): ?>
                <?= View::partial('meal-option', ['opt' => $meal['options'][0], 'class' => '']) ?>
            <?php endif ?>

            <?php if ($progress !== null): ?>
                <?php if ($isDone): ?>
                    <form method="post" action="/app/dieta/desfazer">
                        <?= csrf_field() ?>
                        <input type="hidden" name="meal" value="<?= e($meal['name']) ?>">
                        <button class="btn btn-ghost btn-sm"><?= icon('x', '', 14) ?><span>Desmarcar</span></button>
                    </form>
                <?php elseif (!$multi): ?>
                    <?= $checkForm(null, 'Marcar como feito', 'btn-soft btn-block') ?>
                <?php endif ?>
            <?php endif ?>
        </section>
    <?php endforeach ?>
<?php endif ?>
