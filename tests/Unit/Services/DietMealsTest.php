<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DietMeals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validação e montagem das refeições do plano alimentar.
 */
final class DietMealsTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function meal(array $overrides = []): array
    {
        return [
            'name'      => 'Café da manhã',
            'meal_time' => '07:30',
            'notes'     => '',
            'options'   => [[
                'name'  => 'Opção 1',
                'notes' => '',
                'items' => [
                    ['food' => 'Ovo cozido', 'quantity' => '2 unidades', 'notes' => ''],
                    ['food' => 'Pão integral', 'quantity' => '2 fatias', 'notes' => 'sem manteiga'],
                ],
            ]],
            ...$overrides,
        ];
    }

    /* ---------------- validate() ---------------- */

    public function testArvoreValidaSaiLimpaNaMesmaOrdem(): void
    {
        [$meals, $errors] = DietMeals::validate([
            self::meal(),
            self::meal(['name' => '  Almoço ', 'meal_time' => '12:00']),
        ]);

        self::assertSame([], $errors);
        self::assertSame(['Café da manhã', 'Almoço'], array_column($meals, 'name'));
        self::assertSame('07:30:00', $meals[0]['meal_time']);
        self::assertNull($meals[0]['notes']);
        self::assertSame([
            ['food' => 'Ovo cozido', 'quantity' => '2 unidades', 'notes' => null],
            ['food' => 'Pão integral', 'quantity' => '2 fatias', 'notes' => 'sem manteiga'],
        ], $meals[0]['options'][0]['items']);
    }

    public function testListaVaziaEhValida(): void
    {
        self::assertSame([[], []], DietMeals::validate([]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function corposInvalidos(): iterable
    {
        yield 'null (JSON inválido)' => [null];
        yield 'texto' => ['refeições'];
        yield 'número' => [42];
    }

    #[DataProvider('corposInvalidos')]
    public function testCorpoQueNaoEhListaGeraErro(mixed $raw): void
    {
        [$meals, $errors] = DietMeals::validate($raw);

        self::assertSame([], $meals);
        self::assertCount(1, $errors);
    }

    public function testNomeDaRefeicaoEhObrigatorio(): void
    {
        [, $errors] = DietMeals::validate([self::meal(['name' => ' '])]);

        self::assertSame(['Refeição 1: dê um nome (ex.: Café da manhã).'], $errors);
    }

    public function testHorarioEhOpcional(): void
    {
        [$meals, $errors] = DietMeals::validate([self::meal(['meal_time' => ''])]);

        self::assertSame([], $errors);
        self::assertNull($meals[0]['meal_time']);
    }

    public function testHorarioInvalidoUsaONomeDaRefeicaoNoErro(): void
    {
        [, $errors] = DietMeals::validate([self::meal(['meal_time' => '25:00'])]);

        self::assertSame(['Café da manhã: horário inválido (use HH:MM).'], $errors);
    }

    public function testRefeicaoSemOpcaoGanhaUmaOpcaoPadrao(): void
    {
        [$meals, $errors] = DietMeals::validate([self::meal(['options' => []])]);

        self::assertSame([], $errors);
        self::assertSame([['name' => 'Opção 1', 'notes' => null, 'items' => []]], $meals[0]['options']);
    }

    public function testOpcaoSemNomeRecebeNomePelaPosicao(): void
    {
        [$meals] = DietMeals::validate([self::meal(['options' => [
            ['name' => 'Doce', 'items' => []],
            ['name' => '', 'items' => []],
        ]])]);

        self::assertSame(['Doce', 'Opção 2'], array_column($meals[0]['options'], 'name'));
    }

    public function testLinhaDeAlimentoTotalmenteVaziaEhIgnorada(): void
    {
        [$meals, $errors] = DietMeals::validate([self::meal(['options' => [['items' => [
            ['food' => '', 'quantity' => '', 'notes' => ''],
            ['food' => 'Banana', 'quantity' => '1 unidade'],
        ]]]])]);

        self::assertSame([], $errors);
        self::assertSame(['Banana'], array_column($meals[0]['options'][0]['items'], 'food'));
    }

    public function testAlimentoSemQuantidadeGeraErroComONomeDoAlimento(): void
    {
        [, $errors] = DietMeals::validate([self::meal(['options' => [['items' => [
            ['food' => 'Arroz', 'quantity' => ''],
        ]]]])]);

        self::assertSame(['Café da manhã: informe a quantidade de Arroz (ex.: 100 g).'], $errors);
    }

    public function testQuantidadeSemAlimentoGeraErro(): void
    {
        [, $errors] = DietMeals::validate([self::meal(['options' => [['items' => [
            ['food' => '', 'quantity' => '100 g'],
        ]]]])]);

        self::assertSame(['Café da manhã: informe o alimento do item 1.'], $errors);
    }

    public function testErroEmOpcaoCitaRefeicaoEOpcaoQuandoHaMaisDeUma(): void
    {
        [, $errors] = DietMeals::validate([self::meal(['options' => [
            ['name' => 'Salgada', 'items' => [['food' => 'Pão', 'quantity' => '1']]],
            ['name' => 'Doce', 'items' => [['food' => 'Aveia', 'quantity' => '']]],
        ]])]);

        self::assertSame(['Café da manhã, Doce: informe a quantidade de Aveia (ex.: 100 g).'], $errors);
    }

    public function testLimitesDeTamanhoDosTextos(): void
    {
        [, $errors] = DietMeals::validate([self::meal([
            'name'    => str_repeat('a', DietMeals::MEAL_NAME_MAX + 1),
            'options' => [['items' => [[
                'food'     => str_repeat('b', DietMeals::FOOD_MAX + 1),
                'quantity' => str_repeat('c', DietMeals::QUANTITY_MAX + 1),
                'notes'    => str_repeat('d', DietMeals::NOTES_MAX + 1),
            ]]]],
        ])]);

        self::assertCount(4, $errors);
    }

    public function testTextosNoLimiteContamCaracteresENaoBytes(): void
    {
        [, $errors] = DietMeals::validate([self::meal([
            'name'    => str_repeat('ç', DietMeals::MEAL_NAME_MAX),
            'options' => [['items' => [['food' => str_repeat('ã', DietMeals::FOOD_MAX), 'quantity' => '1']]]],
        ])]);

        self::assertSame([], $errors);
    }

    public function testMaximoDeRefeicoes(): void
    {
        $raw = array_map(static fn ($n) => self::meal(['name' => "Refeição {$n}"]), range(1, DietMeals::MAX_MEALS + 1));
        [$meals, $errors] = DietMeals::validate($raw);

        self::assertCount(DietMeals::MAX_MEALS, $meals);
        self::assertSame(['O plano pode ter no máximo ' . DietMeals::MAX_MEALS . ' refeições.'], $errors);
    }

    public function testNomesDeRefeicaoRepetidosSaoRecusados(): void
    {
        [, $errors] = DietMeals::validate([
            self::meal(['name' => 'Lanche']),
            self::meal(['name' => 'Almoço']),
            self::meal(['name' => '  lanche ']),
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('duas refeições chamadas lanche', $errors[0]);
    }

    public function testNomesParecidosMasDiferentesSaoAceitos(): void
    {
        [, $errors] = DietMeals::validate([
            self::meal(['name' => 'Lanche da manhã']),
            self::meal(['name' => 'Lanche da tarde']),
        ]);

        self::assertSame([], $errors);
    }

    public function testMaximoDeOpcoesEAlimentos(): void
    {
        $items = array_fill(0, DietMeals::MAX_ITEMS + 1, ['food' => 'Arroz', 'quantity' => '100 g']);
        $options = array_fill(0, DietMeals::MAX_OPTIONS + 1, ['name' => 'X', 'items' => $items]);
        [$meals, $errors] = DietMeals::validate([self::meal(['options' => $options])]);

        self::assertCount(DietMeals::MAX_OPTIONS, $meals[0]['options']);
        self::assertCount(DietMeals::MAX_ITEMS, $meals[0]['options'][0]['items']);
        self::assertContains('Café da manhã: no máximo ' . DietMeals::MAX_OPTIONS . ' opções.', $errors);
        // Mesma mensagem para todas as opções, sem repetir.
        self::assertCount(2, $errors);
    }

    public function testValoresQueNaoSaoTextoNaoQuebram(): void
    {
        [$meals, $errors] = DietMeals::validate([
            ['name' => ['x'], 'meal_time' => ['7'], 'options' => 'nada'],
            'não é refeição',
        ]);

        self::assertCount(2, $meals);
        self::assertSame(['Refeição 1: dê um nome (ex.: Café da manhã).', 'Refeição 2: dê um nome (ex.: Café da manhã).'], $errors);
    }

    /* ---------------- time() ---------------- */

    /** @return iterable<string, array{string, ?string}> */
    public static function horarios(): iterable
    {
        yield 'HH:MM' => ['07:30', '07:30:00'];
        yield 'H:MM' => ['7:05', '07:05:00'];
        yield 'com segundos zerados' => ['21:00:00', '21:00:00'];
        yield 'meia-noite' => ['00:00', '00:00:00'];
        yield 'hora 24' => ['24:00', null];
        yield 'minuto 60' => ['10:60', null];
        yield 'sem dois pontos' => ['0730', null];
        yield 'texto' => ['manhã', null];
        yield 'segundos não zerados' => ['07:30:15', null];
    }

    #[DataProvider('horarios')]
    public function testHorario(string $input, ?string $expected): void
    {
        self::assertSame($expected, DietMeals::time($input));
    }

    /* ---------------- assemble() ---------------- */

    public function testMontaArvoreMantendoAOrdemDasLinhas(): void
    {
        $meals = [['id' => '2', 'name' => 'Almoço'], ['id' => '1', 'name' => 'Café']];
        $options = [
            ['id' => '10', 'meal_id' => '1', 'name' => 'A'],
            ['id' => '11', 'meal_id' => '2', 'name' => 'B'],
            ['id' => '12', 'meal_id' => '2', 'name' => 'C'],
        ];
        $items = [
            ['id' => '100', 'option_id' => '12', 'food' => 'Arroz'],
            ['id' => '101', 'option_id' => '11', 'food' => 'Feijão'],
            ['id' => '102', 'option_id' => '12', 'food' => 'Frango'],
        ];

        $tree = DietMeals::assemble($meals, $options, $items);

        self::assertSame(['Almoço', 'Café'], array_column($tree, 'name'));
        self::assertSame(['B', 'C'], array_column($tree[0]['options'], 'name'));
        self::assertSame(['Arroz', 'Frango'], array_column($tree[0]['options'][1]['items'], 'food'));
        self::assertSame([], $tree[1]['options'][0]['items']);
    }

    public function testRefeicaoSemOpcoesFicaComListaVazia(): void
    {
        $tree = DietMeals::assemble([['id' => 1, 'name' => 'Ceia']], [], []);

        self::assertSame([], $tree[0]['options']);
    }

    /* ---------------- toInput() (cópia de plano) ---------------- */

    public function testArvoreDoBancoVoltaIdenticaDepoisDeValidar(): void
    {
        $tree = DietMeals::assemble(
            [
                ['id' => 1, 'name' => 'Café da manhã', 'meal_time' => '07:30:00', 'notes' => null],
                ['id' => 2, 'name' => 'Ceia', 'meal_time' => null, 'notes' => 'opcional'],
            ],
            [
                ['id' => 10, 'meal_id' => 1, 'name' => 'Salgada', 'notes' => null],
                ['id' => 11, 'meal_id' => 1, 'name' => 'Doce', 'notes' => 'fim de semana'],
                ['id' => 12, 'meal_id' => 2, 'name' => 'Opção 1', 'notes' => null],
            ],
            [
                ['id' => 100, 'option_id' => 10, 'food' => 'Ovo', 'quantity' => '2 unidades', 'notes' => null],
                ['id' => 101, 'option_id' => 11, 'food' => 'Aveia', 'quantity' => '40 g', 'notes' => 'com mel'],
            ],
        );

        [$meals, $errors] = DietMeals::validate(DietMeals::toInput($tree));

        self::assertSame([], $errors);
        self::assertSame([
            [
                'name' => 'Café da manhã', 'meal_time' => '07:30:00', 'notes' => null,
                'options' => [
                    ['name' => 'Salgada', 'notes' => null, 'items' => [['food' => 'Ovo', 'quantity' => '2 unidades', 'notes' => null]]],
                    ['name' => 'Doce', 'notes' => 'fim de semana', 'items' => [['food' => 'Aveia', 'quantity' => '40 g', 'notes' => 'com mel']]],
                ],
            ],
            [
                'name' => 'Ceia', 'meal_time' => null, 'notes' => 'opcional',
                'options' => [['name' => 'Opção 1', 'notes' => null, 'items' => []]],
            ],
        ], $meals);
    }

    public function testPlanoSemRefeicoesViraListaVazia(): void
    {
        self::assertSame([], DietMeals::toInput([]));
    }
}
