<?php
use App\Core\View;

$sit = student_situation($student);
$age = $student['birth_date'] ? (new DateTimeImmutable($student['birth_date']))->diff(new DateTimeImmutable('today'))->y : null;
[$nextFrom, $nextTo] = $nextPeriod;
$statusLabels = ['paid' => ['Pago', 'success'], 'pending' => ['Pendente', 'warning'], 'cancelled' => ['Cancelado', 'neutral']];
?>
<section class="card card-glow reveal">
    <div class="row wrap" style="--gap:16px;align-items:center">
        <span class="avatar avatar-xl"><?= e(initials($student['name'])) ?></span>
        <div class="grow stack" style="--gap:6px;min-width:220px">
            <div class="row wrap" style="--gap:8px">
                <h1 style="font-size:1.5rem"><?= e($student['name']) ?></h1>
                <span class="badge badge-<?= $sit['tone'] ?>"><?= e($sit['label']) ?></span>
                <?php if ($student['auto_renew']): ?><span class="badge badge-info badge-plain"><?= icon('repeat', '', 12) ?>Renovação automática</span><?php endif ?>
            </div>
            <div class="metric-row">
                <span><?= icon('mail', '', 14) ?><?= e($student['email']) ?></span>
                <?php if ($student['phone']): ?><span><?= icon('phone', '', 14) ?><?= e($student['phone']) ?></span><?php endif ?>
                <?php if ($student['goal']): ?><span><?= icon('target', '', 14) ?><?= e($student['goal']) ?></span><?php endif ?>
            </div>
        </div>
        <div class="row wrap" style="--gap:8px">
            <button type="button" class="btn btn-primary" data-open="payment-modal"><?= icon('banknote', '', 18) ?><span>Registrar pagamento</span></button>
            <a href="/admin/alunos/<?= (int) $student['id'] ?>/editar" class="btn btn-outline"><?= icon('pencil', '', 16) ?><span>Editar</span></a>
            <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/status"
                  data-confirm="<?= $student['status'] === 'active' ? 'O aluno perde o acesso ao app até ser reativado.' : 'O aluno volta a ter acesso (se a mensalidade estiver em dia).' ?>"
                  data-confirm-label="<?= $student['status'] === 'active' ? 'Desativar' : 'Reativar' ?>"
                  data-confirm-tone="<?= $student['status'] === 'active' ? 'danger' : 'primary' ?>">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-icon" title="<?= $student['status'] === 'active' ? 'Desativar' : 'Reativar' ?>" aria-label="<?= $student['status'] === 'active' ? 'Desativar' : 'Reativar' ?>">
                    <?= icon($student['status'] === 'active' ? 'user-x' : 'user-check', '', 18) ?>
                </button>
            </form>
        </div>
    </div>
</section>

<section class="grid grid-4">
    <div class="card stat reveal" style="--i:1">
        <div class="stat-label"><?= icon('calendar-check', '', 16) ?>Acesso até</div>
        <div class="stat-value" style="font-size:1.4rem"><?= e(date_br($student['access_until'])) ?></div>
        <div class="stat-foot"><?= $student['access_until'] ? e(relative_days($student['access_until'])) : 'nenhum pagamento ainda' ?></div>
    </div>
    <div class="card stat reveal" style="--i:2">
        <div class="stat-label"><?= icon('wallet', '', 16) ?>Mensalidade</div>
        <div class="stat-value" style="font-size:1.4rem"><?= e(money($student['monthly_fee'])) ?></div>
        <div class="stat-foot"><?= e(money($paidTotal)) ?> pagos no total</div>
    </div>
    <div class="card stat reveal" style="--i:3">
        <div class="stat-label"><?= icon('flame', '', 16) ?>Treinos (30 dias)</div>
        <div class="stat-value" style="font-size:1.4rem"><?= (int) $stats['last30'] ?></div>
        <div class="stat-foot"><?= (int) $stats['completed'] ?> concluídos no total</div>
    </div>
    <div class="card stat reveal" style="--i:4">
        <div class="stat-label"><?= icon('activity', '', 16) ?>Último treino</div>
        <div class="stat-value" style="font-size:1.4rem"><?= $stats['last_at'] ? e(date_br($stats['last_at'], 'd/m')) : '—' ?></div>
        <div class="stat-foot"><?= $stats['last_at'] ? e(relative_days($stats['last_at'])) : 'ainda não treinou' ?></div>
    </div>
</section>

<section class="card reveal" style="--i:5;padding-top:4px" data-tabs="aba">
    <nav class="tabs" role="tablist">
        <button class="tab" data-tab="geral"><?= icon('user', '', 16) ?>Visão geral</button>
        <button class="tab" data-tab="financeiro"><?= icon('wallet', '', 16) ?>Financeiro</button>
        <button class="tab" data-tab="treinos"><?= icon('dumbbell', '', 16) ?>Treinos</button>
        <button class="tab" data-tab="dieta"><?= icon('salad', '', 16) ?>Dieta</button>
        <button class="tab" data-tab="evolucao"><?= icon('activity', '', 16) ?>Evolução</button>
    </nav>

    <div class="tab-panel" data-panel="geral" style="padding-top:20px">
        <div class="grid grid-2" style="--gap:24px">
            <dl class="kv">
                <dt>Idade</dt><dd><?= $age !== null ? $age . ' anos' : '—' ?></dd>
                <dt>Nascimento</dt><dd><?= e(date_br($student['birth_date'])) ?></dd>
                <dt>Altura</dt><dd><?= $student['height_cm'] ? (int) $student['height_cm'] . ' cm' : '—' ?></dd>
                <dt>Peso</dt><dd><?= $student['weight_kg'] ? e(number_format((float) $student['weight_kg'], 1, ',', '')) . ' kg' : '—' ?></dd>
                <dt>Cadastrado em</dt><dd><?= e(date_br($student['created_at'])) ?></dd>
                <dt>Último login</dt><dd><?= $student['last_login_at'] ? e(date_br($student['last_login_at'], 'd/m/Y H:i')) : 'nunca entrou' ?></dd>
            </dl>
            <div class="stack" style="--gap:8px">
                <span class="label">Observações</span>
                <p class="soft small" style="white-space:pre-line"><?= $student['notes'] ? e($student['notes']) : '<span class="muted">Nenhuma observação.</span>' ?></p>
            </div>
        </div>
        <hr class="divider mt-4">
        <div class="row-between mt-3 wrap">
            <span class="small muted">Remover o aluno apaga o acesso, mas mantém o histórico financeiro.</span>
            <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/excluir"
                  data-confirm="<?= e($student['name']) ?> será removido e perderá o acesso. O histórico de pagamentos continua no financeiro." data-confirm-label="Remover aluno">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm"><?= icon('trash-2', '', 16) ?><span>Remover aluno</span></button>
            </form>
        </div>
    </div>

    <div class="tab-panel" data-panel="financeiro" style="padding-top:12px">
        <?php if ($payments === []): ?>
            <?= View::partial('empty', [
                'icon'    => 'banknote',
                'heading' => 'Nenhum pagamento ainda',
                'text'    => 'Registre o primeiro pagamento para liberar o acesso de ' . first_name($student['name']) . ' ao app.',
                'action'  => '<button type="button" class="btn btn-primary" data-open="payment-modal">' . icon('banknote', '', 16) . '<span>Registrar pagamento</span></button>',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Período</th><th>Valor</th><th>Situação</th><th class="hide-sm">Pago em</th><th class="hide-sm">Origem</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): [$label, $tone] = $statusLabels[$p['status']]; ?>
                        <tr>
                            <td class="num small"><?= e(date_br($p['period_start'], 'd/m/y')) ?> – <?= e(date_br($p['period_end'], 'd/m/y')) ?></td>
                            <td class="num bold"><?= e(money($p['amount'])) ?></td>
                            <td><span class="badge badge-<?= $tone ?>"><?= $label ?></span></td>
                            <td class="hide-sm small muted"><?= $p['paid_at'] ? e(date_br($p['paid_at'])) . ($p['method'] ? ' · ' . e($methods[$p['method']] ?? $p['method']) : '') : '—' ?></td>
                            <td class="hide-sm small muted"><?= $p['origin'] === 'auto_renewal' ? 'Renovação automática' : 'Manual' ?></td>
                            <td class="actions">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <form method="post" action="/admin/pagamentos/<?= (int) $p['id'] ?>/pagar" style="display:inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-soft btn-sm"><?= icon('check', '', 14) ?><span class="hide-sm">Marcar pago</span></button>
                                    </form>
                                <?php endif ?>
                                <?php if ($p['status'] !== 'cancelled'): ?>
                                    <form method="post" action="/admin/pagamentos/<?= (int) $p['id'] ?>/cancelar" style="display:inline"
                                          data-confirm="A mensalidade será cancelada. Se for o período mais recente, o acesso volta para <?= e(date_br((new DateTimeImmutable($p['period_start']))->modify('-1 day')->format('Y-m-d'))) ?>." data-confirm-label="Cancelar mensalidade">
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

    <div class="tab-panel" data-panel="treinos">
        <?= View::render('admin/students/_workouts', compact('student', 'workouts', 'schedule', 'sessions', 'templates')) ?>
    </div>

    <div class="tab-panel" data-panel="dieta">
        <?= View::render('admin/students/_diets', compact('student', 'diets', 'adherence')) ?>
    </div>

    <div class="tab-panel" data-panel="evolucao">
        <?= View::render('admin/students/_evolution', compact('student', 'measurements', 'charts')) ?>
    </div>
</section>

<dialog class="modal" id="payment-modal">
    <form method="post" action="/admin/alunos/<?= (int) $student['id'] ?>/pagamentos">
        <?= csrf_field() ?>
        <div class="modal-header">
            <div>
                <h2>Registrar pagamento</h2>
                <p class="small muted mt-1">Libera o acesso de <?= e(first_name($student['name'])) ?> a partir de <?= e(date_br($nextFrom->format('Y-m-d'))) ?>.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <div class="grid grid-2">
                <div class="field">
                    <label class="label" for="amount">Valor por mês</label>
                    <input class="input" id="amount" name="amount" inputmode="decimal" value="<?= e(number_format((float) $student['monthly_fee'], 2, ',', '')) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="months">Meses</label>
                    <select class="select" id="months" name="months" data-months>
                        <?php foreach ([1, 2, 3, 6, 12] as $m): ?>
                            <option value="<?= $m ?>"><?= $m ?> <?= $m === 1 ? 'mês' : 'meses' ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="field">
                    <label class="label" for="paid_at">Data do pagamento</label>
                    <input class="input" id="paid_at" name="paid_at" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="method">Forma</label>
                    <select class="select" id="method" name="method">
                        <?php foreach ($methods as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="label" for="notes">Observação <span class="muted">(opcional)</span></label>
                <input class="input" id="notes" name="notes" maxlength="255" placeholder="Ex.: pagou adiantado">
            </div>
            <div class="alert alert-success">
                <?= icon('calendar-check', '', 18) ?>
                <span>Acesso liberado até <strong data-until><?= e(date_br($nextTo->format('Y-m-d'))) ?></strong></span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><?= icon('check', '', 18) ?><span>Confirmar</span></button>
        </div>
    </form>
</dialog>

<script type="application/json" id="period-ends"><?php
    // Fim do acesso para 1..12 meses, calculado no servidor com a mesma regra do Billing.
    $ends = [];
    $start = $nextFrom;
    for ($i = 1; $i <= 12; $i++) {
        [, $end] = \App\Services\Billing::period($start);
        $ends[$i] = date_br($end->format('Y-m-d'));
        $start = $end->modify('+1 day');
    }
    echo json_encode($ends);
?></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ends = JSON.parse(document.getElementById('period-ends').textContent);
    const sel = document.querySelector('[data-months]');
    const out = document.querySelector('[data-until]');
    sel?.addEventListener('change', () => { out.textContent = ends[sel.value]; });
});
</script>
