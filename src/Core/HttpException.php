<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Interrompe a requisição com um status HTTP; App transforma em página de erro.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
