<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DietCheckins;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Check-in da dieta: validação contra o plano, progresso do dia e próxima refeição.
 */
final class DietCheckinsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function plan(): array
    {
        $opt = static fn (string $name) => ['name' => $name, 'items' => []];

        return [
            ['name' => 'Jantar', 'meal_time' => '20:00:00', 'options' => [$opt('Opção 1')]],
            ['name' => 'Café da manhã', 'meal_time' => '07:30:00', 'options' => [$opt('Salgada'), $opt('Doce')]],
            ['name' => 'Almoço', 'meal_time' => '12:00:00', 'options' => [$opt('Opção 1')]],
            ['name' => 'Ceia', 'meal_time' => null, 'options' => []],
        ];
    }

    /* ---------------- key() ---------------- */

    public function testChaveIgnoraMaiusculasEEspacos(): void
    {
        self::assertSame('café da manhã', DietCheckins::key("  Café   DA\tManhã "));
    }

    /* ---------------- resolve() ---------------- */

    public function testRefeicaoComUmaOpcaoUsaEssaOpcaoMesmoSemEscolha(): void
    {
        self::assertSame(
            ['meal_key' => 'almoço', 'meal_name' => 'Almoço', 'option_name' => 'Opção 1'],
            DietCheckins::resolve(self::plan(), 'almoço', ''),
        );
    }

    public function testRefeicaoComVariasOpcoesExigeEscolha(): void
    {
        $result = DietCheckins::resolve(self::plan(), 'Café da manhã', '');

        self::assertSame('Escolha qual opção de Café da manhã você seguiu.', $result);
    }

    public function testOpcaoEscolhidaUsaONomeDoPlano(): void
    {
        self::assertSame(
            ['meal_key' => 'café da manhã', 'meal_name' => 'Café da manhã', 'option_name' => 'Doce'],
            DietCheckins::resolve(self::plan(), ' café da manhã', 'DOCE'),
        );
    }

    public function testOpcaoQueNaoExisteEhRecusada(): void
    {
        self::assertIsString(DietCheckins::resolve(self::plan(), 'Café da manhã', 'Vegana'));
    }

    public function testRefeicaoForaDoPlanoEhRecusada(): void
    {
        self::assertSame(
            'Essa refeição não está no seu plano atual. Atualize a página.',
            DietCheckins::resolve(self::plan(), 'Lanche', 'Opção 1'),
        );
    }

    public function testRefeicaoSemOpcoesFicaSemOpcao(): void
    {
        $result = DietCheckins::resolve(self::plan(), 'Ceia', 'qualquer');

        self::assertIsArray($result);
        self::assertNull($result['option_name']);
    }

    public function testEntradaQueNaoEhTextoNaoQuebra(): void
    {
        self::assertIsString(DietCheckins::resolve(self::plan(), ['Almoço'], null));
        self::assertIsString(DietCheckins::resolve(self::plan(), '', ''));
        self::assertIsString(DietCheckins::resolve([], 'Almoço', ''));
    }

    /* ---------------- progress() ---------------- */

    public function testProgressoContaSoRefeicoesDoPlanoAtual(): void
    {
        $logs = [
            ['meal_key' => 'almoço', 'option_name' => 'Opção 1'],
            ['meal_key' => 'café da manhã', 'option_name' => 'Doce'],
            ['meal_key' => 'lanche antigo', 'option_name' => null], // refeição removida do plano
        ];

        $progress = DietCheckins::progress(self::plan(), $logs);

        self::assertSame(2, $progress['done']);
        self::assertSame(4, $progress['total']);
        self::assertSame(50, $progress['percent']);
        self::assertSame(['café da manhã' => 'Doce', 'almoço' => 'Opção 1'], $progress['byMeal']);
    }

    public function testProgressoSemCheckinsESemRefeicoes(): void
    {
        self::assertSame(['done' => 0, 'total' => 4, 'percent' => 0, 'byMeal' => []], DietCheckins::progress(self::plan(), []));
        self::assertSame(['done' => 0, 'total' => 0, 'percent' => 0, 'byMeal' => []], DietCheckins::progress([], []));
    }

    public function testProgressoArredondaPercentual(): void
    {
        $plan = array_slice(self::plan(), 0, 3);

        self::assertSame(33, DietCheckins::progress($plan, [['meal_key' => 'jantar', 'option_name' => null]])['percent']);
    }

    public function testCheckinDeRefeicaoSemOpcaoApareceComoNull(): void
    {
        $progress = DietCheckins::progress(self::plan(), [['meal_key' => 'ceia', 'option_name' => null]]);

        self::assertArrayHasKey('ceia', $progress['byMeal']);
        self::assertNull($progress['byMeal']['ceia']);
    }

    /* ---------------- nextMeal() ---------------- */

    public function testProximaRefeicaoEhAMaisCedoAPartirDeAgoraMesmoForaDeOrdem(): void
    {
        self::assertSame('Almoço', DietCheckins::nextMeal(self::plan(), '10:00:00')['name']);
        self::assertSame('Café da manhã', DietCheckins::nextMeal(self::plan(), '06:00:00')['name']);
    }

    public function testProximaRefeicaoPulaAsJaFeitas(): void
    {
        self::assertSame('Jantar', DietCheckins::nextMeal(self::plan(), '10:00:00', ['almoço'])['name']);
    }

    public function testDepoisDaUltimaRefeicaoMostraAPrimeiraPendenteDoPlano(): void
    {
        self::assertSame('Jantar', DietCheckins::nextMeal(self::plan(), '22:00:00')['name']);
        self::assertSame('Café da manhã', DietCheckins::nextMeal(self::plan(), '22:00:00', ['jantar'])['name']);
    }

    public function testSemRefeicoesPendentesNaoHaProxima(): void
    {
        self::assertNull(DietCheckins::nextMeal(self::plan(), '10:00:00', ['jantar', 'café da manhã', 'almoço', 'ceia']));
        self::assertNull(DietCheckins::nextMeal([], '10:00:00'));
    }

    public function testAncoraEhEstavelEIgnoraMaiusculas(): void
    {
        self::assertSame(DietCheckins::anchor('Café da manhã'), DietCheckins::anchor(' café  DA manhã'));
        self::assertNotSame(DietCheckins::anchor('Almoço'), DietCheckins::anchor('Jantar'));
        self::assertMatchesRegularExpression('/^refeicao-[0-9a-f]{8}$/', DietCheckins::anchor('Almoço'));
    }

    /* ---------------- tabela ausente ---------------- */

    public function testReconheceErroDeTabelaInexistente(): void
    {
        $missing = new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'x.diet_meal_logs' doesn't exist");
        $other = new PDOException('SQLSTATE[HY000] [2002] Connection refused');

        self::assertTrue(DietCheckins::isMissingTable($missing));
        self::assertFalse(DietCheckins::isMissingTable($other));
    }
}
