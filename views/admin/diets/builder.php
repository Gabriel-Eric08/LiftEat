<?php
use App\Services\DietMeals;

/** @var array $plan @var array $student @var list<array> $meals */

// Rascunho volta quando a validação falha, para não perder o que foi digitado.
$draft = flash('diet_draft');
$initial = is_array($draft) ? $draft : array_map(static fn ($m) => [
    'name'      => $m['name'],
    'meal_time' => $m['meal_time'] === null ? '' : substr((string) $m['meal_time'], 0, 5),
    'notes'     => (string) $m['notes'],
    'options'   => array_map(static fn ($o) => [
        'name'  => $o['name'],
        'notes' => (string) $o['notes'],
        'items' => array_map(static fn ($i) => [
            'food'     => $i['food'],
            'quantity' => $i['quantity'],
            'notes'    => (string) $i['notes'],
        ], $o['items']),
    ], $m['options']),
], $meals);

$targets = array_filter([
    'kcal' => $plan['target_kcal'], 'prot' => $plan['target_protein_g'],
    'carb' => $plan['target_carbs_g'], 'gord' => $plan['target_fat_g'],
], static fn ($v) => $v !== null);
?>
<form method="post" action="/admin/dietas/<?= (int) $plan['id'] ?>/refeicoes" class="grid builder" data-diet-builder>
    <?= csrf_field() ?>
    <input type="hidden" name="meals" value="" data-meals-input>

    <div class="stack" style="--gap:16px;min-width:0">
        <div class="page-header reveal">
            <div>
                <h1><?= e($plan['name']) ?></h1>
                <p>Plano alimentar de <a href="/admin/alunos/<?= (int) $student['id'] ?>?aba=dieta" class="text-primary"><?= e($student['name']) ?></a>
                    · <?= $plan['is_active'] ? '<span class="badge badge-success">ativo no app</span>' : '<span class="badge badge-neutral">inativo</span>' ?></p>
            </div>
            <button type="button" class="btn btn-soft" data-add-meal><?= icon('plus', '', 18) ?><span>Adicionar refeição</span></button>
        </div>

        <div class="stack" style="--gap:12px" data-meals></div>

        <div class="card card-glow reveal" data-empty hidden>
            <?= \App\Core\View::partial('empty', [
                'icon'    => 'utensils',
                'heading' => 'Nenhuma refeição ainda',
                'text'    => 'Adicione as refeições do dia com horário. Cada refeição pode ter opções para o aluno escolher.',
                'action'  => '<button type="button" class="btn btn-primary" data-add-meal>' . icon('plus', '', 16) . '<span>Adicionar refeição</span></button>',
            ]) ?>
        </div>

        <button type="button" class="btn btn-outline btn-block" data-add-meal data-add-more hidden><?= icon('plus', '', 16) ?><span>Adicionar outra refeição</span></button>
    </div>

    <aside class="stack builder-aside" style="--gap:16px">
        <section class="card stack reveal" style="--i:1;padding:16px;--gap:12px">
            <span class="label">Resumo</span>
            <div class="grid grid-3 center" style="--gap:8px">
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-meals>0</div><div class="xsmall muted">refeições</div></div>
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-options>0</div><div class="xsmall muted">opções</div></div>
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-items>0</div><div class="xsmall muted">alimentos</div></div>
            </div>
            <?php if ($targets): ?>
                <div class="xsmall muted center">Metas: <?= e(implode(' · ', array_map(
                    static fn ($k, $v) => (int) $v . ($k === 'kcal' ? ' kcal' : 'g ' . $k),
                    array_keys($targets),
                    $targets,
                ))) ?></div>
            <?php endif ?>
            <button type="button" class="btn btn-ghost btn-sm btn-block" data-sort-time><?= icon('clock', '', 16) ?><span>Ordenar refeições por horário</span></button>
        </section>

        <div class="stack reveal" style="--i:2;--gap:8px">
            <button type="submit" class="btn btn-primary btn-lg btn-block"><?= icon('check', '', 18) ?><span>Salvar refeições</span></button>
            <button type="submit" name="after" value="aluno" class="btn btn-ghost btn-block">Salvar e voltar para o aluno</button>
            <span class="xsmall muted center" data-dirty hidden>Alterações não salvas</span>
        </div>

        <p class="xsmall muted reveal" style="--i:3">O aluno vê as refeições nesta ordem. Com mais de uma opção, ele escolhe qual seguir.</p>

        <section class="card stack reveal" style="--i:4;padding:16px;--gap:10px">
            <span class="label">Mais ações</span>
            <button type="button" class="btn btn-outline btn-sm btn-block" data-open="duplicate-diet-<?= (int) $plan['id'] ?>"><?= icon('repeat', '', 16) ?><span>Duplicar / copiar para aluno</span></button>
            <span class="xsmall muted">Salve antes de duplicar: a cópia leva as refeições já salvas.</span>
        </section>
    </aside>
</form>

<?= \App\Core\View::render('admin/diets/_duplicate-modal', ['plan' => $plan, 'students' => $students ?? []]) ?>

<template id="meal-template">
    <article class="card ex-row stack" style="--gap:12px">
        <div class="ex-row-head">
            <span class="ex-num mono"></span>
            <input class="input grow" maxlength="<?= DietMeals::MEAL_NAME_MAX ?>" placeholder="Nome da refeição (ex.: Café da manhã)" aria-label="Nome da refeição" data-k="name">
            <input class="input" type="time" style="width:120px" aria-label="Horário" data-k="meal_time">
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="meal-up" aria-label="Subir refeição"><?= icon('chevron-left', 'rot-90', 16) ?></button>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="meal-down" aria-label="Descer refeição"><?= icon('chevron-right', 'rot-90', 16) ?></button>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="meal-remove" aria-label="Remover refeição"><?= icon('trash-2', '', 16) ?></button>
        </div>
        <input class="input" maxlength="<?= DietMeals::NOTES_MAX ?>" placeholder="Observação da refeição (opcional)" aria-label="Observação da refeição" data-k="notes">
        <div class="stack" style="--gap:10px" data-options></div>
        <button type="button" class="btn btn-ghost btn-sm" style="align-self:flex-start" data-act="option-add"><?= icon('plus', '', 14) ?><span>Adicionar opção</span></button>
    </article>
</template>

<template id="option-template">
    <div class="stack" style="--gap:8px;padding:12px;border:1px solid var(--border);border-radius:12px">
        <div class="row" style="--gap:8px">
            <input class="input grow" maxlength="<?= DietMeals::OPTION_NAME_MAX ?>" aria-label="Nome da opção" data-k="name">
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="option-remove" aria-label="Remover opção"><?= icon('x', '', 16) ?></button>
        </div>
        <div class="stack" style="--gap:6px" data-items></div>
        <div class="row wrap" style="--gap:8px">
            <button type="button" class="btn btn-soft btn-sm" data-act="item-add"><?= icon('plus', '', 14) ?><span>Alimento</span></button>
            <input class="input grow" maxlength="<?= DietMeals::NOTES_MAX ?>" placeholder="Observação da opção (opcional)" aria-label="Observação da opção" data-k="notes">
        </div>
    </div>
</template>

<template id="item-template">
    <div class="row wrap" style="--gap:6px">
        <input class="input grow" style="min-width:160px" maxlength="<?= DietMeals::FOOD_MAX ?>" placeholder="Alimento" aria-label="Alimento" data-k="food">
        <input class="input" style="width:130px" maxlength="<?= DietMeals::QUANTITY_MAX ?>" placeholder="Qtd. (100 g)" aria-label="Quantidade" data-k="quantity">
        <input class="input grow" style="min-width:120px" maxlength="<?= DietMeals::NOTES_MAX ?>" placeholder="Obs. (opcional)" aria-label="Observação do alimento" data-k="notes">
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="item-up" aria-label="Subir alimento"><?= icon('chevron-left', 'rot-90', 14) ?></button>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="item-down" aria-label="Descer alimento"><?= icon('chevron-right', 'rot-90', 14) ?></button>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="item-remove" aria-label="Remover alimento"><?= icon('x', '', 14) ?></button>
    </div>
</template>

<script type="application/json" id="diet-builder-data"><?= json_encode([
    'meals'      => $initial,
    'maxMeals'   => DietMeals::MAX_MEALS,
    'maxOptions' => DietMeals::MAX_OPTIONS,
    'maxItems'   => DietMeals::MAX_ITEMS,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
