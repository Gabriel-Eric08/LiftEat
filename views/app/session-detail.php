<?php
$fmt = static fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
?>
<?php if ($celebrate): ?>
    <section class="celebrate reveal">
        <div class="celebrate-badge"><?= icon('check', '', 40) ?></div>
        <h2>Treino concluído!</h2>
        <p class="muted small">Mais um pra conta. Seu personal já consegue ver tudo.</p>
        <div class="confetti" aria-hidden="true"><?php for ($i = 0; $i < 18; $i++): ?><i style="--d:<?= $i ?>"></i><?php endfor ?></div>
    </section>
<?php elseif ($session['status'] === 'abandoned'): ?>
    <div class="alert alert-warning reveal"><?= icon('info', '', 18) ?><span>Esse treino foi encerrado sem concluir.</span></div>
<?php endif ?>

<section class="grid grid-2 reveal" style="--i:1;--gap:10px">
    <div class="quick"><span class="xsmall muted">Duração</span><span class="stat-value" style="font-size:1.4rem"><?= $session['minutes'] !== null ? $session['minutes'] . ' min' : '—' ?></span></div>
    <div class="quick"><span class="xsmall muted">Séries</span><span class="stat-value" style="font-size:1.4rem"><?= (int) $session['sets_done'] ?></span></div>
    <div class="quick"><span class="xsmall muted">Volume total</span><span class="stat-value" style="font-size:1.4rem"><?= e(number_format($session['volume'], 0, ',', '.')) ?> kg</span></div>
    <div class="quick"><span class="xsmall muted">Horário</span><span class="stat-value" style="font-size:1.4rem"><?= e(date_br($session['started_at'], 'H:i')) ?></span></div>
</section>

<?php if ($session['notes']): ?>
    <section class="card reveal" style="--i:2">
        <div class="xsmall muted">Seu comentário</div>
        <p class="small mt-1" style="white-space:pre-line"><?= e($session['notes']) ?></p>
    </section>
<?php endif ?>

<?php if ($session['photos']): ?>
    <section class="stack reveal" style="--i:3;--gap:10px">
        <h2>Fotos</h2>
        <div class="h-scroll">
            <?php foreach ($session['photos'] as $p): ?>
                <a href="/media/fotos/<?= (int) $p['id'] ?>" target="_blank" class="photo photo-lg"><img src="/media/fotos/<?= (int) $p['id'] ?>" alt="Foto do treino" loading="lazy"></a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<section class="stack reveal" style="--i:4;--gap:10px">
    <h2>Exercícios</h2>
    <?php if ($session['exercises'] === []): ?>
        <div class="card"><p class="small muted">Nenhuma série registrada.</p></div>
    <?php endif ?>
    <?php foreach ($session['exercises'] as $ex): ?>
        <article class="card" style="padding:14px 16px">
            <div class="row-between">
                <div class="bold"><?= e($ex['name']) ?></div>
                <span class="xsmall muted"><?= e($ex['muscle_group']) ?></span>
            </div>
            <div class="row wrap mt-2" style="--gap:6px">
                <?php foreach ($ex['sets'] as $set): ?>
                    <span class="badge <?= $set['completed'] ? 'badge-success' : 'badge-neutral' ?> badge-plain num">
                        <?= $fmt($set['load_kg']) ?>kg × <?= $set['reps'] ?? '—' ?>
                    </span>
                <?php endforeach ?>
            </div>
        </article>
    <?php endforeach ?>
</section>

<a href="/app" class="btn btn-primary btn-lg btn-block reveal" style="--i:5"><?= icon('house', '', 18) ?><span>Voltar ao início</span></a>
