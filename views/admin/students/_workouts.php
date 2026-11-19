<?php
use App\Core\View;

/** @var array $student @var array $workouts @var array $schedule @var array $sessions @var array $templates */
$activeWorkouts = array_values(array_filter($workouts, static fn ($w) => (bool) $w['is_active']));
?>
<div class="stack" style="--gap:24px;padding-top:20px">

    <!-- Agenda da semana -->
    <section class="stack" style="--gap:12px">
        <div class="section-title">
            <h2>Semana de <?= e(first_name($student['name'])) ?></h2>
            <?php if ($activeWorkouts): ?><span class="xsmall muted">Mude o treino de cada dia; salva sozinho.</span><?php endif ?>
        </div>
        <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/agenda" class="week-grid" data-schedule-form>
            <?= csrf_field() ?>
            <?php for ($d = 1; $d <= 7; $d++): $current = $schedule[$d] ?? null; ?>
                <label class="week-day <?= $current ? 'has-workout' : '' ?> <?= $d === (int) date('N') ? 'is-today' : '' ?>">
                    <span class="xsmall bold"><?= e(weekday_name($d, true)) ?><?= $d === (int) date('N') ? ' · hoje' : '' ?></span>
                    <span class="week-day-name truncate"><?= $current ? e($current['name']) : 'Descanso' ?></span>
                    <select class="select" name="day_<?= $d ?>" aria-label="Treino de <?= e(weekday_name($d)) ?>" <?= $activeWorkouts ? '' : 'disabled' ?>>
                        <option value="">Descanso</option>
                        <?php foreach ($activeWorkouts as $w): ?>
                            <option value="<?= (int) $w['id'] ?>" <?= $current && (int) $current['id'] === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
            <?php endfor ?>
        </form>
    </section>

    <!-- Treinos -->
    <section class="stack" style="--gap:12px">
        <div class="section-title">
            <h2>Treinos</h2>
            <button type="button" class="btn btn-primary btn-sm" data-open="new-workout"><?= icon('plus', '', 16) ?><span>Novo treino</span></button>
        </div>

        <?php if ($workouts === []): ?>
            <div class="card">
                <?= View::partial('empty', [
                    'icon'    => 'dumbbell',
                    'heading' => 'Nenhum treino ainda',
                    'text'    => 'Crie o primeiro treino de ' . first_name($student['name']) . ' do zero ou a partir de um modelo de outro aluno.',
                    'action'  => '<button type="button" class="btn btn-primary" data-open="new-workout">' . icon('plus', '', 16) . '<span>Criar treino</span></button>',
                ]) ?>
            </div>
        <?php else: ?>
            <div class="grid grid-3" style="--gap:12px">
                <?php foreach ($workouts as $w): ?>
                    <a href="/admin/treinos/<?= (int) $w['id'] ?>/editar" class="card card-link stack" style="--gap:10px;padding:16px<?= $w['is_active'] ? '' : ';opacity:.55' ?>">
                        <div class="row-between" style="align-items:flex-start">
                            <div style="min-width:0">
                                <div class="bold truncate"><?= e($w['name']) ?></div>
                                <div class="xsmall muted"><?= (int) $w['exercise_count'] ?> exercícios · <?= (int) $w['set_count'] ?> séries</div>
                            </div>
                            <?= $w['is_active'] ? icon('chevron-right', 'muted', 18) : '<span class="badge badge-neutral">arquivado</span>' ?>
                        </div>
                        <div class="row" style="--gap:4px">
                            <?php for ($d = 1; $d <= 7; $d++): $on = in_array($d, $w['days'], true); ?>
                                <span class="mini-day <?= $on ? 'on' : '' ?>" title="<?= e(weekday_name($d)) ?>"><?= e(mb_substr(weekday_name($d), 0, 1)) ?></span>
                            <?php endfor ?>
                        </div>
                        <div class="xsmall muted"><?= $w['last_done_at'] ? 'Último: ' . e(relative_days($w['last_done_at'])) : 'Ainda não realizado' ?></div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>

    <!-- Treinos realizados -->
    <section class="stack" style="--gap:12px">
        <div class="section-title"><h2>Treinos realizados</h2></div>
        <?php if ($sessions === []): ?>
            <p class="small muted">Quando <?= e(first_name($student['name'])) ?> iniciar treinos pelo app, eles aparecem aqui com cargas e fotos.</p>
        <?php else: ?>
            <div class="card card-flush">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Data</th><th>Treino</th><th class="hide-sm">Duração</th><th class="hide-sm">Séries</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($sessions as $s):
                            $mins = $s['finished_at'] ? (int) round((strtotime($s['finished_at']) - strtotime($s['started_at'])) / 60) : null; ?>
                            <tr>
                                <td class="small num"><?= e(date_br($s['started_at'], 'd/m H:i')) ?></td>
                                <td>
                                    <a href="/admin/sessoes/<?= (int) $s['id'] ?>" class="small bold"><?= e($s['workout_name']) ?></a>
                                    <?php if ($s['status'] === 'in_progress'): ?><span class="badge badge-info">treinando</span><?php elseif ($s['status'] === 'abandoned'): ?><span class="badge badge-neutral">abandonado</span><?php endif ?>
                                    <?php if ($s['photos']): ?><span class="muted xsmall"> · <?= icon('camera', '', 12) ?> <?= (int) $s['photos'] ?></span><?php endif ?>
                                </td>
                                <td class="hide-sm small muted"><?= $mins !== null ? $mins . ' min' : '—' ?></td>
                                <td class="hide-sm small muted"><?= (int) $s['sets_done'] ?></td>
                                <td class="actions"><a href="/admin/sessoes/<?= (int) $s['id'] ?>" class="btn btn-ghost btn-icon btn-sm" aria-label="Ver"><?= icon('chevron-right', '', 16) ?></a></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>
    </section>
</div>

<dialog class="modal" id="new-workout">
    <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/treinos">
        <?= csrf_field() ?>
        <div class="modal-header">
            <h2>Novo treino</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <div class="field">
                <label class="label" for="nw-name">Nome</label>
                <input class="input" id="nw-name" name="name" maxlength="120" required
                       placeholder="Treino <?= e(chr(65 + min(count($workouts), 25))) ?> — ex.: Peito e tríceps">
            </div>
            <div class="field">
                <label class="label" for="nw-template">Começar a partir de</label>
                <select class="select" id="nw-template" name="template_id">
                    <option value="">Treino em branco</option>
                    <?php $lastStudent = null; foreach ($templates as $t): ?>
                        <?php if ($lastStudent !== $t['student_name']): ?>
                            <?= $lastStudent !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($t['student_name']) ?>">
                            <?php $lastStudent = $t['student_name']; endif ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> (<?= (int) $t['exercise_count'] ?> exercícios)</option>
                    <?php endforeach ?>
                    <?= $lastStudent !== null ? '</optgroup>' : '' ?>
                </select>
                <span class="hint">Copia os exercícios de um treino que você já montou.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><span>Criar e montar</span><?= icon('arrow-up-right', '', 16) ?></button>
        </div>
    </form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Agenda salva sozinha ao trocar o treino de um dia.
    const form = document.querySelector('[data-schedule-form]');
    form?.addEventListener('change', async (e) => {
        const sel = e.target.closest('select');
        if (!sel) return;
        const day = sel.closest('.week-day');
        day.querySelector('.week-day-name').textContent = sel.value ? sel.selectedOptions[0].textContent : 'Descanso';
        day.classList.toggle('has-workout', !!sel.value);
        window.LiftEat?.startProgress();
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error();
            day.classList.add('saved');
            setTimeout(() => day.classList.remove('saved'), 900);
        } catch {
            alert('Não foi possível salvar a agenda. Recarregue a página e tente de novo.');
        } finally {
            window.LiftEat?.endProgress();
        }
    });
});
</script>
