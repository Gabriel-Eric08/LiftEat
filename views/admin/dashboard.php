<?php
$admin = \App\Core\Auth::admin();
$trend = $prevRevenue > 0 ? (($revenue - $prevRevenue) / $prevRevenue) * 100 : null;
?>
<div class="page-header reveal">
    <div>
        <h1><?= e(greeting()) ?>, <?= e(first_name($admin['name'])) ?></h1>
        <p>Resumo de <?= e(mb_strtolower($periods[$period])) ?> · <?= e(date_br($from)) ?> a <?= e(date_br($to)) ?></p>
    </div>
    <nav class="chips" aria-label="Período">
        <?php foreach ($periods as $key => $label): ?>
            <a href="?periodo=<?= e($key) ?>" class="chip <?= $key === $period ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach ?>
    </nav>
</div>

<section class="grid grid-4">
    <a href="/admin/financeiro" class="card card-link card-glow stat reveal" style="--i:1">
        <div class="stat-label"><span class="stat-icon"><?= icon('circle-dollar-sign', '', 18) ?></span>Recebido</div>
        <div class="stat-value"><?= e(money_short($revenue)) ?></div>
        <div class="stat-foot">
            <?php if ($trend === null): ?>
                <span>sem base de comparação</span>
            <?php else: ?>
                <span class="<?= $trend >= 0 ? 'trend-up' : 'trend-down' ?> row" style="--gap:2px">
                    <?= icon($trend >= 0 ? 'arrow-up-right' : 'arrow-down-right', '', 14) ?><?= e(number_format(abs($trend), 0, ',', '.')) ?>%
                </span>
                <span>vs. período anterior</span>
            <?php endif ?>
        </div>
    </a>

    <a href="/admin/financeiro?status=pending" class="card card-link stat reveal" style="--i:2">
        <div class="stat-label"><span class="stat-icon" style="background:var(--warning-soft);color:var(--warning)"><?= icon('hourglass', '', 18) ?></span>A receber</div>
        <div class="stat-value"><?= e(money_short($pending['total'])) ?></div>
        <div class="stat-foot"><?= (int) $pending['qty'] ?> mensalidade<?= (int) $pending['qty'] === 1 ? '' : 's' ?> pendente<?= (int) $pending['qty'] === 1 ? '' : 's' ?></div>
    </a>

    <a href="/admin/alunos?situacao=ativos" class="card card-link stat reveal" style="--i:3">
        <div class="stat-label"><span class="stat-icon" style="background:var(--info-soft);color:var(--info)"><?= icon('users', '', 18) ?></span>Alunos ativos</div>
        <div class="stat-value"><?= (int) $students['with_access'] ?><span class="muted" style="font-size:1rem;font-weight:500"> / <?= (int) $students['total'] ?></span></div>
        <div class="stat-foot">+<?= (int) $students['new_in_period'] ?> novo<?= (int) $students['new_in_period'] === 1 ? '' : 's' ?> no período</div>
    </a>

    <div class="card stat reveal" style="--i:4">
        <div class="stat-label"><span class="stat-icon"><?= icon('repeat', '', 18) ?></span>Receita recorrente</div>
        <div class="stat-value"><?= e(money_short($students['mrr'])) ?></div>
        <div class="stat-foot">soma das mensalidades ativas</div>
    </div>
</section>

<section class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(0, 1fr)" data-dash-split>
    <div class="card reveal" style="--i:5" data-tabs>
        <div class="card-header">
            <div>
                <h2>Últimos 12 meses</h2>
                <p class="small muted mt-1" data-chart-caption>Receita recebida por mês</p>
            </div>
            <div class="segmented" role="tablist">
                <button type="button" data-tab="revenue" class="is-active">Receita</button>
                <button type="button" data-tab="sessions">Treinos</button>
            </div>
        </div>
        <div style="position:relative;height:280px">
            <div class="skeleton" style="position:absolute;inset:0" data-chart-skeleton></div>
            <canvas id="dash-chart" aria-label="Gráfico mensal"></canvas>
        </div>
    </div>

    <div class="stack" style="--gap:16px">
        <div class="card stat reveal" style="--i:6">
            <div class="stat-label"><span class="stat-icon"><?= icon('flame', '', 18) ?></span>Treinos concluídos</div>
            <div class="stat-value"><?= $sessionsQty ?></div>
            <div class="stat-foot">no período selecionado</div>
        </div>
        <a href="/admin/alunos?situacao=vencendo" class="card card-link stat reveal" style="--i:7">
            <div class="stat-label"><span class="stat-icon" style="background:var(--warning-soft);color:var(--warning)"><?= icon('calendar-days', '', 18) ?></span>Vencem em 7 dias</div>
            <div class="stat-value"><?= (int) $students['expiring'] ?></div>
            <div class="stat-foot"><?= (int) $students['expired'] ?> já vencido<?= (int) $students['expired'] === 1 ? '' : 's' ?> ou sem pagamento</div>
        </a>
    </div>
</section>

<section class="grid grid-3">
    <div class="card reveal" style="--i:8">
        <div class="section-title"><h2>Vencimentos</h2><a href="/admin/alunos?situacao=vencendo">Ver todos</a></div>
        <?php if ($expiringList === []): ?>
            <?= \App\Core\View::partial('empty', ['icon' => 'calendar-check', 'heading' => 'Tudo em dia', 'text' => 'Nenhum aluno vencendo nos próximos 7 dias.']) ?>
        <?php else: ?>
            <div class="list mt-2">
                <?php foreach ($expiringList as $s): $sit = student_situation($s + ['status' => 'active']); ?>
                    <a href="/admin/alunos/<?= (int) $s['id'] ?>" class="list-item">
                        <span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
                        <div class="grow">
                            <div class="small bold truncate"><?= e($s['name']) ?></div>
                            <div class="xsmall muted"><?= $s['access_until'] ? e(date_br($s['access_until'])) : 'nunca pagou' ?><?= $s['auto_renew'] ? ' · renova sozinho' : '' ?></div>
                        </div>
                        <span class="badge badge-<?= $sit['tone'] ?>"><?= e($sit['label']) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <div class="card reveal" style="--i:9">
        <div class="section-title"><h2>Pendentes</h2><a href="/admin/financeiro?status=pending">Ver todos</a></div>
        <?php if ($pendingList === []): ?>
            <?= \App\Core\View::partial('empty', ['icon' => 'circle-check', 'heading' => 'Nada pendente', 'text' => 'Todas as mensalidades estão quitadas.']) ?>
        <?php else: ?>
            <div class="list mt-2">
                <?php foreach ($pendingList as $p): ?>
                    <a href="/admin/alunos/<?= (int) $p['student_id'] ?>?aba=financeiro" class="list-item">
                        <span class="avatar avatar-sm"><?= e(initials($p['name'])) ?></span>
                        <div class="grow">
                            <div class="small bold truncate"><?= e($p['name']) ?></div>
                            <div class="xsmall muted">vence <?= e(relative_days($p['due_date'])) ?><?= $p['origin'] === 'auto_renewal' ? ' · renovação' : '' ?></div>
                        </div>
                        <span class="small bold num"><?= e(money($p['amount'])) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <div class="card reveal" style="--i:10">
        <div class="section-title"><h2>Atividade recente</h2></div>
        <?php if ($recentList === []): ?>
            <?= \App\Core\View::partial('empty', ['icon' => 'activity', 'heading' => 'Academia vazia… por enquanto', 'text' => 'Quando seus alunos iniciarem treinos, eles aparecem aqui.']) ?>
        <?php else: ?>
            <div class="list mt-2">
                <?php foreach ($recentList as $r): ?>
                    <a href="/admin/alunos/<?= (int) $r['student_id'] ?>" class="list-item">
                        <span class="avatar avatar-sm"><?= e(initials($r['name'])) ?></span>
                        <div class="grow">
                            <div class="small bold truncate"><?= e(first_name($r['name'])) ?> · <?= e($r['workout_name']) ?></div>
                            <div class="xsmall muted"><?= e(date_br($r['started_at'], 'd/m H:i')) ?></div>
                        </div>
                        <?php if ($r['status'] === 'completed'): ?>
                            <span class="badge badge-success">Concluído</span>
                        <?php elseif ($r['status'] === 'in_progress'): ?>
                            <span class="badge badge-info">Treinando</span>
                        <?php else: ?>
                            <span class="badge badge-neutral">Abandonado</span>
                        <?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>

<style>
    @media (max-width: 1024px) { [data-dash-split] { grid-template-columns: minmax(0, 1fr) !important; } }
</style>

<script type="application/json" id="dash-data"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const data = JSON.parse(document.getElementById('dash-data').textContent);
    const canvas = document.getElementById('dash-chart');
    const caption = document.querySelector('[data-chart-caption]');
    if (!window.Chart || !canvas) return;

    const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
    let mode = 'revenue';
    let chart;

    const build = () => {
        chart?.destroy();
        const primary = css('--primary');
        const ctx = canvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, primary + 'cc');
        grad.addColorStop(1, primary + '22');

        chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    data: mode === 'revenue' ? data.revenue : data.sessions,
                    backgroundColor: grad,
                    hoverBackgroundColor: primary,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 36,
                }],
            },
            options: {
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: css('--surface-3'),
                        titleColor: css('--text'),
                        bodyColor: css('--text'),
                        borderColor: css('--border-strong'),
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: (c) => mode === 'revenue' ? brl.format(c.parsed.y) : `${c.parsed.y} treino${c.parsed.y === 1 ? '' : 's'}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: css('--muted') } },
                    y: {
                        beginAtZero: true,
                        grid: { color: css('--border') },
                        border: { display: false },
                        ticks: {
                            color: css('--muted'),
                            precision: 0,
                            callback: (v) => mode === 'revenue' ? brl.format(v) : v,
                        },
                    },
                },
                font: { family: css('--font') },
            },
        });
        document.querySelector('[data-chart-skeleton]')?.remove();
    };

    Chart.defaults.font.family = 'Geist, system-ui, sans-serif';
    build();

    canvas.closest('[data-tabs]').addEventListener('tabchange', (e) => {
        mode = e.detail;
        caption.textContent = mode === 'revenue' ? 'Receita recebida por mês' : 'Treinos concluídos por mês';
        build();
    });
    document.addEventListener('themechange', () => setTimeout(build, 50));
});
</script>
