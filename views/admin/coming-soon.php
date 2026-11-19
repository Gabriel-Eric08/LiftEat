<div class="page-header reveal">
    <div>
        <h1><?= e($section['title']) ?></h1>
    </div>
</div>

<div class="card card-glow reveal" style="--i:1">
    <?= \App\Core\View::partial('empty', [
        'icon'    => $section['icon'],
        'heading' => 'Aquecendo para entrar em cena',
        'text'    => $section['text'] . ' Essa seção é a próxima da fila.',
        'action'  => '<a href="/admin/alunos" class="btn btn-outline">' . icon('users', '', 16) . '<span>Ir para alunos</span></a>',
    ]) ?>
</div>
