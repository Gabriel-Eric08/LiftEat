<?php use App\Core\View; ?>
<div class="page-header reveal">
    <div>
        <h1>Treinos</h1>
        <p>Quem está treinando, quem precisa de ficha e o que foi feito.</p>
    </div>
    <a href="/admin/exercicios" class="btn btn-outline"><?= icon('list-checks', '', 18) ?><span>Biblioteca de exercícios</span></a>
</div>

<section class="grid grid-4">
    <div class="card card-glow stat reveal" style="--i:1">
        <div class="stat-label"><span class="stat-icon"><?= icon('flame', '', 18) ?></span>Hoje</div>
        <div class="stat-value"><?= (int) $stats['today'] ?></div>
        <div class="stat-foot">treinos concluídos</div>
    </div>
    <div class="card stat reveal" style="--i:2">
        <div class="stat-label"><span class="stat-icon"><?= icon('calendar-days', '', 18) ?></span>Esta semana</div>
        <div class="stat-value"><?= (int) $stats['week'] ?></div>
        <div class="stat-foot">desde segunda</div>
    </div>
    <div class="card stat reveal" style="--i:3">
        <div class="stat-label"><span class="stat-icon" style="background:var(--info-soft);color:var(--info)"><?= icon('users', '', 18) ?></span>Alunos treinando</div>
        <div class="stat-value"><?= (int) $stats['active_students'] ?></div>
        <div class="stat-foot">nos últimos 7 dias</div>
    </div>
    <div class="card stat reveal" style="--i:4">
        <div class="stat-label"><span class="stat-icon" style="background:var(--warning-soft);color:var(--warning)"><?= icon('notebook-pen', '', 18) ?></span>Com ficha</div>
        <div class="stat-value"><?= $withWorkout ?><span class="muted" style="font-size:1rem;font-weight:500"> / <?= $withWorkout + count($without) ?></span></div>
        <div class="stat-foot"><?= count($without) ?> ativo<?= count($without) === 1 ? '' : 's' ?> sem treino</div>
    </div>
</section>

<?php if ($live): ?>
    <section class="card reveal" style="--i:5">
        <div class="section-title"><h2><span class="live-dot"></span> Treinando agora</h2></div>
        <div class="list mt-2">
            <?php foreach ($live as $l): ?>
                <a href="/admin/sessoes/<?= (int) $l['id'] ?>" class="list-item">
                    <span class="avatar avatar-sm"><?= e(initials($l['name'])) ?></span>
                    <div class="grow">
                        <div class="small bold"><?= e($l['name']) ?> · <?= e($l['workout_name']) ?></div>
                        <div class="xsmall muted">começou <?= e(date_br($l['started_at'], 'H:i')) ?> · <?= (int) $l['sets_done'] ?> séries feitas</div>
                    </div>
                    <span class="badge badge-info">ao vivo</span>
                </a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<section class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,2fr);align-items:start" data-wo-split>
    <div class="card reveal" style="--i:6">
        <div class="section-title"><h2>Sem treino</h2></div>
        <?php if ($without === []): ?>
            <?= View::partial('empty', ['icon' => 'circle-check', 'heading' => 'Todo mundo com ficha', 'text' => 'Todos os alunos ativos têm treino na semana.']) ?>
        <?php else: ?>
            <div class="list mt-2">
                <?php foreach ($without as $s): ?>
                    <a href="/admin/alunos/<?= (int) $s['id'] ?>?aba=treinos" class="list-item">
                        <span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
                        <div class="grow">
                            <div class="small bold truncate"><?= e($s['name']) ?></div>
                            <div class="xsmall muted truncate"><?= e($s['goal'] ?? 'sem objetivo definido') ?></div>
                        </div>
                        <span class="btn btn-soft btn-sm"><?= icon('plus', '', 14) ?><span>Montar</span></span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <div class="card card-flush reveal" style="--i:7">
        <div class="section-title" style="padding:20px 20px 0"><h2>Treinos realizados</h2></div>
        <?php if ($recent === []): ?>
            <?= View::partial('empty', ['icon' => 'activity', 'heading' => 'Nenhum treino realizado ainda', 'text' => 'Quando os alunos usarem o "Iniciar treino" no app, tudo aparece aqui.']) ?>
        <?php else: ?>
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead><tr><th>Aluno</th><th>Treino</th><th class="hide-sm">Quando</th><th class="hide-sm">Séries</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td>
                                <div class="row" style="--gap:10px">
                                    <span class="avatar avatar-sm"><?= e(initials($r['student_name'])) ?></span>
                                    <span class="small bold truncate"><?= e(first_name($r['student_name'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <a href="/admin/sessoes/<?= (int) $r['id'] ?>" class="small"><?= e($r['workout_name']) ?></a>
                                <?php if ($r['status'] === 'abandoned'): ?><span class="badge badge-neutral">abandonado</span><?php endif ?>
                                <?php if ($r['photos']): ?><span class="muted xsmall"> <?= icon('camera', '', 12) ?> <?= (int) $r['photos'] ?></span><?php endif ?>
                            </td>
                            <td class="hide-sm small muted"><?= e(relative_days($r['started_at'])) ?>, <?= e(date_br($r['started_at'], 'H:i')) ?></td>
                            <td class="hide-sm small muted"><?= (int) $r['sets_done'] ?></td>
                            <td class="actions"><a href="/admin/sessoes/<?= (int) $r['id'] ?>" class="btn btn-ghost btn-icon btn-sm" aria-label="Ver"><?= icon('chevron-right', '', 16) ?></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</section>

<style>
    @media (max-width: 1024px) { [data-wo-split] { grid-template-columns: minmax(0, 1fr) !important; } }
</style>
