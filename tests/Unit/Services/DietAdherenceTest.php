<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DietAdherence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cálculo da adesão à dieta nos últimos 7 dias.
 */
final class DietAdherenceTest extends TestCase
{
    private const TODAY = '2026-11-25';

    /** @return array<string, mixed> */
    private static function log(string $date, string $meal, int $planCount): array
    {
        return ['log_date' => $date, 'meal_key' => $meal, 'plan_meal_count' => $planCount];
    }

    /** @return array<string, array{date: string, done: int, total: int, percent: ?int}> */
    private static function byDate(array $days): array
    {
        return array_column($days, null, 'date');
    }

    /* ---------------- days() ---------------- */

    public function testDevolveSeteDiasDoMaisAntigoParaHoje(): void
    {
        $days = DietAdherence::days([], self::TODAY, 4, '2026-01-01');

        self::assertCount(7, $days);
        self::assertSame('2026-11-19', $days[0]['date']);
        self::assertSame(self::TODAY, $days[6]['date']);
    }

    public function testDiaComCheckinsUsaOTamanhoDoPlanoGravado(): void
    {
        $logs = [
            self::log('2026-11-24', 'café', 5),
            self::log('2026-11-24', 'almoço', 5),
        ];

        $day = self::byDate(DietAdherence::days($logs, self::TODAY, 4, '2026-01-01'))['2026-11-24'];

        self::assertSame(['date' => '2026-11-24', 'done' => 2, 'total' => 5, 'percent' => 40], $day);
    }

    public function testDiaSemCheckinComPlanoContaZeroDoPlanoAtual(): void
    {
        $day = self::byDate(DietAdherence::days([], self::TODAY, 4, '2026-01-01'))['2026-11-23'];

        self::assertSame(['date' => '2026-11-23', 'done' => 0, 'total' => 4, 'percent' => 0], $day);
    }

    public function testDiasAntesDoPrimeiroPlanoFicamSemPlano(): void
    {
        $days = self::byDate(DietAdherence::days([], self::TODAY, 4, '2026-11-23'));

        self::assertNull($days['2026-11-22']['percent']);
        self::assertSame(0, $days['2026-11-23']['percent']);
    }

    public function testAlunoSemPlanoAtivoNaoTemDiasSemCheckinContados(): void
    {
        $days = DietAdherence::days([], self::TODAY, 0, '2026-01-01');

        self::assertSame([null], array_values(array_unique(array_column($days, 'percent'))));
    }

    public function testPlanoTrocadoNoMeioDaSemanaNaoMudaOPassado(): void
    {
        // Até dia 22 o plano tinha 6 refeições; o atual tem 3.
        $logs = [
            self::log('2026-11-21', 'café', 6),
            self::log('2026-11-21', 'almoço', 6),
            self::log('2026-11-21', 'jantar', 6),
            self::log('2026-11-24', 'café', 3),
            self::log('2026-11-24', 'almoço', 3),
            self::log('2026-11-24', 'jantar', 3),
        ];

        $days = self::byDate(DietAdherence::days($logs, self::TODAY, 3, '2026-01-01'));

        self::assertSame(50, $days['2026-11-21']['percent']);
        self::assertSame(100, $days['2026-11-24']['percent']);
    }

    public function testMesmoDiaComDoisPlanosUsaOMaior(): void
    {
        $logs = [self::log(self::TODAY, 'café', 4), self::log(self::TODAY, 'lanche', 5)];

        $day = self::byDate(DietAdherence::days($logs, self::TODAY, 5, '2026-01-01'))[self::TODAY];

        self::assertSame(5, $day['total']);
        self::assertSame(40, $day['percent']);
    }

    public function testNuncaPassaDeCemPorCento(): void
    {
        // Check-ins antigos sem plan_meal_count confiável (0).
        $logs = [self::log(self::TODAY, 'café', 0), self::log(self::TODAY, 'almoço', 0)];

        $day = self::byDate(DietAdherence::days($logs, self::TODAY, 5, '2026-01-01'))[self::TODAY];

        self::assertSame(['date' => self::TODAY, 'done' => 2, 'total' => 2, 'percent' => 100], $day);
    }

    public function testRefeicaoRepetidaNoDiaContaUmaVez(): void
    {
        $logs = [self::log(self::TODAY, 'café', 4), self::log(self::TODAY, 'café', 4)];

        self::assertSame(1, self::byDate(DietAdherence::days($logs, self::TODAY, 4, '2026-01-01'))[self::TODAY]['done']);
    }

    public function testCheckinsForaDoPeriodoSaoIgnorados(): void
    {
        $logs = [self::log('2026-11-10', 'café', 4)];

        self::assertSame(0, DietAdherence::summary(DietAdherence::days($logs, self::TODAY, 4, '2026-01-01'))['done']);
    }

    /* ---------------- summary() ---------------- */

    public function testResumoSomaSoDiasComPlano(): void
    {
        $days = [
            ['date' => 'a', 'done' => 0, 'total' => 0, 'percent' => null],
            ['date' => 'b', 'done' => 2, 'total' => 4, 'percent' => 50],
            ['date' => 'c', 'done' => 0, 'total' => 4, 'percent' => 0],
            ['date' => 'd', 'done' => 4, 'total' => 4, 'percent' => 100],
        ];

        self::assertSame(
            ['done' => 6, 'total' => 12, 'percent' => 50, 'daysWithCheckin' => 2, 'daysWithPlan' => 3],
            DietAdherence::summary($days),
        );
    }

    public function testResumoSemDiasComPlanoNaoTemPercentual(): void
    {
        $summary = DietAdherence::summary(DietAdherence::days([], self::TODAY, 0, null));

        self::assertNull($summary['percent']);
        self::assertSame(0, $summary['daysWithPlan']);
    }

    /* ---------------- ordenação ---------------- */

    /** @return iterable<string, array{mixed, string}> */
    public static function ordenacoes(): iterable
    {
        yield 'adesão' => ['adesao', 'adesao'];
        yield 'aluno' => ['aluno', 'aluno'];
        yield 'ausente' => [null, 'aluno'];
        yield 'desconhecida' => ['kcal', 'aluno'];
        yield 'array' => [['adesao'], 'aluno'];
    }

    #[DataProvider('ordenacoes')]
    public function testParseSort(mixed $input, string $expected): void
    {
        self::assertSame($expected, DietAdherence::parseSort($input));
    }

    public function testOrdenarPorAdesaoPoeAtivosComMenorAdesaoPrimeiro(): void
    {
        $row = static fn (int $id, string $name, int $active, ?int $percent) => [
            'id' => $id, 'student_name' => $name, 'is_active' => $active,
            'adherence' => $active ? ['percent' => $percent] : null,
        ];

        $rows = [
            $row(1, 'Bruno', 1, 80),
            $row(2, 'Ana', 0, null),
            $row(3, 'Carla', 1, null), // sem dado = 0%
            $row(4, 'Diego', 1, 20),
            $row(5, 'Alice', 1, 20),
        ];

        $sorted = DietAdherence::sortRows($rows, 'adesao');

        self::assertSame(['Carla', 'Alice', 'Diego', 'Bruno', 'Ana'], array_column($sorted, 'student_name'));
    }

    public function testOrdenarPorAlunoIgnoraMaiusculas(): void
    {
        $rows = [
            ['id' => 1, 'student_name' => 'bruno', 'is_active' => 1, 'adherence' => null],
            ['id' => 2, 'student_name' => 'Ana', 'is_active' => 0, 'adherence' => null],
        ];

        self::assertSame(['Ana', 'bruno'], array_column(DietAdherence::sortRows($rows, 'aluno'), 'student_name'));
    }
}
