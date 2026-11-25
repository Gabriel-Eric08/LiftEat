<?php
/**
 * Modal "Duplicar / copiar para aluno" de um plano alimentar.
 *
 * @var array       $plan     plano de origem
 * @var list<array> $students alunos que podem receber a cópia (id, name)
 */
?>
<dialog class="modal" id="duplicate-diet-<?= (int) $plan['id'] ?>">
    <form method="post" action="/admin/dietas/<?= (int) $plan['id'] ?>/duplicar">
        <?= csrf_field() ?>
        <div class="modal-header">
            <div>
                <h2>Duplicar plano</h2>
                <p class="xsmall muted mt-1">Copia <?= e($plan['name']) ?> com todas as refeições, opções e alimentos.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Fechar"><?= icon('x', '', 18) ?></button>
        </div>
        <div class="modal-body stack">
            <div class="field">
                <label class="label" for="dup-student-<?= (int) $plan['id'] ?>">Para qual aluno</label>
                <select class="select" id="dup-student-<?= (int) $plan['id'] ?>" name="student_id">
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $plan['student_id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?><?= (int) $s['id'] === (int) $plan['student_id'] ? ' (mesmo aluno)' : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <span class="hint">A cópia entra inativa, a menos que o aluno não tenha nenhum plano ativo.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><?= icon('repeat', '', 18) ?><span>Duplicar</span></button>
        </div>
    </form>
</dialog>
