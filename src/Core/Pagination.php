<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Conta de paginação: página pedida (já corrigida), deslocamento e total de páginas.
 * Página fora do intervalo vai para a primeira ou para a última.
 */
final readonly class Pagination
{
    public int $page;
    public int $pages;
    public int $offset;

    public function __construct(mixed $requestedPage, public int $total, public int $perPage)
    {
        $this->pages = max(1, (int) ceil(max(0, $total) / max(1, $perPage)));

        $page = is_numeric($requestedPage) ? (int) $requestedPage : 1;
        $this->page = min(max(1, $page), $this->pages);
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }
}
