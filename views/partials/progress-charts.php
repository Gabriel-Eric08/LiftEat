<?php
use App\Core\View;

/**
 * Seção de gráficos de evolução (app do aluno e ficha no painel).
 *
 * @var array  $charts saída de ProgressCharts::forStudent()
 * @var string $action URL do formulário de troca de exercício
 * @var array<string, string> $hidden campos extras do formulário (ex.: aba)
 * @var bool   $showWeight mostrar o gráfico de peso
 */
$hidden ??= [];
$showWeight ??= true;
?>
<?php if ($showWeight): ?>
    <section class="card stack" style="--gap:10px;padding:16px">
        <div class="row-between"><span class="label">Peso</span><?= icon('activity', 'muted', 16) ?></div>
        <?= View::partial('line-chart', [
            'points' => $charts['weight'],
            'unit'   => 'kg',
            'label'  => 'Peso',
            'empty'  => 'Registre o peso em pelo menos 2 datas para ver o gráfico.',
        ]) ?>
    </section>
<?php endif ?>

<section class="card stack" style="--gap:10px;padding:16px">
    <div class="row-between wrap" style="--gap:8px">
        <span class="label">Carga máxima por treino</span>
        <?php if ($charts['exercises'] !== []): ?>
            <form method="get" action="<?= e($action) ?>" data-no-loading>
                <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach ?>
                <label class="sr-only" for="exercicio">Exercício</label>
                <select class="select" id="exercicio" name="exercicio" data-autosubmit style="height:36px;font-size:.88rem">
                    <?php foreach ($charts['exercises'] as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $charts['exercise'] && $charts['exercise']['id'] === $ex['id'] ? 'selected' : '' ?>><?= e($ex['name']) ?> (<?= $ex['sessions'] ?>)</option>
                    <?php endforeach ?>
                </select>
                <noscript><button class="btn btn-outline btn-sm">Ver</button></noscript>
            </form>
        <?php endif ?>
    </div>
    <?php if ($charts['exercise'] === null): ?>
        <div class="center" style="padding:28px 12px;border:1px dashed var(--border-strong);border-radius:12px">
            <div class="small muted">Quando houver treinos concluídos com carga registrada, a evolução de cada exercício aparece aqui.</div>
        </div>
    <?php else: ?>
        <?= View::partial('line-chart', [
            'points' => $charts['load'],
            'unit'   => 'kg',
            'label'  => 'Carga máxima em ' . $charts['exercise']['name'],
            'empty'  => 'Faça ' . $charts['exercise']['name'] . ' com carga em pelo menos 2 treinos para ver o gráfico.',
        ]) ?>
    <?php endif ?>
</section>
