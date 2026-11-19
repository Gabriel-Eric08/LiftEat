<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Billing;
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
        $request = Request::fromGlobals();

        try {
            $response = $this->handle($request);
        } catch (HttpException $e) {
            $response = ErrorPage::response($e->status, $request, $e->getMessage(), $e->headers);
        } catch (Throwable $e) {
            $this->log($e);
            $response = ErrorPage::response(500, $request, '', [], $this->config['app']['debug'] ? $e : null);
        }

        $response->send();
    }

    private function handle(Request $request): Response
    {
        $path = $request->path();
        $isInfra = $path === '/health' || str_starts_with($path, '/install');

        if (!$isInfra) {
            Session::start($request->isSecure());

            if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                Csrf::verify($request);
            }

            // Renovações automáticas rodam no máximo uma vez por dia, na primeira visita.
            try {
                Billing::runDailyRenewals();
            } catch (Throwable $e) {
                $this->log($e);
            }
        }

        return $this->router->dispatch($request);
    }

    private function configureErrors(): void
    {
        $debug = (bool) $this->config['app']['debug'];

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
    }

    private function log(Throwable $e): void
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
    }
}
