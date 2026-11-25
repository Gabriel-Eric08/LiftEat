<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testPrimeiraPagina(): void
    {
        $p = new Pagination(null, 95, 30);

        self::assertSame(1, $p->page);
        self::assertSame(4, $p->pages);
        self::assertSame(0, $p->offset);
        self::assertFalse($p->hasPrevious());
        self::assertTrue($p->hasNext());
    }

    public function testPaginaDoMeio(): void
    {
        $p = new Pagination('2', 95, 30);

        self::assertSame(2, $p->page);
        self::assertSame(30, $p->offset);
        self::assertTrue($p->hasPrevious());
        self::assertTrue($p->hasNext());
    }

    public function testUltimaPaginaIncompleta(): void
    {
        $p = new Pagination(4, 95, 30);

        self::assertSame(90, $p->offset);
        self::assertTrue($p->hasPrevious());
        self::assertFalse($p->hasNext());
    }

    public function testTotalExatoNaoCriaPaginaVazia(): void
    {
        self::assertSame(2, (new Pagination(1, 60, 30))->pages);
    }

    public function testSemRegistrosTemUmaPagina(): void
    {
        $p = new Pagination(3, 0, 30);

        self::assertSame(1, $p->pages);
        self::assertSame(1, $p->page);
        self::assertSame(0, $p->offset);
        self::assertFalse($p->hasNext());
    }

    /** @return iterable<string, array{mixed, int}> */
    public static function paginasForaDoIntervalo(): iterable
    {
        yield 'zero' => [0, 1];
        yield 'negativa' => ['-5', 1];
        yield 'texto' => ['abc', 1];
        yield 'array na URL' => [['2'], 1];
        yield 'além da última' => ['99', 4];
    }

    #[DataProvider('paginasForaDoIntervalo')]
    public function testPaginaForaDoIntervaloEhCorrigida(mixed $requested, int $expected): void
    {
        $p = new Pagination($requested, 95, 30);

        self::assertSame($expected, $p->page);
        self::assertSame(($expected - 1) * 30, $p->offset);
    }
}
