<?php

declare(strict_types=1);

/*
 * Aplica migrações pendentes pela linha de comando (uso local):
 *   php bin/migrate.php
 * Em produção, use /install?token=... (a hospedagem não tem SSH).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use App\Core\Migrator;

$migrator = new Migrator(Database::connection(), BASE_PATH . '/database/migrations');

try {
    $done = $migrator->migrate();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo $done === []
    ? 'Nenhuma migração pendente.' . PHP_EOL
    : 'Aplicadas:' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $done) . PHP_EOL;
