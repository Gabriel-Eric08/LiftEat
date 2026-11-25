<?php
use App\Core\View;

/** @var array $student @var list<array> $diets */
$first = e(first_name($student['name']));
$hasActive = array_filter($diets, static fn ($d) => (bool) $d['is_active']) !== [];
?>
<div class="stack" style="--gap:12px;padding-top:20px">
    <div class="section-title">
        <h2>Planos alimentares</h2>
        <button type="button" class="btn btn-primary btn-sm" data-open="new-diet"><?= icon('plus', '', 16) ?><span>Novo plano</span></button>
    </div>

    <?php if ($diets === []): ?>
        <div class="card">
            <?= View::partial('empty', [
                'icon'    => 'salad',
                'heading' => 'Nenhum plano alimentar ainda',
                'text'    => 'Crie o plano de ' . first_name($student['name']) . ' com as metas do dia. O primeiro plano já fica ativo no app.',
                'action'  => '<button type="button" class="btn btn-primary" data-open="new-diet">' . icon('plus', '', 16) . '<span>Criar plano</span></button>',
            ]) ?>
        </div>
    <?php else: ?>
        <?php if (!$hasActive): ?>
            <div class="alert alert-warning"><?= icon('triangle-alert', '', 18) ?><span>Nenhum plano ativo: <?= $first ?> não vê dieta no app. Ative um dos planos abaixo.</span></div>
        <?php endif ?>

        <div class="grid grid-2" style="--gap:12px">
            <?php foreach ($diets as $d):
                $targets = array_filter([
                    'kcal' => $d['target_kcal'], 'prot' => $d['target_protein_g'],
                    'carb' => $d['target_carbs_g'], 'gord' => $d['target_fat_g'],
                ], static fn ($v) => $v !== null); ?>
                <article class="card stack" style="--gap:10px;padding:16px<?= $d['is_active'] ? '' : ';opacity:.7' ?>">
                    <div class="row-between" style="align-items:flex-start">
                        <div style="min-width:0">
                            <div class="row" style="--gap:8px">
                                <span class="bold truncate"><?= e($d['name']) ?></span>
                                <?php if ($d['is_active']): ?><span class="badge badge-success">ativo</span><?php endif ?>
                            </div>
                            <div class="xsmall muted"><?= (int) $d['meal_count'] ?> <?= (int) $d['meal_count'] === 1 ? 'refeição' : 'refeições' ?> · atualizado <?= e(date_br($d['updated_at'] ?? $d['created_at'])) ?></div>
                        </div>
                        <div class="row" style="--gap:4px">
                            <?php if (!$d['is_active']): ?>
                                <form method="post" action="/admin/dietas/<?= (int) $d['id'] ?>/ativar"
                                      data-confirm="<?= e($d['name']) ?> passa a ser o plano que <?= $first ?> vê no app. O plano ativo atual será desativado." data-confirm-label="Ativar plano" data-confirm-tone="primary">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-soft btn-sm"><?= icon('check', '', 14) ?><span>Ativar</span></button>
                                </form>
                            <?php endif ?>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-open="edit-diet-<?= (int) $d['id'] ?>" title="Editar" aria-label="Editar"><?= icon('pencil', '', 16) ?></button>
                            <form method="post" action="/admin/dietas/<?= (int) $d['id'] ?>/excluir"
                                  data-confirm="<?= e($d['name']) ?> e todas as refeições dele serão excluídos.<?= $d['is_active'] ? ' ' . $first . ' fica sem plano no app até você ativar outro.' : '' ?>" data-confirm-label="Excluir plano">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-icon btn-sm" title="Excluir" aria-label="Excluir"><?= icon('trash-2', '', 16) ?></button>
                            </form>
                        </div>
                    </div>

                    <?php if ($targets): ?>
                        <div class="grid grid-4" style="--gap:8px">
                            <?php foreach ($targets as $label => $val): ?>
                                <div class="center" style="padding:8px 4px;border-radius:12px;background:var(--surface-2)">
                                    <div class="bold num"><?= (int) $val ?><span class="xsmall muted"><?= $label === 'kcal' ? '' : 'g' ?></span></div>
                                    <div class="xsmall muted"><?= e($label) ?></div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>

                    <?php if ($d['notes']): ?><p class="small soft" style="white-space:pre-line"><?= e($d['notes']) ?></p><?php endif ?>
                </article>

                <dialog class="modal" id="edit-diet-<?= (int) $d['id'] ?>">
                    <form method="post" action="/admin/dietas/<?= (int) $d['id'] ?>">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h2>Editar plano</h2>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
                        </div>
                        <div class="modal-body stack">
                            <?= View::render('admin/diets/_fields', ['prefix' => 'ed' . (int) $d['id'], 'plan' => $d]) ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
                            <button type="submit" class="btn btn-primary"><?= icon('check', '', 18) ?><span>Salvar</span></button>
                        </div>
                    </form>
                </dialog>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<dialog class="modal" id="new-diet">
    <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/dietas">
        <?= csrf_field() ?>
        <div class="modal-header">
            <h2>Novo plano alimentar</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <?= View::render('admin/diets/_fields', ['prefix' => 'nd']) ?>
            <?php if ($hasActive): ?>
                <label class="switch">
                    <input type="checkbox" name="activate" value="1">
                    <span class="switch-track"></span>
                    <span class="grow">
                        <span class="small bold" style="display:block">Ativar agora</span>
                        <span class="xsmall muted">Substitui o plano ativo atual no app de <?= $first ?>.</span>
                    </span>
                </label>
            <?php else: ?>
                <p class="xsmall muted"><?= icon('info', '', 12) ?> Este plano já fica ativo no app de <?= $first ?>.</p>
            <?php endif ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><?= icon('check', '', 18) ?><span>Criar plano</span></button>
        </div>
    </form>
</dialog>
