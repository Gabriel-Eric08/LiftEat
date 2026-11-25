<?php
use App\Core\View;

$daysLeft = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($student['access_until']))->format('%r%a');
?>
<?php if ($daysLeft <= 5): ?>
    <div class="alert alert-warning reveal">
        <?= icon('calendar-days', '', 18) ?>
        <span>Seu acesso vai até <strong><?= e(date_br($student['access_until'])) ?></strong> (<?= e(relative_days($student['access_until'])) ?>). Combine a renovação com seu personal.</span>
    </div>
<?php endif ?>

<!-- Semana: bolinhas de progresso -->
<section class="card reveal" style="--i:1;padding:16px">
    <div class="row-between">
        <span class="small bold">Sua semana</span>
        <span class="xsmall muted"><?= count($doneThisWeek) ?>/<?= count($schedule) ?: '—' ?> treinos</span>
    </div>
    <div class="row mt-3" style="justify-content:space-between">
        <?php for ($d = 1; $d <= 7; $d++):
            $planned = isset($schedule[$d]);
            $done = isset($doneThisWeek[$d]);
            $isToday = $d === $today;
            ?>
            <a href="/app/treinos?dia=<?= $d ?>" class="stack center" style="--gap:6px;align-items:center">
                <span class="xsmall <?= $isToday ? 'text-primary bold' : 'muted' ?>"><?= e(mb_substr(weekday_name($d), 0, 1)) ?></span>
                <span style="display:grid;place-items:center;width:34px;height:34px;border-radius:50%;
                    <?php if ($done): ?>background:var(--primary);color:var(--on-primary);
                    <?php elseif ($isToday): ?>border:2px solid var(--primary);color:var(--primary);
                    <?php elseif ($planned): ?>border:1px solid var(--border-strong);color:var(--text-soft);
                    <?php else: ?>background:var(--surface-2);color:var(--muted);<?php endif ?>">
                    <?php if ($done): ?><?= icon('check', '', 16) ?>
                    <?php elseif ($planned): ?><?= icon('dumbbell', '', 14) ?>
                    <?php else: ?><span class="xsmall">·</span><?php endif ?>
                </span>
            </a>
        <?php endfor ?>
    </div>
</section>

<!-- Treino de hoje -->
<?php if ($active !== null): ?>
    <a href="/app/sessao/<?= (int) $active['id'] ?>" class="hero-card reveal" style="--i:2;display:block">
        <div class="eyebrow"><span class="live-dot"></span> Treino em andamento</div>
        <h2><?= e($active['workout_name']) ?></h2>
        <p class="small soft mt-1">Começou às <?= e(date_br($active['started_at'], 'H:i')) ?>. Bora terminar?</p>
        <span class="btn btn-primary btn-lg btn-block mt-3"><?= icon('play', '', 18) ?><span>Continuar treino</span></span>
    </a>
<?php elseif ($todayWorkout !== null): ?>
    <section class="hero-card reveal" style="--i:2">
        <div class="eyebrow"><?= icon('flame', '', 14) ?> Treino de hoje · <?= e(weekday_name($today)) ?></div>
        <h2><?= e($todayWorkout['name']) ?></h2>
        <div class="metric-row mt-2">
            <span><?= icon('list-checks', '', 16) ?><?= (int) $todayWorkout['exercise_count'] ?> exercícios</span>
            <span><?= icon('timer', '', 16) ?>~<?= max(20, (int) $todayWorkout['exercise_count'] * 7) ?> min</span>
        </div>
        <?php if (isset($doneThisWeek[$today])): ?>
            <div class="alert alert-success mt-3"><?= icon('circle-check', '', 18) ?><span>Treino de hoje concluído. Mandou bem!</span></div>
        <?php endif ?>
        <a href="/app/treinos?dia=<?= $today ?>" class="btn btn-primary btn-lg btn-block mt-3">
            <?= icon('play', '', 18) ?><span><?= isset($doneThisWeek[$today]) ? 'Ver treino' : 'Bora treinar' ?></span>
        </a>
    </section>
<?php else: ?>
    <section class="card card-glow reveal" style="--i:2">
        <?= View::partial('empty', [
            'icon'    => $schedule === [] ? 'notebook-pen' : 'moon-star',
            'heading' => $schedule === [] ? 'Sua ficha está sendo montada' : 'Hoje é dia de descanso',
            'text'    => $schedule === []
                ? 'Seu personal ainda não cadastrou seus treinos. Assim que cadastrar, eles aparecem aqui.'
                : 'Músculo cresce no descanso. Hidrate-se, durma bem e volte com tudo amanhã.',
        ]) ?>
    </section>
<?php endif ?>

<!-- Atalhos -->
<section class="grid grid-2 reveal" style="--i:3;--gap:12px">
    <a href="/app/historico" class="quick">
        <span class="stat-icon"><?= icon('flame', '', 18) ?></span>
        <div>
            <div class="stat-value" style="font-size:1.5rem"><?= $monthCount ?></div>
            <div class="xsmall muted">treinos em <?= e(month_name((int) date('n'))) ?></div>
        </div>
    </a>
    <a href="/app/perfil" class="quick">
        <span class="stat-icon" style="background:var(--info-soft);color:var(--info)"><?= icon('calendar-check', '', 18) ?></span>
        <div>
            <div class="stat-value" style="font-size:1.5rem"><?= max(0, $daysLeft) ?></div>
            <div class="xsmall muted">dia<?= $daysLeft === 1 ? '' : 's' ?> de acesso</div>
        </div>
    </a>
</section>

<!-- Próxima refeição -->
<section class="stack reveal" style="--i:4;--gap:12px">
    <div class="section-title">
        <h2>Próxima refeição</h2>
        <a href="/app/dieta"><?= $dietProgress && $dietProgress['total'] > 0 ? $dietProgress['done'] . ' de ' . $dietProgress['total'] . ' hoje' : 'Ver dieta' ?></a>
    </div>
    <?php if ($nextMeal === null && $hasDiet): ?>
        <div class="card">
            <?= View::partial('empty', ['icon' => 'check', 'heading' => 'Dieta de hoje completa', 'text' => 'Você marcou todas as refeições do plano. Mandou bem!']) ?>
        </div>
    <?php elseif ($nextMeal === null): ?>
        <div class="card">
            <?= View::partial('empty', ['icon' => 'utensils', 'heading' => 'Sem plano alimentar ainda', 'text' => 'Quando seu personal montar sua dieta, a próxima refeição aparece aqui.']) ?>
        </div>
    <?php else: $opt = $nextMeal['options'][0] ?? null; ?>
        <a href="/app/dieta" class="card card-link stack" style="--gap:10px">
            <div class="row-between">
                <div class="row" style="--gap:10px">
                    <span class="stat-icon"><?= icon('utensils', '', 18) ?></span>
                    <div>
                        <div class="bold"><?= e($nextMeal['name']) ?></div>
                        <?php if ($nextMeal['meal_time']): ?><div class="meal-time"><?= e(substr($nextMeal['meal_time'], 0, 5)) ?></div><?php endif ?>
                    </div>
                </div>
                <?php if (count($nextMeal['options']) > 1): ?><span class="badge badge-plain"><?= count($nextMeal['options']) ?> opções</span><?php endif ?>
            </div>
            <?php if ($opt && $opt['items']): ?>
                <div>
                    <?php foreach (array_slice($opt['items'], 0, 3) as $item): ?>
                        <div class="food-item"><span><?= e($item['food']) ?></span><span class="qty"><?= e($item['quantity']) ?></span></div>
                    <?php endforeach ?>
                    <?php if (count($opt['items']) > 3): ?><div class="xsmall muted mt-1">+<?= count($opt['items']) - 3 ?> itens</div><?php endif ?>
                </div>
            <?php endif ?>
        </a>
        <?php if ($dietProgress !== null && count($nextMeal['options']) <= 1): ?>
            <form method="post" action="/app/dieta/feito">
                <?= csrf_field() ?>
                <input type="hidden" name="meal" value="<?= e($nextMeal['name']) ?>">
                <input type="hidden" name="from" value="home">
                <button class="btn btn-soft btn-block"><?= icon('check', '', 16) ?><span>Marcar <?= e(mb_strtolower($nextMeal['name'])) ?> como feito</span></button>
            </form>
        <?php endif ?>
    <?php endif ?>
</section>
