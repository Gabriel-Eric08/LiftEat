<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * GET /health — confirma após o deploy que o PHP e o banco estão ok.
 */
final class HealthController
{
    public function show(Request $request): Response
    {
        try {
            Database::connection()->query('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'erro';
        }

        return Response::json([
            'status' => 'ok',
            'php'    => PHP_VERSION,
            'db'     => $db,
        ], $db === 'ok' ? 200 : 503);
    }
}
