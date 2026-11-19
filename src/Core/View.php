<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderiza templates PHP de /views. Use e() para escapar saída.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);

        return $layout === null
            ? $content
            : self::capture($layout, [...$data, 'content' => $content]);
    }

    /**
     * Renderiza um trecho reutilizável (views/partials/...).
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View não encontrada: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
