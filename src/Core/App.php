<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class App
{
    private Router $router;

    /** @param array<string, array<string, mixed>> $config */
    public function __construct(private readonly array $config)
    {
        $this->router = new Router();

        Database::configure($config['database']);
        $this->configureErrors();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        try {
            $response = $this->router->dispatch(Request::fromGlobals());
        } catch (Throwable $e) {
            $response = $this->handleException($e);
        }

        $response->send();
    }

    private function configureErrors(): void
    {
        $debug = (bool) $this->config['app']['debug'];

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
    }

    private function handleException(Throwable $e): Response
    {
        error_log(sprintf(
            "[%s] %s: %s em %s:%d\n%s",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        ));

        if ($this->config['app']['debug']) {
            $html = '<h1>' . e($e::class) . '</h1>'
                . '<p>' . e($e->getMessage()) . '</p>'
                . '<p>' . e($e->getFile()) . ':' . $e->getLine() . '</p>'
                . '<pre>' . e($e->getTraceAsString()) . '</pre>';

            return Response::html($html, 500);
        }

        return Response::html('<h1>Erro interno</h1><p>Tente novamente mais tarde.</p>', 500);
    }
}
