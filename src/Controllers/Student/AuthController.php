<?php

declare(strict_types=1);

namespace App\Controllers\Student;

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
            'title'            => 'Entrar',
            'heading'          => 'Bora treinar?',
            'subtitle'         => 'Entre para ver seu treino e sua dieta de hoje.',
            'action'           => '/app/login',
            'emailPlaceholder' => 'seu@email.com',
            'error'            => $this->loginError($request),
            'foot'             => 'Esqueceu a senha? Fale com seu personal.',
        ], 'layouts/blank');
    }

    public function login(Request $request): Response
    {
        return $this->attemptLogin($request, 'student', '/app/login', '/app');
    }

    public function logout(Request $request): Response
    {
        Auth::logout('student');

        return redirect('/app/login');
    }
}
