<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class StudentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::student() === null) {
            return Response::redirect('/app/login');
        }

        return $next($request);
    }
}
