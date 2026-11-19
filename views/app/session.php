<?php
/** @var array $session @var array $items @var array $saved @var array $last @var array $photos */
$fmt = static fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$totalSets = 0;
$doneSets = 0;
?>
<div class="session-top">
    <div class="row-between">
        <div class="stack" style="--gap:2px">
            <span class="xsmall muted">Tempo de treino</span>
            <span class="mono bold session-clock" data-clock data-start="<?= e(date('c', strtotime($session['started_at']))) ?>">00:00</span>
        </div>
        <div class="stack right" style="--gap:2px">
            <span class="xsmall muted">Séries</span>
            <span class="bold num"><span data-done>0</span>/<span data-total>0</span></span>
        </div>
    </div>
    <div class="meter mt-2"><span data-progress style="width:0%"></span></div>
</div>

<?php if ($items === []): ?>
    <div class="card"><?= \App\Core\View::partial('empty', ['icon' => 'triangle-alert', 'heading' => 'Treino sem exercícios', 'text' => 'Esse treino não tem exercícios cadastrados. Fale com seu personal.']) ?></div>
<?php endif ?>

<div class="stack" style="--gap:14px" data-session data-id="<?= (int) $session['id'] ?>">
    <?php foreach ($items as $i => $it):
        $exId = (int) $it['exercise_id'];
        $prevSets = $last[$exId] ?? [];
        $savedSets = $saved[$exId] ?? [];
        $setCount = max((int) $it['sets'], $savedSets ? max(array_keys($savedSets)) : 0);
        $defaultReps = preg_match('/\d+/', (string) $it['reps'], $m) ? (int) $m[0] : '';
        $lastLabel = $prevSets
            ? implode(' · ', array_map(static fn ($s) => ($s['load_kg'] !== null ? $fmt($s['load_kg']) . 'kg' : '—') . ' × ' . ($s['reps'] ?? '—'), $prevSets))
            : null;
        ?>
        <article class="card ex-card reveal" style="--i:<?= min($i, 8) ?>" data-exercise="<?= $exId ?>" data-rest="<?= (int) ($it['rest_seconds'] ?? 60) ?>">
            <header class="row" style="--gap:12px;align-items:flex-start">
                <span class="ex-index mono"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div class="grow" style="min-width:0">
                    <h3 class="truncate"><?= e($it['name']) ?></h3>
                    <div class="row wrap mt-1" style="--gap:6px">
                        <span class="badge badge-plain"><?= (int) $it['sets'] ?> × <?= e($it['reps']) ?></span>
                        <?php if ($it['rest_seconds']): ?><span class="badge badge-plain"><?= icon('timer', '', 12) ?><?= (int) $it['rest_seconds'] ?>s</span><?php endif ?>
                        <?php if ($it['technique']): ?><span class="badge badge-success badge-plain"><?= e($it['technique']) ?></span><?php endif ?>
                    </div>
                </div>
                <?php if ($it['video_url']): ?>
                    <a href="<?= e($it['video_url']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-icon btn-sm" aria-label="Ver vídeo"><?= icon('play', '', 14) ?></a>
                <?php endif ?>
            </header>

            <?php if ($it['notes'] || $it['description']): ?>
                <details class="ex-tips">
                    <summary class="xsmall"><?= icon('info', '', 14) ?> Dicas</summary>
                    <?php if ($it['notes']): ?><p class="small soft"><?= e($it['notes']) ?></p><?php endif ?>
                    <?php if ($it['description']): ?><p class="xsmall muted mt-1"><?= e($it['description']) ?></p><?php endif ?>
                </details>
            <?php endif ?>

            <?php if ($lastLabel): ?>
                <div class="xsmall muted ex-last"><?= icon('history', '', 12) ?> Última vez: <?= e($lastLabel) ?></div>
            <?php endif ?>

            <div class="set-head xsmall muted"><span>Série</span><span>Carga (kg)</span><span>Reps</span><span></span></div>
            <div class="sets" data-sets>
                <?php for ($n = 1; $n <= $setCount; $n++):
                    $s = $savedSets[$n] ?? null;
                    $done = $s !== null && (int) $s['completed'] === 1;
                    $totalSets++;
                    $doneSets += $done ? 1 : 0;
                    $load = $s['load_kg'] ?? ($prevSets[$n]['load_kg'] ?? ($prevSets ? end($prevSets)['load_kg'] : null) ?? $it['load_kg']);
                    $reps = $s['reps'] ?? ($prevSets[$n]['reps'] ?? $defaultReps);
                    ?>
                    <div class="set-row <?= $done ? 'is-done' : '' ?>" data-set="<?= $n ?>">
                        <span class="set-n mono"><?= $n ?></span>
                        <input class="input set-input" inputmode="decimal" value="<?= e($fmt($load)) ?>" placeholder="—" aria-label="Carga da série <?= $n ?>" data-load>
                        <input class="input set-input" inputmode="numeric" value="<?= e((string) $reps) ?>" placeholder="—" aria-label="Repetições da série <?= $n ?>" data-reps>
                        <button type="button" class="set-check" aria-label="Marcar série <?= $n ?> como feita" aria-pressed="<?= $done ? 'true' : 'false' ?>" data-check>
                            <?= icon('check', '', 18) ?>
                        </button>
                    </div>
                <?php endfor ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm add-set" data-add-set><?= icon('plus', '', 14) ?><span>Série extra</span></button>
        </article>
    <?php endforeach ?>

    <!-- Fotos -->
    <section class="card stack reveal" style="--gap:12px">
        <div class="row-between">
            <div>
                <h3>Fotos <span class="muted small" style="font-weight:400">(opcional)</span></h3>
                <p class="xsmall muted">Registre seu shape ou o treino. Só você e seu personal veem.</p>
            </div>
            <span class="stat-icon"><?= icon('camera', '', 18) ?></span>
        </div>
        <div class="photo-grid" data-photos>
            <?php foreach ($photos as $p): ?>
                <div class="photo" data-photo="<?= (int) $p['id'] ?>">
                    <img src="/media/fotos/<?= (int) $p['id'] ?>" alt="Foto do treino" loading="lazy">
                    <button type="button" class="photo-del" aria-label="Remover foto" data-photo-del><?= icon('x', '', 14) ?></button>
                </div>
            <?php endforeach ?>
            <label class="photo photo-add" data-photo-add>
                <input type="file" accept="image/*" hidden data-photo-input>
                <?= icon('camera', '', 22) ?>
                <span class="xsmall">Adicionar</span>
            </label>
        </div>
    </section>

    <!-- Finalizar -->
    <form method="post" action="/app/sessao/<?= (int) $session['id'] ?>/finalizar" class="card stack reveal" style="--gap:12px" id="finish" data-finish-form>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="notes">Como foi o treino? <span class="muted">(opcional)</span></label>
            <textarea class="textarea" id="notes" name="notes" maxlength="2000" placeholder="Senti o ombro, aumentei a carga no supino…" style="min-height:80px"></textarea>
        </div>
    </form>

    <form method="post" action="/app/sessao/<?= (int) $session['id'] ?>/descartar" class="center" data-confirm="Sair sem finalizar? Se nenhuma série foi marcada, o treino é descartado." data-confirm-label="Sair do treino">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm text-danger"><?= icon('log-out', '', 14) ?><span>Sair sem finalizar</span></button>
    </form>
</div>

<!-- Barra inferior: descanso + finalizar -->
<div class="session-bar">
    <div class="rest-pill" data-rest-pill hidden>
        <span class="rest-ring"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="16" pathLength="100"/><circle cx="18" cy="18" r="16" pathLength="100" data-rest-arc/></svg></span>
        <div class="grow">
            <div class="xsmall muted">Descanso</div>
            <div class="mono bold" data-rest-time>0:00</div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-rest-plus>+15s</button>
        <button type="button" class="btn btn-soft btn-sm" data-rest-skip>Pular</button>
    </div>
    <button type="submit" form="finish" class="btn btn-primary btn-lg btn-block" data-finish><?= icon('circle-check', '', 20) ?><span>Finalizar treino</span></button>
</div>

<script type="application/json" id="session-data"><?= json_encode([
    'id'        => (int) $session['id'],
    'csrf'      => csrf_token(),
    'total'     => $totalSets,
    'done'      => $doneSets,
], JSON_HEX_TAG) ?></script>
<script src="<?= asset('assets/js/session.js') ?>" defer></script>
