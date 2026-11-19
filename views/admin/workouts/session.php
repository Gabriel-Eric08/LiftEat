<?php
$statusBadge = [
    'completed'   => ['Concluído', 'success'],
    'in_progress' => ['Em andamento', 'info'],
    'abandoned'   => ['Abandonado', 'neutral'],
][$session['status']];
?>
<div class="page-header reveal">
    <div>
        <h1><?= e($session['workout_name']) ?></h1>
        <p>
            <a href="/admin/alunos/<?= (int) $session['student_id'] ?>?aba=treinos" class="text-primary"><?= e($session['student_name']) ?></a>
            · <?= e(weekday_name((int) date('N', strtotime($session['started_at'])))) ?>, <?= e(date_br($session['started_at'], 'd/m/Y \à\s H:i')) ?>
        </p>
    </div>
    <span class="badge badge-<?= $statusBadge[1] ?>"><?= $statusBadge[0] ?></span>
</div>

<section class="grid grid-4">
    <div class="card stat reveal" style="--i:1"><div class="stat-label"><?= icon('timer', '', 16) ?>Duração</div><div class="stat-value" style="font-size:1.4rem"><?= $session['minutes'] !== null ? $session['minutes'] . ' min' : '—' ?></div></div>
    <div class="card stat reveal" style="--i:2"><div class="stat-label"><?= icon('repeat', '', 16) ?>Séries feitas</div><div class="stat-value" style="font-size:1.4rem"><?= (int) $session['sets_done'] ?></div></div>
    <div class="card stat reveal" style="--i:3"><div class="stat-label"><?= icon('weight', '', 16) ?>Volume</div><div class="stat-value" style="font-size:1.4rem"><?= e(number_format($session['volume'], 0, ',', '.')) ?> kg</div></div>
    <div class="card stat reveal" style="--i:4"><div class="stat-label"><?= icon('camera', '', 16) ?>Fotos</div><div class="stat-value" style="font-size:1.4rem"><?= count($session['photos']) ?></div></div>
</section>

<?php if ($session['notes']): ?>
    <div class="card reveal" style="--i:5">
        <div class="label">Comentário do aluno</div>
        <p class="soft mt-2" style="white-space:pre-line"><?= e($session['notes']) ?></p>
    </div>
<?php endif ?>

<?php if ($session['photos']): ?>
    <section class="card reveal" style="--i:6">
        <div class="section-title"><h2>Fotos</h2></div>
        <div class="photo-grid mt-3">
            <?php foreach ($session['photos'] as $p): ?>
                <a href="/media/fotos/<?= (int) $p['id'] ?>" target="_blank" class="photo"><img src="/media/fotos/<?= (int) $p['id'] ?>" alt="Foto do treino" loading="lazy"></a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<section class="stack reveal" style="--i:7;--gap:12px">
    <div class="section-title"><h2>Exercícios</h2></div>
    <?php if ($session['exercises'] === []): ?>
        <div class="card"><p class="small muted">Nenhuma série registrada nesse treino.</p></div>
    <?php endif ?>
    <div class="grid grid-2" style="--gap:12px">
        <?php foreach ($session['exercises'] as $ex): ?>
            <article class="card" style="padding:16px">
                <div class="row-between">
                    <div>
                        <div class="bold"><?= e($ex['name']) ?></div>
                        <div class="xsmall muted"><?= e($ex['muscle_group']) ?><?= $ex['planned'] ? ' · prescrito ' . e($ex['planned']) : '' ?></div>
                    </div>
                </div>
                <table class="table mt-2" style="font-size:.85rem">
                    <thead><tr><th style="padding:8px 4px">Série</th><th style="padding:8px 4px">Carga</th><th style="padding:8px 4px">Reps</th><th style="padding:8px 4px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($ex['sets'] as $set): ?>
                        <tr>
                            <td style="padding:8px 4px" class="mono"><?= (int) $set['set_number'] ?></td>
                            <td style="padding:8px 4px" class="num"><?= $set['load_kg'] !== null ? e(rtrim(rtrim(number_format((float) $set['load_kg'], 2, ',', ''), '0'), ',')) . ' kg' : '—' ?></td>
                            <td style="padding:8px 4px" class="num"><?= $set['reps'] !== null ? (int) $set['reps'] : '—' ?></td>
                            <td style="padding:8px 4px" class="right"><?= $set['completed'] ? '<span class="text-primary">' . icon('check', '', 14) . '</span>' : '<span class="muted">pulou</span>' ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </article>
        <?php endforeach ?>
    </div>
</section>
