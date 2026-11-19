<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Fluxo de login compartilhado entre personal e aluno.
 */
trait HandlesLogin
{
    /** @param 'admin'|'student' $guard */
    private function attemptLogin(Request $request, string $guard, string $loginUrl, string $homeUrl): Response
    {
        $email = $request->string('email');
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return $this->loginFailed($request, $loginUrl, 'Informe e-mail e senha.');
        }

        try {
            $user = Auth::attempt($guard, $email, $password, $request->ip());
        } catch (HttpException $e) {
            if ($e->status === 429) {
                return $this->loginFailed($request, $loginUrl, $e->getMessage());
            }
            throw $e;
        }

        if ($user === null) {
            return $this->loginFailed($request, $loginUrl, 'E-mail ou senha incorretos.');
        }

        Session::flash('success', 'Bem-vindo de volta, ' . first_name($user['name']) . '!');

        return redirect($homeUrl);
    }

    private function loginFailed(Request $request, string $loginUrl, string $message): Response
    {
        Session::flashInput(['email' => $request->string('email')]);
        Session::flash('login_error', $message);

        return redirect($loginUrl);
    }
}
