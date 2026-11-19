<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logs simples em storage/logs (um arquivo por canal).
 */
final class Log
{
    /** Registra eventos de login: storage/logs/auth.log */
    public static function auth(string $guard, string $email, string $ip, string $event): void
    {
        self::write('auth', sprintf('%s | %-7s | %s | %s | %s', date('Y-m-d H:i:s'), $guard, self::mask($email), $ip, $event));
    }

    public static function write(string $channel, string $line): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($dir . '/' . $channel . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** "gabriel@gmail.com" -> "ga*****@gmail.com" (o log não guarda e-mails completos). */
    private static function mask(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email === '' ? '(vazio)' : mb_substr($email, 0, 2) . '***';
        }

        [$user, $domain] = explode('@', $email, 2);

        return mb_substr($user, 0, 2) . str_repeat('*', max(1, mb_strlen($user) - 2)) . '@' . $domain;
    }
}
