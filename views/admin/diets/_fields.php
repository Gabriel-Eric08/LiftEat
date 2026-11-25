<?php
use App\Services\DietPlans;

/**
 * Campos do formulário de plano alimentar (criar e editar).
 *
 * @var string     $prefix prefixo dos ids, único por modal
 * @var array|null $plan   plano em edição (null = novo)
 */
$plan ??= null;
$val = static fn (string $k): string => $plan !== null && $plan[$k] !== null ? (string) $plan[$k] : '';
$units = ['target_kcal' => 'kcal', 'target_protein_g' => 'g', 'target_carbs_g' => 'g', 'target_fat_g' => 'g'];
?>
<div class="field">
    <label class="label" for="<?= e($prefix) ?>-name">Nome do plano</label>
    <input class="input" id="<?= e($prefix) ?>-name" name="name" maxlength="<?= DietPlans::NAME_MAX ?>" required
           value="<?= e($val('name')) ?>" placeholder="Ex.: Cutting dezembro">
</div>

<div>
    <span class="label">Metas diárias <span class="muted">(opcional)</span></span>
    <div class="grid grid-4 mt-1" style="--gap:8px">
        <?php foreach (DietPlans::TARGETS as $field => [$label, $max]): ?>
            <div class="field">
                <label class="xsmall muted" for="<?= e($prefix . '-' . $field) ?>"><?= e($label) ?> (<?= $units[$field] ?>)</label>
                <input class="input" id="<?= e($prefix . '-' . $field) ?>" name="<?= $field ?>" type="number"
                       min="0" max="<?= $max ?>" step="1" inputmode="numeric" value="<?= e($val($field)) ?>">
            </div>
        <?php endforeach ?>
    </div>
</div>

<div class="field">
    <label class="label" for="<?= e($prefix) ?>-notes">Observações <span class="muted">(o aluno vê no app)</span></label>
    <textarea class="textarea" id="<?= e($prefix) ?>-notes" name="notes" maxlength="<?= DietPlans::NOTES_MAX ?>"
              placeholder="Ex.: beber 3 L de água por dia, evitar frituras."><?= e($val('notes')) ?></textarea>
</div>
