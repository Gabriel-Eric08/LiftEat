<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HealthController::class, 'show']);

    // Instalação do banco (só ativa com INSTALL_TOKEN no .env)
    $router->get('/install', [InstallController::class, 'show']);
    $router->post('/install/migrate', [InstallController::class, 'migrate']);
    $router->post('/install/admin', [InstallController::class, 'createAdmin']);
};
