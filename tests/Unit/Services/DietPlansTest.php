<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DietPlans;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validação do plano alimentar e regra de "só um plano ativo por aluno".
 */
final class DietPlansTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function input(array $overrides = []): array
    {
        return [
            'name'             => 'Cutting dezembro',
            'notes'            => 'Beber 3 L de água por dia.',
            'target_kcal'      => '2200',
            'target_protein_g' => '160',
            'target_carbs_g'   => '220',
            'target_fat_g'     => '70',
            ...$overrides,
        ];
    }

    /* ---------------- validate() ---------------- */

    public function testDadosValidosSaemLimposEComMetasInteiras(): void
    {
        [$data, $errors] = DietPlans::validate(self::input(['name' => '  Cutting dezembro  ']));

        self::assertSame([], $errors);
        self::assertSame([
            'name'             => 'Cutting dezembro',
            'notes'            => 'Beber 3 L de água por dia.',
            'target_kcal'      => 2200,
            'target_protein_g' => 160,
            'target_carbs_g'   => 220,
            'target_fat_g'     => 70,
        ], $data);
    }

    public function testMetasEObservacoesSaoOpcionais(): void
    {
        [$data, $errors] = DietPlans::validate(['name' => 'Manutenção']);

        self::assertSame([], $errors);
        self::assertNull($data['notes']);
        foreach (array_keys(DietPlans::TARGETS) as $field) {
            self::assertNull($data[$field], $field);
        }
    }

    public function testNomeEhObrigatorio(): void
    {
        [, $errors] = DietPlans::validate(self::input(['name' => '   ']));

        self::assertSame(['Dê um nome ao plano.'], $errors);
    }

    public function testNomeAcimaDoLimite(): void
    {
        [, $errors] = DietPlans::validate(self::input(['name' => str_repeat('á', DietPlans::NAME_MAX + 1)]));

        self::assertCount(1, $errors);
        self::assertStringContainsString('120', $errors[0]);
    }

    public function testNomeNoLimiteContaCaracteresENaoBytes(): void
    {
        [, $errors] = DietPlans::validate(self::input(['name' => str_repeat('ç', DietPlans::NAME_MAX)]));

        self::assertSame([], $errors);
    }

    public function testObservacoesAcimaDoLimite(): void
    {
        [, $errors] = DietPlans::validate(self::input(['notes' => str_repeat('a', DietPlans::NOTES_MAX + 1)]));

        self::assertCount(1, $errors);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function metasInvalidas(): iterable
    {
        yield 'kcal negativa' => ['target_kcal', '-1'];
        yield 'kcal acima do máximo' => ['target_kcal', '10001'];
        yield 'proteína decimal' => ['target_protein_g', '150.5'];
        yield 'carboidrato com texto' => ['target_carbs_g', '200g'];
        yield 'gordura acima do máximo' => ['target_fat_g', '1001'];
    }

    #[DataProvider('metasInvalidas')]
    public function testMetaInvalidaGeraErroEFicaNula(string $field, string $value): void
    {
        [$data, $errors] = DietPlans::validate(self::input([$field => $value]));

        self::assertCount(1, $errors);
        self::assertStringContainsString(DietPlans::TARGETS[$field][0], $errors[0]);
        self::assertNull($data[$field]);
    }

    public function testArrayNaRequisicaoViraCampoVazioSemQuebrar(): void
    {
        [$data, $errors] = DietPlans::validate(self::input(['target_kcal' => ['2000'], 'name' => ['x']]));

        self::assertSame(['Dê um nome ao plano.'], $errors);
        self::assertNull($data['target_kcal']);
    }

    public function testMetaZeroEhAceita(): void
    {
        [$data, $errors] = DietPlans::validate(self::input(['target_carbs_g' => '0']));

        self::assertSame([], $errors);
        self::assertSame(0, $data['target_carbs_g']);
    }

    public function testVariosErrosSaoAcumulados(): void
    {
        [, $errors] = DietPlans::validate(self::input(['name' => '', 'target_kcal' => 'abc', 'target_fat_g' => '-5']));

        self::assertCount(3, $errors);
    }

    /* ---------------- regra do plano ativo ---------------- */

    public function testPrimeiroPlanoDoAlunoSempreFicaAtivo(): void
    {
        self::assertTrue(DietPlans::shouldActivate(false, false));
        self::assertTrue(DietPlans::shouldActivate(true, false));
    }

    public function testComPlanoAtivoSoAtivaSePedido(): void
    {
        self::assertFalse(DietPlans::shouldActivate(false, true));
        self::assertTrue(DietPlans::shouldActivate(true, true));
    }

    public function testAtivarDesativaTodosOsOutrosAtivos(): void
    {
        $plans = [
            ['id' => '1', 'is_active' => '1'],
            ['id' => '2', 'is_active' => '0'],
            ['id' => '3', 'is_active' => '1'], // estado inconsistente: dois ativos
            ['id' => '4', 'is_active' => '0'],
        ];

        self::assertSame([1, 3], DietPlans::idsToDeactivate($plans, 4));
    }

    public function testPlanoQueSeraAtivadoNaoEntraNaLista(): void
    {
        $plans = [['id' => 7, 'is_active' => 1], ['id' => 8, 'is_active' => 1]];

        self::assertSame([8], DietPlans::idsToDeactivate($plans, 7));
    }

    public function testNadaADesativarQuandoNaoHaOutroAtivo(): void
    {
        self::assertSame([], DietPlans::idsToDeactivate([], 1));
        self::assertSame([], DietPlans::idsToDeactivate([['id' => 2, 'is_active' => 0]], 1));
    }
}
