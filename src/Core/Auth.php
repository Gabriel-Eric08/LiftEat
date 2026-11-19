<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autenticação dos dois perfis: personal (admin) e aluno (student).
 * Cada um tem sua própria chave na sessão; podem coexistir no mesmo navegador.
 */
final class Auth
{
    private const MAX_FAILS_PER_EMAIL = 5;
    private const MAX_FAILS_PER_IP = 20;
    private const WINDOW_MINUTES = 15;

    /** @var array<string, array<string, mixed>|null> */
    private static array $cache = [];

    /**
     * @param 'admin'|'student' $guard
     * @return array<string, mixed>|null usuário autenticado, ou null se credenciais inválidas
     * @throws HttpException 429 se exceder o limite de tentativas
     */
    public static function attempt(string $guard, string $email, string $password, string $ip): ?array
    {
        $email = mb_strtolower(trim($email));
        self::guardAgainstBruteForce($email, $ip);

        $table = $guard === 'admin' ? 'admins' : 'students';
        $extra = $guard === 'student' ? ' AND deleted_at IS NULL' : '';
        $user = Database::select("SELECT * FROM {$table} WHERE email = ?{$extra} LIMIT 1", [$email])[0] ?? null;

        // password_verify roda mesmo sem usuário, para não revelar quais e-mails existem pelo tempo de resposta.
        $hash = $user['password_hash'] ?? '$2y$12$xbipGey2W5WrvBUF1avxde78uCIrxkDE/u5Wor3g4RdR8eQVBN6jO';
        $ok = password_verify($password, $hash) && $user !== null;

        Database::execute(
            'INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)',
            [$email, $ip, $ok ? 1 : 0],
        );

        if (!$ok) {
            return null;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute(
                "UPDATE {$table} SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $user['id']],
            );
        }

        Database::execute("UPDATE {$table} SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

        Session::regenerate();
        Session::put($guard . '_id', (int) $user['id']);
        unset(self::$cache[$guard]);

        return $user;
    }

    /** @param 'admin'|'student' $guard */
    public static function logout(string $guard): void
    {
        Session::forget($guard . '_id');
        unset(self::$cache[$guard]);
        Session::regenerate();
    }

    /** @return array<string, mixed>|null */
    public static function admin(): ?array
    {
        return self::user('admin');
    }

    /** @return array<string, mixed>|null */
    public static function student(): ?array
    {
        return self::user('student');
    }

    /**
     * Aluno pode usar o app: ativo, não excluído e com acesso pago até hoje ou depois.
     *
     * @param array<string, mixed> $student
     */
    public static function studentHasAccess(array $student): bool
    {
        return $student['status'] === 'active'
            && $student['deleted_at'] === null
            && $student['access_until'] !== null
            && $student['access_until'] >= date('Y-m-d');
    }

    /**
     * @param 'admin'|'student' $guard
     * @return array<string, mixed>|null
     */
    private static function user(string $guard): ?array
    {
        if (array_key_exists($guard, self::$cache)) {
            return self::$cache[$guard];
        }

        $id = Session::get($guard . '_id');
        $user = null;

        if (is_int($id)) {
            $table = $guard === 'admin' ? 'admins' : 'students';
            $extra = $guard === 'student' ? ' AND deleted_at IS NULL' : '';
            $user = Database::select("SELECT * FROM {$table} WHERE id = ?{$extra} LIMIT 1", [$id])[0] ?? null;

            if ($user === null) {
                Session::forget($guard . '_id');
            }
        }

        return self::$cache[$guard] = $user;
    }

    private static function guardAgainstBruteForce(string $email, string $ip): void
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);

        $row = Database::select(
            'SELECT
                COALESCE(SUM(email = ?), 0)      AS by_email,
                COALESCE(SUM(ip_address = ?), 0) AS by_ip
             FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= ? AND (email = ? OR ip_address = ?)',
            [$email, $ip, $since, $email, $ip],
        )[0] ?? [];

        if ((int) ($row['by_email'] ?? 0) >= self::MAX_FAILS_PER_EMAIL || (int) ($row['by_ip'] ?? 0) >= self::MAX_FAILS_PER_IP) {
            throw new HttpException(429, sprintf(
                'Muitas tentativas de login. Aguarde %d minutos e tente novamente.',
                self::WINDOW_MINUTES,
            ));
        }
    }
}
