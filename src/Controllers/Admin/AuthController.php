<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Concerns\HandlesLogin;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthController
{
    use HandlesLogin;

    public function show(Request $request): Response
    {
        return view('auth/login', [
            'title'            => 'Entrar no painel',
            'heading'          => 'Painel do personal',
            'subtitle'         => 'Seus alunos, treinos e faturamento em um só lugar.',
            'action'           => '/admin/login',
            'emailPlaceholder' => 'voce@seudominio.com',
            'error'            => flash('login_error'),
            'foot'             => 'É aluno? <a href="/app/login">Acesse o app</a>',
        ], 'layouts/blank');
    }

    public function login(Request $request): Response
    {
        return $this->attemptLogin($request, 'admin', '/admin/login', '/admin');
    }

    public function logout(Request $request): Response
    {
        Auth::logout('admin');

        return redirect('/admin/login');
    }
}
