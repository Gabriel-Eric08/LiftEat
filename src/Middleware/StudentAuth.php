<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Log;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class StudentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::student() === null) {
            if ($request->query('entrou') !== null) {
                Log::auth('student', '', $request->ip(), 'SESSÃO PERDIDA: login aceito, mas o navegador não devolveu o cookie de sessão');

                return Response::redirect('/app/login?erro=sessao');
            }

            return Response::redirect('/app/login');
        }

        return $next($request);
    }
}
