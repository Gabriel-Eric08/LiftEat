<?php use App\Core\View; ?>

<nav class="h-scroll reveal" aria-label="Dias da semana">
    <?php for ($d = 1; $d <= 7; $d++): ?>
        <a href="?dia=<?= $d ?>" class="day-pill <?= $d === $day ? 'is-active' : '' ?> <?= $d === $today ? 'is-today' : '' ?>" aria-current="<?= $d === $day ? 'true' : 'false' ?>">
            <span><?= e(weekday_name($d, true)) ?></span>
            <strong><?= $d === $today ? 'Hoje' : e(mb_substr(weekday_name($d), 0, 1)) ?></strong>
            <span class="dot <?= isset($schedule[$d]) ? '' : 'rest' ?>"></span>
        </a>
    <?php endfor ?>
</nav>

<?php if ($workout === null): ?>
    <section class="card card-glow reveal" style="--i:1">
        <?= View::partial('empty', [
            'icon'    => $schedule === [] ? 'notebook-pen' : 'coffee',
            'heading' => $schedule === [] ? 'Ficha em construção' : 'Dia livre',
            'text'    => $schedule === []
                ? 'Seu personal ainda não montou seus treinos. Fica de olho: logo aparecem aqui.'
                : weekday_name($day) . ' é dia de descanso. Recuperação também é treino.',
        ]) ?>
    </section>
<?php else: ?>
    <section class="hero-card reveal" style="--i:1">
        <div class="eyebrow"><?= e(weekday_name($day)) ?><?= $day === $today ? ' · hoje' : '' ?></div>
        <h2><?= e($workout['name']) ?></h2>
        <div class="metric-row mt-2">
            <span><?= icon('list-checks', '', 16) ?><?= count($exercises) ?> exercícios</span>
            <span><?= icon('repeat', '', 16) ?><?= array_sum(array_map(static fn ($x) => (int) $x['sets'], $exercises)) ?> séries</span>
        </div>
        <?php if ($workout['notes']): ?><p class="small soft mt-3"><?= e($workout['notes']) ?></p><?php endif ?>
        <?php if ($day === $today): ?>
            <button type="button" class="btn btn-primary btn-lg btn-block mt-3" aria-disabled="true" title="Disponível em breve">
                <?= icon('play', '', 18) ?><span>Iniciar treino</span>
            </button>
            <p class="xsmall muted center mt-2">O registro de treino chega na próxima atualização.</p>
        <?php endif ?>
    </section>

    <section class="stack" style="--gap:10px">
        <?php foreach ($exercises as $i => $ex): ?>
            <article class="card reveal" style="--i:<?= $i + 2 ?>;padding:16px">
                <div class="row" style="--gap:14px;align-items:flex-start">
                    <span class="avatar" style="border-radius:12px;color:var(--text-soft)"><span class="mono small"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span></span>
                    <div class="grow stack" style="--gap:6px">
                        <div class="row-between" style="align-items:flex-start">
                            <div>
                                <div class="bold"><?= e($ex['name']) ?></div>
                                <div class="xsmall muted"><?= e($ex['muscle_group']) ?><?= $ex['equipment'] ? ' · ' . e($ex['equipment']) : '' ?></div>
                            </div>
                            <?php if ($ex['video_url']): ?>
                                <a href="<?= e($ex['video_url']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-icon btn-sm" aria-label="Ver vídeo"><?= icon('play', '', 14) ?></a>
                            <?php endif ?>
                        </div>
                        <div class="row wrap" style="--gap:6px">
                            <span class="badge badge-plain"><?= (int) $ex['sets'] ?> × <?= e($ex['reps']) ?></span>
                            <?php if ($ex['load_kg'] !== null): ?><span class="badge badge-plain"><?= icon('weight', '', 12) ?><?= e(rtrim(rtrim(number_format((float) $ex['load_kg'], 2, ',', ''), '0'), ',')) ?> kg</span><?php endif ?>
                            <?php if ($ex['rest_seconds']): ?><span class="badge badge-plain"><?= icon('timer', '', 12) ?><?= (int) $ex['rest_seconds'] ?>s</span><?php endif ?>
                            <?php if ($ex['technique']): ?><span class="badge badge-success badge-plain"><?= e($ex['technique']) ?></span><?php endif ?>
                        </div>
                        <?php if ($ex['notes']): ?><p class="xsmall soft"><?= e($ex['notes']) ?></p><?php endif ?>
                    </div>
                </div>
            </article>
        <?php endforeach ?>
    </section>
<?php endif ?>
