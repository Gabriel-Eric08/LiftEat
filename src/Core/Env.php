<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Leitor mínimo de arquivo .env (CHAVE=valor, comentários com #).
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    /** O arquivo .env foi encontrado e lido? */
    public static function loaded(): bool
    {
        return self::$loaded;
    }

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        self::$loaded = true;
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM de editores Windows

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = preg_replace('/^export\s+/', '', $key) ?? $key;

            if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
                $value = $m[2];
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value) ?? '');
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $env = getenv($key);

        return $env === false ? $default : $env;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
