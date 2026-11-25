<?php
use App\Core\View;
use App\Services\BodyMeasurements as M;

/** @var array $student @var list<array>|null $measurements null = migration ainda não aplicada */
$measurements ??= null;
$variation = M::variation($measurements ?? []);
// Colunas da tabela: só as medidas que o aluno já registrou alguma vez.
$used = array_values(array_filter(array_keys(M::FIELDS), static fn ($f) => isset($variation[$f])));
?>
<div class="stack" style="--gap:16px;padding-top:20px">
    <?php if ($measurements === null): ?>
        <p class="small muted">O registro de peso e medidas fica disponível depois que a migration 003 for aplicada na página de instalação.</p>
    <?php elseif ($measurements === []): ?>
        <div class="card">
            <?= View::partial('empty', [
                'icon'    => 'activity',
                'heading' => 'Nenhum registro ainda',
                'text'    => first_name($student['name']) . ' registra peso e medidas no app, em Perfil › Evolução. Os registros aparecem aqui.',
            ]) ?>
        </div>
    <?php else: ?>
        <section class="grid grid-4" style="--gap:10px">
            <?php foreach ($variation as $field => $v): ?>
                <div class="card stat" style="padding:14px">
                    <div class="stat-label"><?= e(M::FIELDS[$field][0]) ?></div>
                    <div class="stat-value" style="font-size:1.3rem"><?= e(M::format($field, $v['last'])) ?></div>
                    <div class="stat-foot num"><?= $v['count'] > 1 ? e(M::format($field, $v['delta'], true)) . ' desde ' . e(date_br($v['firstDate'], 'd/m/y')) : 'um registro' ?></div>
                </div>
            <?php endforeach ?>
        </section>

        <div class="card card-flush">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Data</th><?php foreach ($used as $f): ?><th class="num"><?= e(M::FIELDS[$f][0]) ?></th><?php endforeach ?><th class="hide-sm">Observação</th></tr></thead>
                    <tbody>
                    <?php foreach ($measurements as $r): ?>
                        <tr>
                            <td class="small num"><?= e(date_br($r['measured_on'])) ?></td>
                            <?php foreach ($used as $f): ?><td class="small num"><?= e(M::format($f, $r[$f])) ?></td><?php endforeach ?>
                            <td class="hide-sm xsmall muted"><?= $r['notes'] ? e($r['notes']) : '' ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif ?>
</div>
