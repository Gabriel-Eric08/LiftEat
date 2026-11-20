<?php

declare(strict_types=1);

/*
 * Bootstrap dos testes.
 *
 * Os testes NUNCA tocam no banco: o .env não é carregado e a conexão aponta
 * para um endereço inválido. Se algum código testado tentar consultar o banco
 * por engano, o teste falha na hora em vez de alterar dados reais.
 */

use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

Database::configure([
    'host'     => '127.0.0.1',
    'port'     => 1,
    'database' => 'banco_bloqueado_nos_testes',
    'username' => 'nenhum',
    'password' => '',
    'charset'  => 'utf8mb4',
]);
