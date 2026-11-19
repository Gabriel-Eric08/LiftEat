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
            $response = $e->status === 419 && !$request->wantsJson()
                ? $this->expiredForm($request)
                : ErrorPage::response($e->status, $request, $e->getMessage(), $e->headers);
        } catch (Throwable $e) {
            $this->log($e);
            $response = ErrorPage::response(500, $request, '', [], $this->config['app']['debug'] ? $e : null);
        }

        // Páginas dinâmicas nunca devem ir para cache (proxy/CDN da hospedagem ou navegador):
        // uma página de login em cache carrega um token CSRF de outra sessão e o login falha.
        $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->withHeader('Pragma', 'no-cache')
            ->send();
    }

    /**
     * Formulário com token CSRF vencido (aba aberta há muito tempo, sessão renovada...):
     * em vez de uma tela de erro, volta para a página com um aviso e um token novo.
     */
    private function expiredForm(Request $request): Response
    {
        $path = $request->path();
        $isLogin = in_array(rtrim($path, '/'), ['/admin/login', '/app/login'], true);

        if ($isLogin) {
            Log::auth(str_starts_with($path, '/admin') ? 'admin' : 'student', $request->string('email'), $request->ip(), 'FALHOU: token do formulário inválido (página antiga ou cookie de sessão não recebido)');
            Session::flashInput(['email' => $request->string('email')]);
            Session::flash('login_error', 'A página de login estava desatualizada. Tente entrar de novo.');

            return Response::redirect($path);
        }

        Session::flash('error', 'O formulário expirou. Confira os dados e envie de novo.');

        return back(str_starts_with($path, '/admin') ? '/admin' : '/app');
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
