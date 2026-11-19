<?php
use App\Core\View;

$qs = static fn (array $extra) => '?' . http_build_query(array_filter([
    'q' => $search, 'grupo' => $group, 'inativos' => $showInactive ? '1' : null, ...$extra,
], static fn ($v) => $v !== null && $v !== ''));
?>
<div class="page-header reveal">
    <div>
        <h1>Exercícios</h1>
        <p><?= $total ?> exercício<?= $total === 1 ? '' : 's' ?> ativo<?= $total === 1 ? '' : 's' ?> na sua biblioteca. Cadastre uma vez, use em todos os treinos.</p>
    </div>
    <button type="button" class="btn btn-primary" data-exercise-new><?= icon('plus', '', 18) ?><span>Novo exercício</span></button>
</div>

<div class="card card-flush reveal" style="--i:1">
    <div class="stack" style="padding:16px 16px 0;--gap:12px">
        <div class="row wrap" style="--gap:10px">
            <form method="get" class="input-group grow" role="search" data-no-loading style="min-width:220px">
                <?= icon('search', '', 18) ?>
                <?php if ($group !== ''): ?><input type="hidden" name="grupo" value="<?= e($group) ?>"><?php endif ?>
                <?php if ($showInactive): ?><input type="hidden" name="inativos" value="1"><?php endif ?>
                <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar exercício ou equipamento…" autocomplete="off">
            </form>
            <a href="<?= e($qs(['inativos' => $showInactive ? null : '1'])) ?>" class="btn btn-ghost btn-sm">
                <?= icon($showInactive ? 'eye-off' : 'eye', '', 16) ?><span><?= $showInactive ? 'Ocultar inativos' : 'Mostrar inativos' ?></span>
            </a>
        </div>
        <nav class="chips" aria-label="Grupo muscular">
            <a href="<?= e($qs(['grupo' => null])) ?>" class="chip <?= $group === '' ? 'is-active' : '' ?>">Todos</a>
            <?php foreach ($groups as $g): ?>
                <a href="<?= e($qs(['grupo' => $g['muscle_group']])) ?>" class="chip <?= $group === $g['muscle_group'] ? 'is-active' : '' ?>">
                    <?= e($g['muscle_group']) ?> <span class="count"><?= (int) $g['qty'] ?></span>
                </a>
            <?php endforeach ?>
        </nav>
    </div>

    <?php if ($exercises === []): ?>
        <?= View::partial('empty', $search !== '' || $group !== ''
            ? ['icon' => 'search', 'heading' => 'Nada encontrado', 'text' => 'Nenhum exercício com esse filtro.',
               'action' => '<a href="/admin/exercicios" class="btn btn-outline btn-sm">Limpar filtros</a>']
            : ['icon' => 'list-checks', 'heading' => 'Biblioteca vazia', 'text' => 'Cadastre seus exercícios favoritos para montar treinos em segundos.',
               'action' => '<button type="button" class="btn btn-primary" data-exercise-new>' . icon('plus', '', 16) . '<span>Cadastrar exercício</span></button>']) ?>
    <?php else: ?>
        <div class="table-wrap mt-3">
            <table class="table">
                <thead><tr><th>Exercício</th><th class="hide-sm">Grupo</th><th class="hide-sm">Equipamento</th><th class="hide-sm">Uso</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($exercises as $i => $ex): ?>
                    <tr class="reveal" style="--i:<?= min($i, 14) ?><?= $ex['is_active'] ? '' : ';opacity:.55' ?>">
                        <td>
                            <div class="row" style="--gap:12px">
                                <span class="stat-icon" style="width:36px;height:36px"><?= icon('dumbbell', '', 16) ?></span>
                                <div class="grow">
                                    <div class="bold truncate"><?= e($ex['name']) ?><?= $ex['is_active'] ? '' : ' <span class="badge badge-neutral">inativo</span>' ?></div>
                                    <div class="xsmall muted truncate">
                                        <span class="show-sm-inline"><?= e($ex['muscle_group']) ?> · </span><?= $ex['description'] ? e(mb_strimwidth($ex['description'], 0, 70, '…')) : 'sem descrição' ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-sm"><span class="badge badge-plain"><?= e($ex['muscle_group']) ?></span></td>
                        <td class="hide-sm small muted"><?= e($ex['equipment'] ?? '—') ?></td>
                        <td class="hide-sm small muted"><?= (int) $ex['used_in'] ?> treino<?= (int) $ex['used_in'] === 1 ? '' : 's' ?></td>
                        <td class="actions">
                            <?php if ($ex['video_url']): ?>
                                <a href="<?= e($ex['video_url']) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm" title="Ver vídeo" aria-label="Ver vídeo"><?= icon('play', '', 16) ?></a>
                            <?php endif ?>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Editar" aria-label="Editar"
                                    data-exercise-edit='<?= e(json_encode([
                                        'id' => (int) $ex['id'], 'name' => $ex['name'], 'muscle_group' => $ex['muscle_group'],
                                        'equipment' => $ex['equipment'], 'description' => $ex['description'], 'video_url' => $ex['video_url'],
                                    ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('pencil', '', 16) ?></button>
                            <form method="post" action="/admin/exercicios/<?= (int) $ex['id'] ?>/status" style="display:inline" data-no-loading>
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-icon btn-sm" title="<?= $ex['is_active'] ? 'Desativar' : 'Reativar' ?>" aria-label="<?= $ex['is_active'] ? 'Desativar' : 'Reativar' ?>">
                                    <?= icon($ex['is_active'] ? 'eye-off' : 'eye', '', 16) ?>
                                </button>
                            </form>
                            <?php if ((int) $ex['used_in'] === 0 && (int) $ex['history'] === 0): ?>
                                <form method="post" action="/admin/exercicios/<?= (int) $ex['id'] ?>/excluir" style="display:inline"
                                      data-confirm="Excluir <?= e($ex['name']) ?> da biblioteca?" data-confirm-label="Excluir">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-icon btn-sm" title="Excluir" aria-label="Excluir"><?= icon('trash-2', '', 16) ?></button>
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

<dialog class="modal" id="exercise-modal">
    <form method="post" action="/admin/exercicios" data-exercise-form>
        <?= csrf_field() ?>
        <div class="modal-header">
            <h2 data-exercise-title>Novo exercício</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <div class="field">
                <label class="label" for="ex-name">Nome</label>
                <input class="input" id="ex-name" name="name" maxlength="120" placeholder="Ex.: Supino reto" required>
            </div>
            <div class="grid grid-2">
                <div class="field">
                    <label class="label" for="ex-group">Grupo muscular</label>
                    <input class="input" id="ex-group" name="muscle_group" list="muscle-groups" maxlength="60" placeholder="Peito" required>
                </div>
                <div class="field">
                    <label class="label" for="ex-equipment">Equipamento</label>
                    <input class="input" id="ex-equipment" name="equipment" list="equipments" maxlength="60" placeholder="Barra, halter…">
                </div>
            </div>
            <div class="field">
                <label class="label" for="ex-video">Link do vídeo <span class="muted">(opcional)</span></label>
                <input class="input" id="ex-video" name="video_url" type="url" maxlength="255" placeholder="https://youtube.com/…">
            </div>
            <div class="field">
                <label class="label" for="ex-description">Como executar <span class="muted">(opcional)</span></label>
                <textarea class="textarea" id="ex-description" name="description" placeholder="Dicas de postura e execução que o aluno vai ver no app."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><?= icon('check', '', 18) ?><span>Salvar</span></button>
        </div>
    </form>
</dialog>

<datalist id="muscle-groups">
    <?php foreach ($suggestions as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>
<datalist id="equipments">
    <?php foreach (['Barra', 'Halter', 'Máquina', 'Polia', 'Peso corporal', 'Kettlebell', 'Elástico', 'Smith', 'Banco', 'Bola'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?>
</datalist>

<style>
    .show-sm-inline { display: none; }
    @media (max-width: 560px) { .show-sm-inline { display: inline; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dlg = document.getElementById('exercise-modal');
    const form = dlg.querySelector('[data-exercise-form]');
    const title = dlg.querySelector('[data-exercise-title]');
    const fields = ['name', 'muscle_group', 'equipment', 'video_url', 'description'];

    const open = (data) => {
        form.reset();
        form.action = data ? `/admin/exercicios/${data.id}` : '/admin/exercicios';
        title.textContent = data ? 'Editar exercício' : 'Novo exercício';
        fields.forEach((f) => { form.elements[f].value = data?.[f] ?? ''; });
        form.querySelector('[type=submit]').classList.remove('is-loading');
        dlg.showModal();
        form.elements.name.focus();
    };

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-exercise-new]')) open(null);
        const edit = e.target.closest('[data-exercise-edit]');
        if (edit) open(JSON.parse(edit.dataset.exerciseEdit));
    });
});
</script>
