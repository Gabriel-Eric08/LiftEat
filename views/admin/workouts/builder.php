<?php
$draft = flash('builder_draft');
$name = $draft['name'] ?? $workout['name'];
$notes = $draft['notes'] ?? (string) $workout['notes'];
$selectedDays = $draft['days'] ?? $days;

$initialItems = $draft['items'] ?? array_map(static fn ($it) => [
    'id'           => (int) $it['id'],
    'exercise_id'  => (int) $it['exercise_id'],
    'name'         => $it['name'],
    'muscle_group' => $it['muscle_group'],
    'sets'         => (int) $it['sets'],
    'reps'         => $it['reps'],
    'load_kg'      => $it['load_kg'] === null ? '' : rtrim(rtrim(number_format((float) $it['load_kg'], 2, ',', ''), '0'), ','),
    'rest_seconds' => $it['rest_seconds'] === null ? '' : (int) $it['rest_seconds'],
    'technique'    => (string) $it['technique'],
    'notes'        => (string) $it['notes'],
], $items);
?>
<form method="post" action="/admin/treinos/<?= (int) $workout['id'] ?>" class="grid builder" data-builder>
    <?= csrf_field() ?>
    <input type="hidden" name="items" value="" data-items-input>

    <div class="stack" style="--gap:16px;min-width:0">
        <div class="page-header reveal">
            <div>
                <h1 data-title-preview><?= e($name) ?></h1>
                <p>Treino de <a href="/admin/alunos/<?= (int) $student['id'] ?>?aba=treinos" class="text-primary"><?= e($student['name']) ?></a><?= $workout['is_active'] ? '' : ' · <span class="badge badge-neutral">arquivado</span>' ?></p>
            </div>
            <button type="button" class="btn btn-soft" data-open="picker"><?= icon('plus', '', 18) ?><span>Adicionar exercícios</span></button>
        </div>

        <div class="stack" style="--gap:10px" data-rows></div>

        <div class="card card-glow reveal" data-empty hidden>
            <?= \App\Core\View::partial('empty', [
                'icon'    => 'dumbbell',
                'heading' => 'Treino em branco',
                'text'    => 'Adicione exercícios da sua biblioteca. Você define séries, repetições, carga e descanso de cada um.',
                'action'  => '<button type="button" class="btn btn-primary" data-open="picker">' . icon('plus', '', 16) . '<span>Adicionar exercícios</span></button>',
            ]) ?>
        </div>

        <button type="button" class="btn btn-outline btn-block" data-open="picker" data-add-more hidden><?= icon('plus', '', 16) ?><span>Adicionar mais exercícios</span></button>
    </div>

    <aside class="stack builder-aside" style="--gap:16px">
        <section class="card stack reveal" style="--i:1">
            <div class="field">
                <label class="label" for="w-name">Nome do treino</label>
                <input class="input" id="w-name" name="name" value="<?= e($name) ?>" maxlength="120" placeholder="Ex.: Treino A — Peito e tríceps" required data-name-input>
            </div>
            <div class="field">
                <span class="label">Dias da semana</span>
                <div class="day-toggles">
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <label class="day-toggle" title="<?= isset($otherDays[$d]) ? e('Hoje: ' . $otherDays[$d] . ' (será substituído)') : e(weekday_name($d)) ?>">
                            <input type="checkbox" name="days[]" value="<?= $d ?>" <?= in_array($d, $selectedDays, true) ? 'checked' : '' ?> data-day="<?= $d ?>" data-other="<?= e($otherDays[$d] ?? '') ?>">
                            <span><?= e(weekday_name($d, true)) ?></span>
                            <?php if (isset($otherDays[$d])): ?><i class="taken" aria-hidden="true"></i><?php endif ?>
                        </label>
                    <?php endfor ?>
                </div>
                <?php if ($otherDays): ?>
                    <span class="hint">Dias com ponto amarelo já têm outro treino; marcar aqui substitui.</span>
                <?php endif ?>
            </div>
            <div class="field">
                <label class="label" for="w-notes">Orientações gerais <span class="muted">(opcional)</span></label>
                <textarea class="textarea" id="w-notes" name="notes" style="min-height:76px" placeholder="Aquecimento, cadência, cardio no final…"><?= e($notes) ?></textarea>
            </div>
        </section>

        <section class="card reveal" style="--i:2;padding:16px">
            <div class="grid grid-3 center" style="--gap:8px">
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-ex>0</div><div class="xsmall muted">exercícios</div></div>
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-sets>0</div><div class="xsmall muted">séries</div></div>
                <div><div class="stat-value" style="font-size:1.3rem" data-sum-min>0</div><div class="xsmall muted">min aprox.</div></div>
            </div>
        </section>

        <div class="stack reveal" style="--i:3;--gap:8px">
            <button type="submit" class="btn btn-primary btn-lg btn-block"><?= icon('check', '', 18) ?><span>Salvar treino</span></button>
            <button type="submit" name="after" value="aluno" class="btn btn-ghost btn-block">Salvar e voltar para o aluno</button>
            <span class="xsmall muted center" data-dirty hidden>Alterações não salvas</span>
        </div>

        <section class="card stack reveal" style="--i:4;padding:16px;--gap:10px">
            <span class="label">Mais ações</span>
            <button type="button" class="btn btn-outline btn-sm btn-block" data-open="duplicate-modal"><?= icon('repeat', '', 16) ?><span>Duplicar / copiar para aluno</span></button>
            <button type="submit" form="toggle-form" class="btn btn-ghost btn-sm btn-block"><?= icon($workout['is_active'] ? 'eye-off' : 'eye', '', 16) ?><span><?= $workout['is_active'] ? 'Arquivar treino' : 'Reativar treino' ?></span></button>
            <button type="submit" form="delete-form" class="btn btn-danger btn-sm btn-block"><?= icon('trash-2', '', 16) ?><span>Excluir treino</span></button>
        </section>
    </aside>
</form>

<form id="toggle-form" method="post" action="/admin/treinos/<?= (int) $workout['id'] ?>/status" hidden><?= csrf_field() ?></form>
<form id="delete-form" method="post" action="/admin/treinos/<?= (int) $workout['id'] ?>/excluir" hidden
      data-confirm="Excluir “<?= e($workout['name']) ?>”? Treinos já realizados continuam no histórico do aluno." data-confirm-label="Excluir treino"><?= csrf_field() ?></form>

<!-- Seletor de exercícios -->
<dialog class="modal picker" id="picker">
    <div class="modal-header">
        <div>
            <h2>Adicionar exercícios</h2>
            <p class="small muted mt-1">Toque para adicionar. Pode escolher vários.</p>
        </div>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
    </div>
    <div class="modal-body stack" style="--gap:12px;padding-bottom:12px">
        <div class="input-group">
            <?= icon('search', '', 18) ?>
            <input class="input" type="search" placeholder="Buscar exercício…" autocomplete="off" data-picker-search>
        </div>
        <div class="chips" data-picker-groups></div>
        <div class="picker-list" data-picker-list></div>
        <div class="card stack" style="padding:14px;--gap:10px" data-quick hidden>
            <span class="small">Não encontrou? Crie <strong data-quick-name></strong> agora:</span>
            <div class="row" style="--gap:8px">
                <input class="input grow" list="muscle-groups-b" placeholder="Grupo muscular" data-quick-group>
                <button type="button" class="btn btn-primary" data-quick-create><?= icon('plus', '', 16) ?><span>Criar</span></button>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <a href="/admin/exercicios" class="btn btn-ghost btn-sm" target="_blank">Gerenciar biblioteca</a>
        <button type="button" class="btn btn-primary" data-close>Concluir</button>
    </div>
</dialog>

<!-- Duplicar -->
<dialog class="modal" id="duplicate-modal">
    <form method="post" action="/admin/treinos/<?= (int) $workout['id'] ?>/duplicar">
        <?= csrf_field() ?>
        <div class="modal-header">
            <h2>Duplicar treino</h2>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <p class="small muted">Cria uma cópia com os mesmos exercícios (sem os dias da semana). Salve antes se tiver alterações pendentes.</p>
            <div class="field">
                <label class="label" for="dup-student">Para qual aluno?</label>
                <select class="select" id="dup-student" name="student_id">
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $student['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?><?= (int) $s['id'] === (int) $student['id'] ? ' (mesmo aluno)' : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><?= icon('repeat', '', 16) ?><span>Duplicar</span></button>
        </div>
    </form>
</dialog>

<datalist id="techniques">
    <?php foreach ($techniques as $t): ?><option value="<?= e($t) ?>"><?php endforeach ?>
</datalist>
<datalist id="muscle-groups-b">
    <?php foreach ($groups as $g): ?><option value="<?= e($g) ?>"><?php endforeach ?>
</datalist>

<template id="row-template">
    <article class="card ex-row">
        <div class="ex-row-head">
            <button type="button" class="ex-handle" draggable="true" aria-label="Arrastar para reordenar" title="Arrastar"><?= icon('ellipsis-vertical', '', 18) ?></button>
            <span class="ex-num mono"></span>
            <div class="grow" style="min-width:0">
                <div class="bold truncate" data-f="name"></div>
                <div class="xsmall muted" data-f="muscle_group"></div>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="up" aria-label="Subir"><?= icon('chevron-left', 'rot-90', 16) ?></button>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="down" aria-label="Descer"><?= icon('chevron-right', 'rot-90', 16) ?></button>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-act="remove" aria-label="Remover"><?= icon('x', '', 16) ?></button>
        </div>
        <div class="ex-fields">
            <label class="field"><span class="label">Séries</span><input class="input" type="number" min="1" max="20" inputmode="numeric" data-k="sets"></label>
            <label class="field"><span class="label">Repetições</span><input class="input" maxlength="20" placeholder="10, 8-12…" data-k="reps"></label>
            <label class="field"><span class="label">Carga (kg)</span><input class="input" inputmode="decimal" placeholder="—" data-k="load_kg"></label>
            <label class="field"><span class="label">Descanso</span>
                <select class="select" data-k="rest_seconds">
                    <option value="">—</option>
                    <?php foreach ([30, 45, 60, 75, 90, 120, 150, 180, 240, 300] as $sec): ?>
                        <option value="<?= $sec ?>"><?= $sec < 60 ? $sec . 's' : (intdiv($sec, 60) . ($sec % 60 ? 'min' . ($sec % 60) : ' min')) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="field"><span class="label">Técnica</span><input class="input" list="techniques" maxlength="60" placeholder="—" data-k="technique"></label>
            <label class="field ex-notes"><span class="label">Observação</span><input class="input" maxlength="255" placeholder="Dica para o aluno…" data-k="notes"></label>
        </div>
    </article>
</template>

<script type="application/json" id="builder-data"><?= json_encode([
    'items'   => $initialItems,
    'library' => array_map(static fn ($e) => [
        'id' => (int) $e['id'], 'name' => $e['name'], 'muscle_group' => $e['muscle_group'], 'equipment' => $e['equipment'],
    ], $library),
    'csrf'    => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
