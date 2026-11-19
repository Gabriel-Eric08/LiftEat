<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sessão PHP com cookie seguro, mensagens flash e "old input" de formulários.
 */
final class Session
{
    private const LIFETIME = 60 * 60 * 24 * 30; // 30 dias: o aluno usa como app e não deve deslogar toda hora

    public static function start(bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $path = BASE_PATH . '/storage/sessions';
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }

        session_save_path($path);
        session_name('lifteat_session');
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Flash: o que foi gravado na requisição anterior fica disponível só nesta.
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    /** @param array<string, mixed> $input */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);
        self::flash('_old', $input);
    }

    /** @param array<string, string> $errors */
    public static function flashErrors(array $errors): void
    {
        self::flash('_errors', $errors);
    }
}
