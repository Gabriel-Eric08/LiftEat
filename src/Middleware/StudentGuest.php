<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class StudentGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::student() !== null) {
            return Response::redirect('/app');
        }

        return $next($request);
    }
}
