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

        // "?entrou=1" permite detectar, na página seguinte, se o navegador perdeu a sessão.
        return redirect($homeUrl . '?entrou=1');
    }

    /** Mensagem de erro da tela de login (tentativa anterior ou sessão perdida). */
    private function loginError(Request $request): ?string
    {
        if ($request->query('erro') === 'sessao') {
            return 'Seu login foi aceito, mas o navegador não guardou a sessão. '
                . 'Verifique se os cookies estão permitidos para este site (e se não está em modo que bloqueia cookies) e tente de novo.';
        }

        return flash('login_error');
    }

    private function loginFailed(Request $request, string $loginUrl, string $message): Response
    {
        Session::flashInput(['email' => $request->string('email')]);
        Session::flash('login_error', $message);

        return redirect($loginUrl);
    }
}
