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
    private const MAX_FAILS_PER_IP = 40; // alto: na hospedagem compartilhada vários acessos podem chegar pelo mesmo IP de proxy
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

        try {
            self::guardAgainstBruteForce($email, $ip);
        } catch (HttpException $e) {
            Log::auth($guard, $email, $ip, 'BLOQUEADO: muitas tentativas recentes');
            throw $e;
        }

        $table = $guard === 'admin' ? 'admins' : 'students';
        $extra = $guard === 'student' ? ' AND deleted_at IS NULL' : '';
        $user = Database::select("SELECT * FROM {$table} WHERE email = ?{$extra} LIMIT 1", [$email])[0] ?? null;

        // password_verify roda mesmo sem usuário, para não revelar quais e-mails existem pelo tempo de resposta.
        $hash = $user['password_hash'] ?? '$2y$12$xbipGey2W5WrvBUF1avxde78uCIrxkDE/u5Wor3g4RdR8eQVBN6jO';
        $ok = password_verify($password, $hash) && $user !== null;

        // Horário vem do PHP (não do DEFAULT do MySQL) para a janela de bloqueio usar um relógio só.
        Database::execute(
            'INSERT INTO login_attempts (email, ip_address, succeeded, attempted_at) VALUES (?, ?, ?, ?)',
            [$email, $ip, $ok ? 1 : 0, date('Y-m-d H:i:s')],
        );

        if (!$ok) {
            Log::auth($guard, $email, $ip, $user === null ? 'FALHOU: e-mail não cadastrado' : 'FALHOU: senha incorreta');

            return null;
        }

        Log::auth($guard, $email, $ip, 'OK: credenciais válidas, sessão ' . substr(hash('sha256', (string) session_id()), 0, 8));

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
        // Limite superior: ignora registros "no futuro" gravados quando o MySQL estava em outro fuso.
        $until = date('Y-m-d H:i:s', time() + 120);

        // Faxina ocasional: tentativas com mais de 2 dias não servem para nada.
        if (random_int(1, 20) === 1) {
            Database::execute('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 172800)]);
        }

        // Só falhas desde o último login bem-sucedido daquele e-mail contam.
        $row = Database::select(
            'SELECT
                COALESCE(SUM(email = ?), 0)      AS by_email,
                COALESCE(SUM(ip_address = ?), 0) AS by_ip
             FROM login_attempts
             WHERE succeeded = 0 AND attempted_at BETWEEN ? AND ? AND (email = ? OR ip_address = ?)
               AND attempted_at > COALESCE(
                   (SELECT MAX(ok.attempted_at) FROM login_attempts ok WHERE ok.email = ? AND ok.succeeded = 1 AND ok.attempted_at <= ?),
                   \'1970-01-01\'
               )',
            [$email, $ip, $since, $until, $email, $ip, $email, $until],
        )[0] ?? [];

        if ((int) ($row['by_email'] ?? 0) >= self::MAX_FAILS_PER_EMAIL || (int) ($row['by_ip'] ?? 0) >= self::MAX_FAILS_PER_IP) {
            throw new HttpException(429, sprintf(
                'Muitas tentativas de login. Aguarde %d minutos e tente novamente.',
                self::WINDOW_MINUTES,
            ));
        }
    }
}
