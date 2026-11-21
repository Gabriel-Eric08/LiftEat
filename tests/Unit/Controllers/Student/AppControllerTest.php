<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Student;

use App\Controllers\Student\AppController;
use PHPUnit\Framework\TestCase;

/**
 * Agrupamento por mês do histórico de treinos do aluno.
 */
final class AppControllerTest extends TestCase
{
    public function testAgrupaPorMesMantendoAOrdem(): void
    {
        $sessions = [
            ['id' => 5, 'started_at' => '2026-11-17 10:00:00'],
            ['id' => 4, 'started_at' => '2026-11-03 07:30:00'],
            ['id' => 3, 'started_at' => '2026-10-28 18:00:00'],
            ['id' => 2, 'started_at' => '2025-12-31 23:59:00'],
        ];

        $grouped = AppController::groupByMonth($sessions);

        self::assertSame(['2026-11', '2026-10', '2025-12'], array_keys($grouped));
        self::assertSame([5, 4], array_column($grouped['2026-11'], 'id'));
        self::assertSame([3], array_column($grouped['2026-10'], 'id'));
    }

    public function testListaVaziaNaoTemGrupos(): void
    {
        self::assertSame([], AppController::groupByMonth([]));
    }
}
