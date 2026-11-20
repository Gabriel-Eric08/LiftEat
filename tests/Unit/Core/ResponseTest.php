<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDownloadDefineCabecalhosDeAnexo(): void
    {
        $response = Response::download('a;b', 'financeiro-2026-11.csv', 'text/csv; charset=UTF-8');

        self::assertSame(200, $response->status());
        self::assertSame('a;b', $response->body());
        self::assertSame('text/csv; charset=UTF-8', $response->header('content-type'));
        self::assertSame('attachment; filename="financeiro-2026-11.csv"', $response->header('Content-Disposition'));
        self::assertSame('no-store', $response->header('Cache-Control'));
    }

    public function testDownloadLimpaCaracteresPerigososDoNomeDoArquivo(): void
    {
        $response = Response::download('', "x\"; evil\r\n.csv", 'text/csv');

        self::assertSame('attachment; filename="x___evil__.csv"', $response->header('Content-Disposition'));
    }

    public function testHeaderInexistenteRetornaNull(): void
    {
        self::assertNull(Response::html('ok')->header('X-Nada'));
    }
}
