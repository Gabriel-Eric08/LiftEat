<?php
$inactive = $student['status'] !== 'active';
$never = $student['access_until'] === null;
?>
<section class="card card-glow reveal" style="margin-top:8vh">
    <div class="empty">
        <div class="empty-icon"><?= icon($inactive ? 'power' : 'hourglass', '', 28) ?></div>
        <h3>
            <?php if ($inactive): ?>Seu acesso está pausado
            <?php elseif ($never): ?>Quase lá!
            <?php else: ?>Sua mensalidade venceu
            <?php endif ?>
        </h3>
        <p>
            <?php if ($inactive): ?>
                Seu personal pausou seu acesso por enquanto. Fale com ele para voltar a ver seus treinos e sua dieta.
            <?php elseif ($never): ?>
                Seu cadastro está pronto. Assim que o personal confirmar o primeiro pagamento, seu app é liberado.
            <?php else: ?>
                Seu acesso foi até <?= e(date_br($student['access_until'])) ?>. Renove com seu personal e volte para o treino. Seu histórico continua guardado.
            <?php endif ?>
        </p>
    </div>
</section>

<div class="stack reveal" style="--i:1;--gap:10px">
    <a href="/app/acesso" class="btn btn-primary btn-lg btn-block"><?= icon('refresh-cw', '', 18) ?><span>Já renovei, verificar</span></a>
    <form method="post" action="/app/logout" data-no-loading>
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-block"><?= icon('log-out', '', 16) ?><span>Sair</span></button>
    </form>
</div>
