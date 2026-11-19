<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Dependências ausentes: rode "composer install".');
}
require $autoload;

Env::load(BASE_PATH . '/.env');

$config = [
    'app'      => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
];

date_default_timezone_set($config['app']['timezone']);

foreach (['storage/logs', 'storage/cache'] as $dir) {
    if (!is_dir(BASE_PATH . '/' . $dir)) {
        mkdir(BASE_PATH . '/' . $dir, 0755, true);
    }
}

$app = new App($config);

(require BASE_PATH . '/routes/web.php')($app->router());

return $app;
