<?php
use App\Services\ProgressCharts;

/**
 * Gráfico de linha em SVG (gerado no servidor).
 *
 * @var list<array{date: string, value: float}> $points em ordem cronológica
 * @var string $unit  unidade exibida ("kg")
 * @var string $label descrição para leitores de tela ("Peso")
 * @var string $empty texto quando há menos de 2 pontos
 */
$g = ProgressCharts::geometry($points);
$fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
?>
<?php if ($g === null): ?>
    <div class="center" style="padding:28px 12px;border:1px dashed var(--border-strong);border-radius:12px">
        <div class="small muted"><?= e($empty) ?></div>
    </div>
<?php else:
    $first = $points[0];
    $last = $points[count($points) - 1];
    $summary = sprintf('%s: de %s %s em %s para %s %s em %s, %d registros.', $label, $fmt($first['value']), $unit, date_br($first['date']), $fmt($last['value']), $unit, date_br($last['date']), count($points));
    $axis = ProgressCharts::AXIS_LEFT;
    ?>
    <figure style="margin:0">
        <svg viewBox="0 0 <?= $g['width'] ?> <?= $g['height'] ?>" width="100%" role="img" aria-label="<?= e($summary) ?>" style="display:block;overflow:visible;font-family:inherit">
            <?php foreach ($g['ticks'] as $t): ?>
                <line x1="<?= $axis ?>" x2="<?= $g['width'] - 17 ?>" y1="<?= $t['y'] ?>" y2="<?= $t['y'] ?>" stroke="var(--border)" stroke-dasharray="4 5"/>
                <text x="<?= $axis - 8 ?>" y="<?= $t['y'] + 6 ?>" text-anchor="end" font-size="17" fill="var(--muted)"><?= e($fmt($t['value'])) ?></text>
            <?php endforeach ?>
            <path d="<?= e($g['area']) ?>" fill="var(--primary)" fill-opacity=".08"/>
            <path d="<?= e($g['path']) ?>" fill="none" stroke="var(--primary)" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
            <?php foreach ($g['dots'] as $i => $d): $isLast = $i === count($g['dots']) - 1; ?>
                <circle cx="<?= $d['x'] ?>" cy="<?= $d['y'] ?>" r="<?= $isLast ? 6 : 4.5 ?>" fill="<?= $isLast ? 'var(--primary)' : 'var(--surface)' ?>" stroke="var(--primary)" stroke-width="2.5">
                    <title><?= e(date_br($d['date']) . ': ' . $fmt($d['value']) . ' ' . $unit) ?></title>
                </circle>
            <?php endforeach ?>
            <text x="<?= $g['dots'][0]['x'] ?>" y="<?= $g['height'] - 4 ?>" font-size="16" fill="var(--muted)"><?= e(date_br($first['date'], 'd/m/y')) ?></text>
            <text x="<?= end($g['dots'])['x'] ?>" y="<?= $g['height'] - 4 ?>" font-size="16" fill="var(--muted)" text-anchor="end"><?= e(date_br($last['date'], 'd/m/y')) ?></text>
        </svg>
        <figcaption class="row-between xsmall muted mt-1">
            <span><?= count($points) ?> registros</span>
            <span>Mín. <?= e($fmt($g['min'])) ?> · Máx. <?= e($fmt($g['max'])) ?> <?= e($unit) ?></span>
        </figcaption>
    </figure>
<?php endif ?>
