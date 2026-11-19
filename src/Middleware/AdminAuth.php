<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::admin() === null) {
            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}
