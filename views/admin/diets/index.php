<?php
use App\Core\View;

/**
 * @var list<array> $plans      cada plano ativo traz 'adherence' (resumo de 7 dias) quando há check-ins
 * @var int         $withActive
 * @var list<array> $without
 * @var string      $sort
 * @var bool        $checkins   false = migration dos check-ins ainda não aplicada
 */
$tone = static fn (?int $p): string => $p === null ? 'neutral' : ($p >= 80 ? 'success' : ($p >= 50 ? 'warning' : 'danger'));
$noCheckin = array_values(array_filter($plans, static fn ($p) => $p['adherence'] !== null && $p['adherence']['daysWithPlan'] > 0 && $p['adherence']['done'] === 0 && $p['student_status'] === 'active'));
?>
<div class="page-header reveal">
    <div>
        <h1>Dietas</h1>
        <p class="muted small mt-1"><?= $withActive ?> <?= $withActive === 1 ? 'aluno com plano ativo' : 'alunos com plano ativo' ?> · <?= count($plans) ?> <?= count($plans) === 1 ? 'plano' : 'planos' ?> no total</p>
    </div>
    <?php if ($checkins && $plans !== []): ?>
        <form method="get" data-no-loading>
            <label class="sr-only" for="ordem">Ordenar por</label>
            <select class="select" id="ordem" name="ordem" data-autosubmit aria-label="Ordenar por">
                <?php foreach (\App\Services\DietAdherence::SORTS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $key === $sort ? 'selected' : '' ?>>Ordenar: <?= e($label) ?></option>
                <?php endforeach ?>
            </select>
            <noscript><button class="btn btn-outline btn-sm">Ordenar</button></noscript>
        </form>
    <?php endif ?>
</div>

<?php if ($noCheckin !== []): ?>
    <section class="card reveal" style="--i:1">
        <div class="section-title">
            <h2>Sem check-in na semana</h2>
            <span class="xsmall muted">Têm plano ativo, mas não marcaram nenhuma refeição nos últimos 7 dias</span>
        </div>
        <div class="row wrap mt-2" style="--gap:8px">
            <?php foreach ($noCheckin as $p): ?>
                <a href="/admin/alunos/<?= (int) $p['student_id'] ?>?aba=dieta" class="badge badge-danger"><?= e($p['student_name']) ?></a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<?php if ($without !== []): ?>
    <section class="card reveal" style="--i:1">
        <div class="section-title">
            <h2>Sem plano ativo</h2>
            <span class="xsmall muted">Alunos ativos que não veem dieta no app</span>
        </div>
        <div class="row wrap mt-2" style="--gap:8px">
            <?php foreach ($without as $s): ?>
                <a href="/admin/alunos/<?= (int) $s['id'] ?>?aba=dieta" class="badge badge-warning"><?= e($s['name']) ?></a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<section class="card card-flush reveal" style="--i:2">
    <?php if ($plans === []): ?>
        <?= View::partial('empty', [
            'icon'    => 'salad',
            'heading' => 'Nenhum plano alimentar ainda',
            'text'    => 'Os planos são criados na ficha de cada aluno, na aba Dieta.',
            'action'  => '<a href="/admin/alunos" class="btn btn-primary">' . icon('users', '', 16) . '<span>Ir para alunos</span></a>',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Aluno</th><th>Plano</th><?php if ($checkins): ?><th>Adesão 7 dias</th><?php endif ?><th class="hide-sm">Metas</th><th class="hide-sm">Refeições</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($plans as $p):
                    $macros = array_filter([
                        $p['target_kcal'] !== null ? (int) $p['target_kcal'] . ' kcal' : null,
                        $p['target_protein_g'] !== null ? 'P ' . (int) $p['target_protein_g'] . 'g' : null,
                        $p['target_carbs_g'] !== null ? 'C ' . (int) $p['target_carbs_g'] . 'g' : null,
                        $p['target_fat_g'] !== null ? 'G ' . (int) $p['target_fat_g'] . 'g' : null,
                    ]); ?>
                    <tr<?= $p['is_active'] ? '' : ' style="opacity:.65"' ?>>
                        <td class="small bold"><?= e($p['student_name']) ?><?php if ($p['student_status'] !== 'active'): ?> <span class="badge badge-neutral">inativo</span><?php endif ?></td>
                        <td>
                            <span class="small"><?= e($p['name']) ?></span>
                            <?php if ($p['is_active']): ?><span class="badge badge-success">ativo</span><?php endif ?>
                        </td>
                        <?php if ($checkins): $a = $p['adherence']; ?>
                            <td class="small num">
                                <?php if ($a === null || $a['daysWithPlan'] === 0): ?>
                                    <span class="muted">—</span>
                                <?php elseif ($a['done'] === 0): ?>
                                    <span class="badge badge-danger">sem check-in</span>
                                <?php else: ?>
                                    <span class="badge badge-<?= $tone($a['percent']) ?>"><?= (int) $a['percent'] ?>%</span>
                                    <span class="xsmall muted hide-sm"><?= $a['done'] ?>/<?= $a['total'] ?></span>
                                <?php endif ?>
                            </td>
                        <?php endif ?>
                        <td class="hide-sm small muted num"><?= $macros ? e(implode(' · ', $macros)) : '—' ?></td>
                        <td class="hide-sm small muted"><?= (int) $p['meal_count'] ?></td>
                        <td class="actions"><a href="/admin/dietas/<?= (int) $p['id'] ?>/montar" class="btn btn-ghost btn-icon btn-sm" title="Refeições" aria-label="Refeições"><?= icon('utensils', '', 16) ?></a><a href="/admin/alunos/<?= (int) $p['student_id'] ?>?aba=dieta" class="btn btn-ghost btn-icon btn-sm" aria-label="Abrir"><?= icon('chevron-right', '', 16) ?></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
