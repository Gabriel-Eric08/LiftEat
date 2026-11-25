<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BodyMeasurements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Peso e medidas: validação, variação desde o primeiro registro e peso mais recente.
 */
final class BodyMeasurementsTest extends TestCase
{
    private const TODAY = '2026-11-25';

    /* ---------------- validate() ---------------- */

    public function testRegistroValidoComDecimalBrasileiro(): void
    {
        [$data, $errors] = BodyMeasurements::validate([
            'measured_on' => '2026-11-20', 'weight_kg' => '72,45', 'waist_cm' => '80', 'notes' => '  em jejum ',
        ], self::TODAY);

        self::assertSame([], $errors);
        self::assertSame('2026-11-20', $data['measured_on']);
        self::assertSame(72.5, $data['weight_kg']);
        self::assertSame(80.0, $data['waist_cm']);
        self::assertNull($data['arm_cm']);
        self::assertSame('em jejum', $data['notes']);
    }

    public function testHojeEhAceito(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => self::TODAY, 'weight_kg' => '70'], self::TODAY);

        self::assertSame([], $errors);
    }

    public function testDataFuturaEhRecusada(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => '2026-11-26', 'weight_kg' => '70'], self::TODAY);

        self::assertSame(['A data não pode ser no futuro.'], $errors);
    }

    /** @return iterable<string, array{mixed}> */
    public static function datasInvalidas(): iterable
    {
        yield 'vazia' => [''];
        yield 'formato BR' => ['25/11/2026'];
        yield '31 de fevereiro' => ['2026-02-31'];
        yield 'array' => [['2026-11-20']];
    }

    #[DataProvider('datasInvalidas')]
    public function testDataInvalida(mixed $date): void
    {
        [$data, $errors] = BodyMeasurements::validate(['measured_on' => $date, 'weight_kg' => '70'], self::TODAY);

        self::assertSame(['Informe uma data válida.'], $errors);
        self::assertNull($data['measured_on']);
    }

    public function testDataMuitoAntiga(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => '1999-12-31', 'weight_kg' => '70'], self::TODAY);

        self::assertSame(['Data muito antiga.'], $errors);
    }

    public function testPrecisaDePeloMenosUmaMedida(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => self::TODAY, 'notes' => 'só nota'], self::TODAY);

        self::assertSame(['Preencha pelo menos o peso ou uma medida.'], $errors);
    }

    /** @return iterable<string, array{string, string}> */
    public static function foraDosLimites(): iterable
    {
        yield 'peso baixo' => ['weight_kg', '19,9'];
        yield 'peso alto' => ['weight_kg', '400,1'];
        yield 'gordura alta' => ['body_fat_pct', '71'];
        yield 'braço baixo' => ['arm_cm', '9'];
        yield 'cintura alta' => ['waist_cm', '251'];
    }

    #[DataProvider('foraDosLimites')]
    public function testMedidaForaDoLimite(string $field, string $value): void
    {
        [$data, $errors] = BodyMeasurements::validate(['measured_on' => self::TODAY, $field => $value], self::TODAY);

        self::assertCount(1, $errors);
        self::assertStringContainsString(BodyMeasurements::FIELDS[$field][0], $errors[0]);
        self::assertNull($data[$field]);
    }

    public function testLimitesSaoInclusivos(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => self::TODAY, 'weight_kg' => '20', 'body_fat_pct' => '70'], self::TODAY);

        self::assertSame([], $errors);
    }

    public function testTextoNoLugarDeNumero(): void
    {
        [, $errors] = BodyMeasurements::validate(['measured_on' => self::TODAY, 'weight_kg' => 'setenta'], self::TODAY);

        self::assertSame(['Peso: informe um número (ex.: 72,5).'], $errors);
    }

    public function testObservacaoLonga(): void
    {
        [, $errors] = BodyMeasurements::validate([
            'measured_on' => self::TODAY, 'weight_kg' => '70', 'notes' => str_repeat('a', BodyMeasurements::NOTES_MAX + 1),
        ], self::TODAY);

        self::assertCount(1, $errors);
    }

    /* ---------------- variation() ---------------- */

    /** @return array<string, mixed> */
    private static function rec(string $date, array $values): array
    {
        return ['measured_on' => $date, ...array_fill_keys(array_keys(BodyMeasurements::FIELDS), null), ...$values];
    }

    public function testVariacaoDesdeOPrimeiroRegistroEmQualquerOrdem(): void
    {
        $records = [
            self::rec('2026-11-20', ['weight_kg' => '71.00']),
            self::rec('2026-10-01', ['weight_kg' => '75.50', 'waist_cm' => '88.0']),
            self::rec('2026-11-01', ['weight_kg' => '73.20', 'waist_cm' => '85.5']),
        ];

        $v = BodyMeasurements::variation($records);

        self::assertSame(['first' => 75.5, 'last' => 71.0, 'delta' => -4.5, 'firstDate' => '2026-10-01', 'lastDate' => '2026-11-20', 'count' => 3], $v['weight_kg']);
        // Cintura: último registro com cintura é 01/11, não 20/11.
        self::assertSame(-2.5, $v['waist_cm']['delta']);
        self::assertSame('2026-11-01', $v['waist_cm']['lastDate']);
        self::assertArrayNotHasKey('arm_cm', $v);
    }

    public function testVariacaoComUmRegistroEhZero(): void
    {
        $v = BodyMeasurements::variation([self::rec(self::TODAY, ['weight_kg' => '70'])]);

        self::assertSame(0.0, $v['weight_kg']['delta']);
        self::assertSame(1, $v['weight_kg']['count']);
    }

    public function testVariacaoSemRegistros(): void
    {
        self::assertSame([], BodyMeasurements::variation([]));
    }

    /* ---------------- latestWeight() ---------------- */

    public function testPesoMaisRecentePelaDataENaoPelaOrdem(): void
    {
        $records = [
            self::rec('2026-11-01', ['weight_kg' => '73']),
            self::rec('2026-11-20', ['waist_cm' => '80']), // mais recente, mas sem peso
            self::rec('2026-11-10', ['weight_kg' => '72.4']),
        ];

        self::assertSame(72.4, BodyMeasurements::latestWeight($records));
    }

    public function testSemPesoRegistradoNaoMudaOCadastro(): void
    {
        self::assertNull(BodyMeasurements::latestWeight([self::rec(self::TODAY, ['waist_cm' => '80'])]));
        self::assertNull(BodyMeasurements::latestWeight([]));
    }

    /* ---------------- format() ---------------- */

    public function testFormatacao(): void
    {
        self::assertSame('72,5 kg', BodyMeasurements::format('weight_kg', '72.50'));
        self::assertSame('80 cm', BodyMeasurements::format('waist_cm', 80.0));
        self::assertSame('−4,5 kg', BodyMeasurements::format('weight_kg', -4.5, true));
        self::assertSame('+1 %', BodyMeasurements::format('body_fat_pct', 1.0, true));
        self::assertSame('0 kg', BodyMeasurements::format('weight_kg', 0.0, true));
        self::assertSame('—', BodyMeasurements::format('weight_kg', null));
    }
}
