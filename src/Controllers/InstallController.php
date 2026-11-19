<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Throwable;

/**
 * Instalação/atualização do banco pela web (a hospedagem não tem SSH).
 *
 * Só funciona se INSTALL_TOKEN estiver definido no .env; sem ele a rota responde 404.
 * Acesso: /install?token=SEU_TOKEN
 */
final class InstallController
{
    public function show(Request $request): Response
    {
        if (!$this->authorized($request)) {
            return $this->notFound();
        }

        return $this->page($request);
    }

    public function migrate(Request $request): Response
    {
        if (!$this->authorized($request)) {
            return $this->notFound();
        }

        try {
            $done = $this->migrator()->migrate();
            $message = $done === []
                ? 'Nenhuma migração pendente.'
                : 'Aplicadas: ' . implode(', ', $done);

            return $this->page($request, success: $message);
        } catch (Throwable $e) {
            return $this->page($request, error: $e->getMessage());
        }
    }

    public function createAdmin(Request $request): Response
    {
        if (!$this->authorized($request)) {
            return $this->notFound();
        }

        if ($this->adminExists()) {
            return $this->page($request, error: 'Já existe um administrador cadastrado.');
        }

        $name = trim((string) $request->input('name', ''));
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'A senha precisa ter pelo menos 8 caracteres.';
        }
        if ($errors !== []) {
            return $this->page($request, error: implode(' ', $errors));
        }

        Database::execute(
            'INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT)],
        );

        return $this->page($request, success: 'Administrador criado. Agora remova INSTALL_TOKEN do .env.');
    }

    private function page(Request $request, ?string $success = null, ?string $error = null): Response
    {
        $pending = [];
        $adminExists = false;
        $dbError = null;

        try {
            $pending = $this->migrator()->pending();
            $adminExists = $pending === [] && $this->adminExists();
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        return Response::html(View::render('install', [
            'title'       => 'Instalação — LiftEat',
            'token'       => (string) $request->input('token', ''),
            'pending'     => $pending,
            'adminExists' => $adminExists,
            'dbError'     => $dbError,
            'success'     => $success,
            'error'       => $error,
        ]))->withHeader('X-Robots-Tag', 'noindex');
    }

    private function authorized(Request $request): bool
    {
        $expected = (string) Env::get('INSTALL_TOKEN', '');
        $given = (string) $request->input('token', '');

        return strlen($expected) >= 16 && hash_equals($expected, $given);
    }

    private function adminExists(): bool
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    }

    private function migrator(): Migrator
    {
        return new Migrator(Database::connection(), BASE_PATH . '/database/migrations');
    }

    private function notFound(): Response
    {
        return Response::html('<h1>404</h1><p>Página não encontrada.</p>', 404);
    }
}
