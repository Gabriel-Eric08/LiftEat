<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProgressCharts;
use PHPUnit\Framework\TestCase;

/**
 * Gráficos de evolução: agregação dos pontos e geometria do SVG.
 */
final class ProgressChartsTest extends TestCase
{
    /* ---------------- weightSeries() ---------------- */

    public function testSerieDePesoOrdenaPorDataEIgnoraRegistrosSemPeso(): void
    {
        $series = ProgressCharts::weightSeries([
            ['measured_on' => '2026-11-20', 'weight_kg' => '71.00'],
            ['measured_on' => '2026-11-10', 'weight_kg' => null],
            ['measured_on' => '2026-10-01', 'weight_kg' => '75.50'],
        ]);

        self::assertSame([
            ['date' => '2026-10-01', 'value' => 75.5],
            ['date' => '2026-11-20', 'value' => 71.0],
        ], $series);
    }

    /* ---------------- loadSeries() ---------------- */

    /** @return array<string, mixed> */
    private static function set(int $session, string $at, ?string $load, ?int $reps): array
    {
        return ['session_id' => $session, 'started_at' => $at, 'load_kg' => $load, 'reps' => $reps];
    }

    public function testCargaMaximaPorTreinoEmOrdemCronologica(): void
    {
        $rows = [
            self::set(9, '2026-11-20 18:00:00', '40.00', 8),
            self::set(9, '2026-11-20 18:00:00', '45.00', 6),
            self::set(3, '2026-11-01 07:30:00', '40.00', 10),
            self::set(3, '2026-11-01 07:30:00', '35.00', 12),
        ];

        self::assertSame([
            ['date' => '2026-11-01', 'value' => 40.0, 'reps' => 10, 'session_id' => 3],
            ['date' => '2026-11-20', 'value' => 45.0, 'reps' => 6, 'session_id' => 9],
        ], ProgressCharts::loadSeries($rows));
    }

    public function testNoEmpateDeCargaFicaASerieComMaisRepeticoes(): void
    {
        $rows = [self::set(1, '2026-11-01 07:00:00', '50.00', 6), self::set(1, '2026-11-01 07:00:00', '50.00', 9)];

        self::assertSame(9, ProgressCharts::loadSeries($rows)[0]['reps']);
    }

    public function testSeriesSemCargaNaoContamETreinoSoComPesoDoCorpoSome(): void
    {
        $rows = [
            self::set(1, '2026-11-01 07:00:00', null, 15),
            self::set(2, '2026-11-03 07:00:00', null, 12),
            self::set(2, '2026-11-03 07:00:00', '10.00', 10),
        ];

        $series = ProgressCharts::loadSeries($rows);

        self::assertCount(1, $series);
        self::assertSame(2, $series[0]['session_id']);
    }

    public function testDoisTreinosNoMesmoDiaViramDoisPontos(): void
    {
        $rows = [self::set(5, '2026-11-01 18:00:00', '30.00', 10), self::set(4, '2026-11-01 07:00:00', '25.00', 10)];

        self::assertSame([4, 5], array_column(ProgressCharts::loadSeries($rows), 'session_id'));
    }

    /* ---------------- geometry() ---------------- */

    public function testMenosDeDoisPontosNaoTemGrafico(): void
    {
        self::assertNull(ProgressCharts::geometry([]));
        self::assertNull(ProgressCharts::geometry([['date' => '2026-11-01', 'value' => 70.0]]));
    }

    public function testEixoXProporcionalAoTempo(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-11-01', 'value' => 70.0],
            ['date' => '2026-11-02', 'value' => 71.0],
            ['date' => '2026-11-11', 'value' => 72.0],
        ]);

        [$a, $b, $c] = array_column($g['dots'], 'x');
        // 1 dia de 10 entre o 1º e o 2º ponto: ~10% da largura útil.
        self::assertEqualsWithDelta(0.1, ($b - $a) / ($c - $a), 0.01);
    }

    public function testValorMaiorFicaMaisAltoNoSvg(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-11-01', 'value' => 80.0],
            ['date' => '2026-11-10', 'value' => 70.0],
        ]);

        self::assertLessThan($g['dots'][1]['y'], $g['dots'][0]['y']);
        self::assertSame(70.0, $g['min']);
        self::assertSame(80.0, $g['max']);
    }

    public function testPontosFicamDentroDaArea(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-01-01', 'value' => 0.5],
            ['date' => '2026-06-01', 'value' => 400.0],
        ], 600, 220);

        foreach ($g['dots'] as $d) {
            self::assertGreaterThan(0, $d['x']);
            self::assertLessThan(600, $d['x']);
            self::assertGreaterThan(0, $d['y']);
            self::assertLessThan(220, $d['y']);
        }
    }

    public function testValoresIguaisNaoDividemPorZero(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-11-01', 'value' => 70.0],
            ['date' => '2026-11-08', 'value' => 70.0],
        ]);

        self::assertSame($g['dots'][0]['y'], $g['dots'][1]['y']);
        self::assertCount(1, $g['ticks']);
    }

    public function testMesmaDataDistribuiOsPontos(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-11-01', 'value' => 30.0],
            ['date' => '2026-11-01', 'value' => 35.0],
        ]);

        self::assertNotSame($g['dots'][0]['x'], $g['dots'][1]['x']);
    }

    public function testCaminhoPassaPorTodosOsPontos(): void
    {
        $g = ProgressCharts::geometry([
            ['date' => '2026-11-01', 'value' => 70.0],
            ['date' => '2026-11-05', 'value' => 72.0],
            ['date' => '2026-11-09', 'value' => 71.0],
        ]);

        self::assertStringStartsWith('M', $g['path']);
        self::assertSame(2, substr_count($g['path'], ' L'));
        self::assertStringEndsWith(' Z', $g['area']);
    }

    /* ---------------- pickExercise() ---------------- */

    public function testEscolheOExercicioPedidoOuOMaisFrequente(): void
    {
        $list = [['id' => 7, 'name' => 'Supino', 'sessions' => 9], ['id' => 3, 'name' => 'Agachamento', 'sessions' => 4]];

        self::assertSame(3, ProgressCharts::pickExercise($list, '3')['id']);
        self::assertSame(7, ProgressCharts::pickExercise($list, '999')['id']);
        self::assertSame(7, ProgressCharts::pickExercise($list, 'abc')['id']);
        self::assertSame(7, ProgressCharts::pickExercise($list, null)['id']);
        self::assertNull(ProgressCharts::pickExercise([], '3'));
    }
}
