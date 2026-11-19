<?php

declare(strict_types=1);

/*
 * Front controller: toda requisição que não for um arquivo real cai aqui.
 * Só esta pasta (public_html) fica exposta na web; o resto do projeto
 * fica um nível acima, fora do alcance do navegador.
 */

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->run();
