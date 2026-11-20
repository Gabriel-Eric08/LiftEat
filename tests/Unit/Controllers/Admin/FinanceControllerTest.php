<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Controllers\Admin\FinanceController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Filtros da URL usados pela tela do financeiro e pela exportação CSV.
 */
final class FinanceControllerTest extends TestCase
{
    public function testFiltrosValidosSaoMantidos(): void
    {
        [$month, $status] = FinanceController::parseFilters('2026-03', 'pending', '2026-11');

        self::assertSame('2026-03-01', $month->format('Y-m-d'));
        self::assertSame('pending', $status);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function filtrosInvalidos(): iterable
    {
        yield 'ausentes' => [null, null];
        yield 'mês 13' => ['2026-13', 'qualquer'];
        yield 'formato errado' => ['11/2026', "paid' OR 1=1"];
        yield 'array na URL' => [['2026-03'], ['paid']];
    }

    #[DataProvider('filtrosInvalidos')]
    public function testFiltrosInvalidosVoltamParaOMesAtualETodos(mixed $month, mixed $status): void
    {
        [$parsedMonth, $parsedStatus] = FinanceController::parseFilters($month, $status, '2026-11');

        self::assertSame('2026-11-01', $parsedMonth->format('Y-m-d'));
        self::assertSame('todos', $parsedStatus);
    }
}
