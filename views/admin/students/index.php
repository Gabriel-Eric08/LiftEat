<?php
use App\Core\View;

// Monta a URL da lista mantendo busca, situação e ordenação (valores vazios/padrão saem da URL).
$qs = static fn (array $extra = []) => '?' . http_build_query(array_filter(
    [...['situacao' => $situation, 'q' => $search, 'ordem' => $sort], ...$extra],
    static fn ($v, $k) => $v !== '' && $v !== null && !($k === 'ordem' && $v === 'nome') && !($k === 'situacao' && $v === 'todos'),
    ARRAY_FILTER_USE_BOTH,
));
?>
<div class="page-header reveal">
    <div>
        <h1>Alunos</h1>
        <p><?= (int) $counts['todos'] ?> cadastrado<?= $counts['todos'] === 1 ? '' : 's' ?> · <?= (int) $counts['ativos'] ?> com acesso liberado</p>
    </div>
    <a href="/admin/alunos/novo" class="btn btn-primary"><?= icon('user-plus', '', 18) ?><span>Novo aluno</span></a>
</div>

<div class="card card-flush reveal" style="--i:1">
    <div class="stack" style="padding:16px 16px 0;--gap:12px">
        <div class="row" style="--gap:8px;flex-wrap:wrap">
            <form method="get" class="input-group grow" role="search" data-no-loading>
                <?= icon('search', '', 18) ?>
                <input type="hidden" name="situacao" value="<?= e($situation) ?>">
                <input type="hidden" name="ordem" value="<?= e($sort) ?>">
                <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome, e-mail ou telefone…" autocomplete="off">
            </form>
            <form method="get" data-no-loading>
                <input type="hidden" name="situacao" value="<?= e($situation) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <label class="sr-only" for="ordem">Ordenar por</label>
                <select class="select" id="ordem" name="ordem" data-autosubmit aria-label="Ordenar por">
                    <?php foreach ($sorts as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $key === $sort ? 'selected' : '' ?>>Ordenar: <?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
                <noscript><button class="btn btn-outline btn-sm">Ordenar</button></noscript>
            </form>
        </div>
        <nav class="chips" aria-label="Filtrar por situação">
            <?php foreach ($situations as $key => $label): ?>
                <a href="<?= e($qs(['situacao' => $key])) ?>" class="chip <?= $key === $situation ? 'is-active' : '' ?>">
                    <?= e($label) ?> <span class="count"><?= (int) $counts[$key] ?></span>
                </a>
            <?php endforeach ?>
        </nav>
    </div>

    <?php if ($students === []): ?>
        <?php if ($search !== '' || $situation !== 'todos'): ?>
            <?= View::partial('empty', [
                'icon'    => 'search',
                'heading' => 'Ninguém por aqui',
                'text'    => 'Nenhum aluno combina com esse filtro. Tente outra busca.',
                'action'  => '<a href="/admin/alunos" class="btn btn-outline btn-sm">Limpar filtros</a>',
            ]) ?>
        <?php else: ?>
            <?= View::partial('empty', [
                'icon'    => 'users',
                'heading' => 'Sua turma começa aqui',
                'text'    => 'Cadastre seu primeiro aluno para montar treinos, dietas e acompanhar os pagamentos.',
                'action'  => '<a href="/admin/alunos/novo" class="btn btn-primary">' . icon('user-plus', '', 16) . '<span>Cadastrar aluno</span></a>',
            ]) ?>
        <?php endif ?>
    <?php else: ?>
        <div class="table-wrap mt-3">
            <table class="table">
                <thead>
                <tr>
                    <th>Aluno</th>
                    <th class="hide-sm">Situação</th>
                    <th class="hide-sm">Acesso até</th>
                    <th class="hide-sm right">Mensalidade</th>
                    <th class="hide-sm">Último treino</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): $sit = student_situation($s); ?>
                    <tr class="reveal" style="--i:<?= min($i, 12) ?>">
                        <td>
                            <a href="/admin/alunos/<?= (int) $s['id'] ?>" class="row" style="--gap:12px">
                                <span class="avatar"><?= e(initials($s['name'])) ?></span>
                                <span class="grow">
                                    <span class="bold truncate" style="display:block"><?= e($s['name']) ?></span>
                                    <span class="xsmall muted truncate" style="display:block"><?= e($s['email']) ?></span>
                                    <span class="badge badge-<?= $sit['tone'] ?> mt-1" style="display:none" data-sm-badge><?= e($sit['label']) ?></span>
                                </span>
                            </a>
                        </td>
                        <td class="hide-sm"><span class="badge badge-<?= $sit['tone'] ?>"><?= e($sit['label']) ?></span></td>
                        <td class="hide-sm num">
                            <?= e(date_br($s['access_until'])) ?>
                            <?php if ($s['auto_renew']): ?><span title="Renovação automática" class="muted"><?= icon('repeat', '', 14) ?></span><?php endif ?>
                        </td>
                        <td class="hide-sm right num"><?= e(money($s['monthly_fee'])) ?></td>
                        <td class="hide-sm muted small"><?= $s['last_session_at'] ? e(relative_days($s['last_session_at'])) : 'nunca' ?></td>
                        <td class="actions">
                            <a href="/admin/alunos/<?= (int) $s['id'] ?>" class="btn btn-ghost btn-icon btn-sm" aria-label="Abrir"><?= icon('chevron-right', '', 18) ?></a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="row-between" style="padding:12px 16px;border-top:1px solid var(--border)">
                <span class="small muted">Página <?= $page ?> de <?= $pages ?> · <?= $total ?> alunos</span>
                <div class="row" style="--gap:6px">
                    <a class="btn btn-outline btn-sm" href="<?= e($qs(['pagina' => $page - 1])) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= icon('chevron-left', '', 16) ?></a>
                    <a class="btn btn-outline btn-sm" href="<?= e($qs(['pagina' => $page + 1])) ?>" <?= $page >= $pages ? 'aria-disabled="true"' : '' ?>><?= icon('chevron-right', '', 16) ?></a>
                </div>
            </div>
        <?php endif ?>
    <?php endif ?>
</div>

<style>
    @media (max-width: 560px) { [data-sm-badge] { display: inline-flex !important; } }
</style>
