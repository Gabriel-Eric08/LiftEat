<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * GET /health — confirma após o deploy que PHP, banco e pastas graváveis estão ok.
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

        // Sessões e logs precisam de escrita; sem isso o login não se mantém.
        $storage = [];
        foreach (['sessions', 'logs', 'cache'] as $dir) {
            $path = BASE_PATH . '/storage/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $storage[$dir] = is_dir($path) && is_writable($path) ? 'ok' : 'sem permissão';
        }

        $ok = $db === 'ok' && !in_array('sem permissão', $storage, true);

        return Response::json([
            'status'  => $ok ? 'ok' : 'erro',
            'php'     => PHP_VERSION,
            'db'      => $db,
            'storage' => $storage,
            'https'   => $request->isSecure(),
        ], $ok ? 200 : 503);
    }
}
