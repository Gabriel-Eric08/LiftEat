<?php
use App\Core\View;

$statusLabels = ['paid' => ['Pago', 'success'], 'pending' => ['Pendente', 'warning'], 'cancelled' => ['Cancelado', 'neutral']];
$monthLabel = ucfirst(month_name((int) $month->format('n'))) . ' de ' . $month->format('Y');
$qs = static fn (array $extra) => '?' . http_build_query([...['mes' => $month->format('Y-m'), 'status' => $status], ...$extra]);
$methodTotal = array_sum(array_map(static fn ($m) => (float) $m['total'], $byMethod)) ?: 1;
?>
<div class="page-header reveal">
    <div>
        <h1>Financeiro</h1>
        <p>Recebimentos, pendências e faturamento por mês.</p>
    </div>
    <div class="segmented" aria-label="Mês">
        <a href="<?= e($qs(['mes' => $prev])) ?>" aria-label="Mês anterior"><?= icon('chevron-left', '', 16) ?></a>
        <a class="is-active" href="<?= e($qs(['mes' => date('Y-m')])) ?>" title="Voltar para o mês atual"><?= icon('calendar', '', 16) ?><?= e($monthLabel) ?></a>
        <a href="<?= e($qs(['mes' => $next])) ?>" aria-label="Próximo mês"><?= icon('chevron-right', '', 16) ?></a>
    </div>
</div>

<section class="grid grid-4">
    <div class="card card-glow stat reveal" style="--i:1">
        <div class="stat-label"><span class="stat-icon"><?= icon('circle-dollar-sign', '', 18) ?></span>Recebido no mês</div>
        <div class="stat-value"><?= e(money($summary['received'])) ?></div>
        <div class="stat-foot"><?= (int) $summary['received_qty'] ?> pagamento<?= (int) $summary['received_qty'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="card stat reveal" style="--i:2">
        <div class="stat-label"><span class="stat-icon" style="background:var(--warning-soft);color:var(--warning)"><?= icon('hourglass', '', 18) ?></span>Pendente no mês</div>
        <div class="stat-value"><?= e(money($summary['pending'])) ?></div>
        <div class="stat-foot"><?= (int) $summary['pending_qty'] ?> mensalidade<?= (int) $summary['pending_qty'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="card stat reveal" style="--i:3">
        <div class="stat-label"><span class="stat-icon" style="background:var(--danger-soft);color:var(--danger)"><?= icon('triangle-alert', '', 18) ?></span>Em atraso (geral)</div>
        <div class="stat-value"><?= e(money($overdue['total'])) ?></div>
        <div class="stat-foot"><?= (int) $overdue['qty'] ?> vencida<?= (int) $overdue['qty'] === 1 ? '' : 's' ?> e não paga<?= (int) $overdue['qty'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="card stat reveal" style="--i:4">
        <div class="stat-label"><span class="stat-icon" style="background:var(--info-soft);color:var(--info)"><?= icon('trending-up', '', 18) ?></span>Previsto por mês</div>
        <div class="stat-value"><?= e(money($forecast)) ?></div>
        <div class="stat-foot">mensalidades de quem está ativo</div>
    </div>
</section>

<section class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start" data-fin-split>
    <div class="card card-flush reveal" style="--i:5">
        <div style="padding:16px 16px 0">
            <nav class="chips" aria-label="Situação">
                <?php foreach ($statuses as $key => $label): ?>
                    <a href="<?= e($qs(['status' => $key])) ?>" class="chip <?= $key === $status ? 'is-active' : '' ?>"><?= e($label) ?></a>
                <?php endforeach ?>
            </nav>
        </div>

        <?php if ($payments === []): ?>
            <?= View::partial('empty', [
                'icon'    => 'wallet',
                'heading' => $status === 'todos' ? 'Mês tranquilo' : 'Nada nesse filtro',
                'text'    => $status === 'todos'
                    ? 'Nenhuma movimentação em ' . mb_strtolower($monthLabel) . '. Pagamentos registrados na ficha do aluno aparecem aqui.'
                    : 'Não há mensalidades com essa situação neste mês.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead><tr><th>Aluno</th><th class="hide-sm">Período</th><th>Valor</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $i => $p): [$label, $tone] = $statusLabels[$p['status']]; ?>
                        <tr class="reveal" style="--i:<?= min($i, 12) ?>">
                            <td>
                                <a href="<?= $p['student_deleted'] ? '#' : '/admin/alunos/' . (int) $p['student_id'] . '?aba=financeiro' ?>" class="row" style="--gap:10px">
                                    <span class="avatar avatar-sm"><?= e(initials($p['student_name'])) ?></span>
                                    <span class="grow">
                                        <span class="small bold truncate" style="display:block"><?= e($p['student_deleted'] ? 'Aluno removido' : $p['student_name']) ?></span>
                                        <span class="xsmall muted">
                                            <?= $p['status'] === 'paid'
                                                ? 'pago em ' . e(date_br($p['paid_at'])) . ($p['method'] ? ' · ' . e($methods[$p['method']] ?? $p['method']) : '')
                                                : 'vence ' . e(date_br($p['due_date'])) ?>
                                            <?= $p['origin'] === 'auto_renewal' ? ' · renovação' : '' ?>
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td class="hide-sm small num muted"><?= e(date_br($p['period_start'], 'd/m')) ?> – <?= e(date_br($p['period_end'], 'd/m/y')) ?></td>
                            <td class="num bold"><?= e(money($p['amount'])) ?></td>
                            <td><span class="badge badge-<?= $tone ?>"><?= $label ?></span></td>
                            <td class="actions">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <form method="post" action="/admin/pagamentos/<?= (int) $p['id'] ?>/pagar" style="display:inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-soft btn-sm" title="Marcar como pago"><?= icon('check', '', 14) ?><span class="hide-sm">Pago</span></button>
                                    </form>
                                <?php endif ?>
                                <?php if ($p['status'] !== 'cancelled'): ?>
                                    <form method="post" action="/admin/pagamentos/<?= (int) $p['id'] ?>/cancelar" style="display:inline"
                                          data-confirm="Cancelar a mensalidade de <?= e(first_name($p['student_name'])) ?> (<?= e(money($p['amount'])) ?>)?" data-confirm-label="Cancelar mensalidade">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-icon btn-sm" title="Cancelar" aria-label="Cancelar"><?= icon('x', '', 16) ?></button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>

    <div class="card reveal" style="--i:6">
        <div class="card-header"><h2>Por forma de pagamento</h2><span class="muted"><?= icon('credit-card', '', 18) ?></span></div>
        <?php if ($byMethod === []): ?>
            <p class="small muted">Sem recebimentos neste mês.</p>
        <?php else: ?>
            <div class="stack" style="--gap:14px">
                <?php foreach ($byMethod as $m): $pct = round((float) $m['total'] / $methodTotal * 100); ?>
                    <div class="stack" style="--gap:6px">
                        <div class="row-between small">
                            <span class="bold"><?= e($methods[$m['method']] ?? ucfirst($m['method'])) ?> <span class="muted">· <?= (int) $m['qty'] ?></span></span>
                            <span class="num"><?= e(money($m['total'])) ?></span>
                        </div>
                        <div class="meter"><span style="width:<?= $pct ?>%"></span></div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>

<style>
    @media (max-width: 1024px) { [data-fin-split] { grid-template-columns: minmax(0, 1fr) !important; } }
</style>
