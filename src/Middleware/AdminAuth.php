<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Log;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::admin() === null) {
            // Acabou de logar (?entrou=1) e já não está autenticado: o cookie de sessão não voltou.
            if ($request->query('entrou') !== null) {
                Log::auth('admin', '', $request->ip(), 'SESSÃO PERDIDA: login aceito, mas o navegador não devolveu o cookie de sessão');

                return Response::redirect('/admin/login?erro=sessao');
            }

            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}
