<?php
use App\Core\View;

$hours = intdiv((int) $totals['minutes'], 60);
?>
<section class="grid grid-3 reveal" style="--gap:10px">
    <div class="quick center" style="align-items:center">
        <div class="stat-value" style="font-size:1.4rem"><?= (int) $totals['total'] ?></div>
        <div class="xsmall muted">treinos</div>
    </div>
    <div class="quick center" style="align-items:center">
        <div class="stat-value" style="font-size:1.4rem"><?= (int) $totals['month'] ?></div>
        <div class="xsmall muted">este mês</div>
    </div>
    <div class="quick center" style="align-items:center">
        <div class="stat-value" style="font-size:1.4rem"><?= $hours ?>h</div>
        <div class="xsmall muted">treinando</div>
    </div>
</section>

<?php if ($grouped === []): ?>
    <section class="card card-glow reveal" style="--i:1">
        <?= View::partial('empty', [
            'icon'    => 'history',
            'heading' => 'Sua história começa no primeiro treino',
            'text'    => 'Cada treino que você concluir fica guardado aqui, com cargas e fotos, para você ver sua evolução.',
            'action'  => '<a href="/app/treinos" class="btn btn-primary">' . icon('dumbbell', '', 16) . '<span>Ver meus treinos</span></a>',
        ]) ?>
    </section>
<?php endif ?>

<?php $i = 1; foreach ($grouped as $ym => $sessions): [$y, $m] = explode('-', $ym); ?>
    <section class="stack reveal" style="--i:<?= $i++ ?>;--gap:10px">
        <h2 class="small muted" style="text-transform:capitalize"><?= e(month_name((int) $m)) ?> <?= $y !== date('Y') ? e($y) : '' ?></h2>
        <div class="card" style="padding:4px 16px">
            <div class="list">
                <?php foreach ($sessions as $s):
                    $mins = $s['finished_at'] ? (int) round((strtotime($s['finished_at']) - strtotime($s['started_at'])) / 60) : null;
                    ?>
                    <a href="/app/<?= $s['status'] === 'in_progress' ? 'sessao' : 'historico' ?>/<?= (int) $s['id'] ?>" class="list-item">
                        <div class="center" style="width:40px">
                            <div class="bold num"><?= e(date_br($s['started_at'], 'd')) ?></div>
                            <div class="xsmall muted"><?= e(weekday_name((int) date('N', strtotime($s['started_at'])), true)) ?></div>
                        </div>
                        <div class="grow">
                            <div class="small bold truncate"><?= e($s['workout_name']) ?></div>
                            <div class="xsmall muted row" style="--gap:10px">
                                <?php if ($mins !== null): ?><span><?= icon('timer', '', 12) ?> <?= $mins ?> min</span><?php endif ?>
                                <?php if ($s['sets_done']): ?><span><?= icon('repeat', '', 12) ?> <?= (int) $s['sets_done'] ?> séries</span><?php endif ?>
                                <?php if ($s['photos']): ?><span><?= icon('camera', '', 12) ?> <?= (int) $s['photos'] ?></span><?php endif ?>
                            </div>
                        </div>
                        <?php if ($s['status'] === 'completed'): ?>
                            <span class="text-primary"><?= icon('circle-check', '', 20) ?></span>
                        <?php elseif ($s['status'] === 'in_progress'): ?>
                            <span class="badge badge-info">agora</span>
                        <?php else: ?>
                            <span class="muted"><?= icon('circle-x', '', 20) ?></span>
                        <?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php endforeach ?>
