<?php
use App\Core\Session;
use App\Core\View;
use App\Services\BodyMeasurements as M;

/**
 * @var list<array> $records   do mais recente para o mais antigo
 * @var bool        $available false = migration ainda não aplicada
 * @var array       $variation saída de BodyMeasurements::variation()
 */
$reopen = (string) old('_form', '');

/** Campos do formulário (novo registro ou edição). */
$fields = static function (string $prefix, array $values): string {
    ob_start(); ?>
    <div class="field">
        <label class="label" for="<?= $prefix ?>-date">Data</label>
        <input class="input" type="date" id="<?= $prefix ?>-date" name="measured_on" required max="<?= date('Y-m-d') ?>" value="<?= e((string) ($values['measured_on'] ?? date('Y-m-d'))) ?>">
    </div>
    <div class="grid grid-2" style="--gap:10px">
        <?php foreach (M::FIELDS as $field => [$label, $unit, $min, $max]):
            $v = $values[$field] ?? '';
            $v = is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',') : (string) $v; ?>
            <div class="field">
                <label class="label" for="<?= $prefix . '-' . $field ?>"><?= e($label) ?> <span class="muted">(<?= $unit ?>)</span></label>
                <input class="input" id="<?= $prefix . '-' . $field ?>" name="<?= $field ?>" inputmode="decimal" autocomplete="off"
                       placeholder="<?= $field === 'weight_kg' ? 'Ex.: 72,5' : '—' ?>" value="<?= e($v) ?>">
            </div>
        <?php endforeach ?>
    </div>
    <div class="field">
        <label class="label" for="<?= $prefix ?>-notes">Observação <span class="muted">(opcional)</span></label>
        <input class="input" id="<?= $prefix ?>-notes" name="notes" maxlength="<?= M::NOTES_MAX ?>" placeholder="Ex.: em jejum, depois do treino…" value="<?= e((string) ($values['notes'] ?? '')) ?>">
    </div>
    <?php return (string) ob_get_clean();
};
?>

<?php if (!$available): ?>
    <section class="card card-glow reveal">
        <?= View::partial('empty', ['icon' => 'ruler', 'heading' => 'Quase pronto', 'text' => 'O registro de peso e medidas ainda está sendo liberado. Tente de novo mais tarde.']) ?>
    </section>
    <div class="stack reveal" style="--i:1;--gap:12px">
        <?= View::partial('progress-charts', ['charts' => $charts, 'action' => '/app/evolucao', 'showWeight' => false]) ?>
    </div>
<?php else: ?>
    <button type="button" class="btn btn-primary btn-block reveal" data-open="new-measurement"><?= icon('plus', '', 18) ?><span>Registrar peso e medidas</span></button>

    <div class="stack reveal" style="--i:1;--gap:12px">
        <?= View::partial('progress-charts', ['charts' => $charts, 'action' => '/app/evolucao']) ?>
    </div>

    <?php if ($records === []): ?>
        <section class="card card-glow reveal" style="--i:1">
            <?= View::partial('empty', [
                'icon'    => 'activity',
                'heading' => 'Comece sua linha do tempo',
                'text'    => 'Registre seu peso e medidas a cada semana ou quinzena. Você e seu personal acompanham a evolução por aqui.',
            ]) ?>
        </section>
    <?php else: ?>
        <?php if ($variation !== []): ?>
            <section class="card stack reveal" style="--i:1;--gap:10px;padding:16px">
                <span class="label">Desde o primeiro registro</span>
                <div class="grid grid-2" style="--gap:8px">
                    <?php foreach ($variation as $field => $v): ?>
                        <div style="padding:10px 12px;border-radius:12px;background:var(--surface-2)">
                            <div class="xsmall muted"><?= e(M::FIELDS[$field][0]) ?></div>
                            <div class="bold num"><?= e(M::format($field, $v['last'])) ?></div>
                            <div class="xsmall muted num">
                                <?= $v['count'] > 1 ? e(M::format($field, $v['delta'], true)) . ' desde ' . e(date_br($v['firstDate'], 'd/m/y')) : 'primeiro registro' ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </section>
        <?php endif ?>

        <section class="stack reveal" style="--i:2;--gap:10px">
            <div class="section-title"><h2>Registros</h2><span class="xsmall muted"><?= count($records) ?></span></div>
            <?php foreach ($records as $r):
                $values = array_filter(array_map(static fn ($f) => $r[$f] === null ? null : M::FIELDS[$f][0] . ' ' . M::format($f, $r[$f]), array_combine(array_keys(M::FIELDS), array_keys(M::FIELDS)))); ?>
                <article class="card stack" style="--gap:6px;padding:14px 16px">
                    <div class="row-between">
                        <span class="small bold"><?= e(date_br($r['measured_on'])) ?></span>
                        <div class="row" style="--gap:2px">
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-open="edit-measurement-<?= (int) $r['id'] ?>" aria-label="Editar"><?= icon('pencil', '', 16) ?></button>
                            <form method="post" action="/app/evolucao/<?= (int) $r['id'] ?>/excluir" data-confirm="O registro de <?= e(date_br($r['measured_on'])) ?> será excluído." data-confirm-label="Excluir">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-icon btn-sm" aria-label="Excluir"><?= icon('trash-2', '', 16) ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="small soft num"><?= e(implode(' · ', $values)) ?></div>
                    <?php if ($r['notes']): ?><div class="xsmall muted"><?= e($r['notes']) ?></div><?php endif ?>
                </article>

                <dialog class="modal" id="edit-measurement-<?= (int) $r['id'] ?>" <?= $reopen === (string) $r['id'] ? 'data-autoopen' : '' ?>>
                    <form method="post" action="/app/evolucao/<?= (int) $r['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_form" value="<?= (int) $r['id'] ?>">
                        <div class="modal-header">
                            <h2>Editar registro</h2>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
                        </div>
                        <div class="modal-body stack"><?= $fields('em' . (int) $r['id'], $reopen === (string) $r['id'] ? array_merge($r, (array) Session::getFlash('_old', [])) : $r) ?></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </dialog>
            <?php endforeach ?>
        </section>
    <?php endif ?>

    <dialog class="modal" id="new-measurement" <?= $reopen === 'new' ? 'data-autoopen' : '' ?>>
        <form method="post" action="/app/evolucao">
            <?= csrf_field() ?>
            <input type="hidden" name="_form" value="new">
            <div class="modal-header">
                <div>
                    <h2>Registrar medidas</h2>
                    <p class="xsmall muted mt-1">Preencha só o que você mediu. Na mesma data, o registro é atualizado.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
            </div>
            <div class="modal-body stack"><?= $fields('nm', $reopen === 'new' ? (array) Session::getFlash('_old', []) : []) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', '', 18) ?><span>Salvar</span></button>
            </div>
        </form>
    </dialog>
<?php endif ?>
