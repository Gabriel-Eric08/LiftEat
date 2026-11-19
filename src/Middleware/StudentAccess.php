<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Bloqueia o app para aluno inativo ou com mensalidade vencida.
 * Deve vir depois de StudentAuth.
 */
final class StudentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::student();

        if ($student !== null && !Auth::studentHasAccess($student)) {
            return Response::redirect('/app/acesso');
        }

        return $next($request);
    }
}
