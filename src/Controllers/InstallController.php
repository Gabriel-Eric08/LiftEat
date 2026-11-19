<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\DemoSeeder;
use Throwable;

/**
 * Instalação/manutenção do banco pela web (a hospedagem não tem SSH).
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
        return $this->action($request, function (): string {
            $done = $this->migrator()->migrate();

            return $done === [] ? 'Nenhuma migração pendente.' : 'Aplicadas: ' . implode(', ', $done);
        });
    }

    public function createAdmin(Request $request): Response
    {
        return $this->action($request, function () use ($request): string {
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
            if ($errors === [] && Database::select('SELECT id FROM admins WHERE email = ?', [$email]) !== []) {
                $errors[] = 'Já existe um administrador com esse e-mail.';
            }
            if ($errors !== []) {
                throw new \InvalidArgumentException(implode(' ', $errors));
            }

            Database::execute(
                'INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT)],
            );
            // Libera o e-mail caso tenha sido bloqueado por tentativas erradas.
            Database::execute('DELETE FROM login_attempts WHERE email = ?', [$email]);

            return "Administrador {$email} criado. Entre em /admin/login.";
        });
    }

    public function seed(Request $request): Response
    {
        return $this->action($request, function (): string {
            $r = DemoSeeder::seed();

            return sprintf(
                'Dados de teste inseridos: %d alunos, %d mensalidades e %d treinos realizados. Senha dos alunos: %s',
                $r['students'],
                $r['payments'],
                $r['sessions'],
                DemoSeeder::PASSWORD,
            );
        });
    }

    public function unseed(Request $request): Response
    {
        return $this->action($request, function (): string {
            $n = DemoSeeder::remove();

            return $n === 0 ? 'Não havia dados de teste.' : "Dados de teste removidos ({$n} alunos).";
        });
    }

    /** Executa uma ação protegida pelo token e mostra o resultado na página. */
    private function action(Request $request, callable $fn): Response
    {
        if (!$this->authorized($request)) {
            return $this->notFound();
        }

        try {
            return $this->page($request, success: $fn());
        } catch (Throwable $e) {
            return $this->page($request, error: $e->getMessage());
        }
    }

    private function page(Request $request, ?string $success = null, ?string $error = null): Response
    {
        $pending = [];
        $admins = [];
        $demo = false;
        $dbError = null;

        try {
            $pending = $this->migrator()->pending();
            if ($pending === []) {
                $admins = Database::select('SELECT name, email, last_login_at FROM admins ORDER BY id');
                $demo = DemoSeeder::exists();
            }
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        return Response::html(View::render('install', [
            'title'   => 'Instalação',
            'token'   => (string) $request->input('token', ''),
            'pending' => $pending,
            'admins'  => $admins,
            'demo'    => $demo,
            'dbError' => $dbError,
            'success' => $success,
            'error'   => $error,
        ], 'layouts/blank'))->withHeader('X-Robots-Tag', 'noindex');
    }

    private function authorized(Request $request): bool
    {
        $expected = (string) Env::get('INSTALL_TOKEN', '');
        $given = (string) $request->input('token', '');

        return strlen($expected) >= 16 && hash_equals($expected, $given);
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
