<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Controllers\Admin\StudentController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ordenação da lista de alunos (?ordem=).
 */
final class StudentControllerTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function ordenacoesValidas(): iterable
    {
        yield 'nome' => ['nome'];
        yield 'vencimento' => ['vencimento'];
        yield 'último treino' => ['ultimo_treino'];
    }

    #[DataProvider('ordenacoesValidas')]
    public function testOrdenacaoValidaEhMantida(string $sort): void
    {
        self::assertSame($sort, StudentController::parseSort($sort));
    }

    /** @return iterable<string, array{mixed}> */
    public static function ordenacoesInvalidas(): iterable
    {
        yield 'ausente' => [null];
        yield 'vazia' => [''];
        yield 'desconhecida' => ['idade'];
        yield 'SQL na URL' => ['name; DROP TABLE students'];
        yield 'maiúsculas' => ['NOME'];
        yield 'array na URL' => [['vencimento']];
    }

    #[DataProvider('ordenacoesInvalidas')]
    public function testOrdenacaoInvalidaVoltaParaNome(mixed $sort): void
    {
        self::assertSame('nome', StudentController::parseSort($sort));
    }

    public function testOrderBySoUsaSqlDaListaFixa(): void
    {
        self::assertSame('s.name ASC, s.id ASC', StudentController::orderBy('nome'));
        self::assertSame('s.name ASC, s.id ASC', StudentController::orderBy("1; DELETE FROM students"));
    }

    public function testVencimentoColocaAlunosSemDataNoFim(): void
    {
        self::assertStringStartsWith('s.access_until IS NULL, s.access_until ASC', StudentController::orderBy('vencimento'));
    }

    public function testUltimoTreinoColocaQuemNuncaTreinouNoFimEMaisAntigoPrimeiro(): void
    {
        self::assertStringStartsWith('last_session_at IS NULL, last_session_at ASC', StudentController::orderBy('ultimo_treino'));
    }

    public function testTodaOrdenacaoTemDesempateEstavel(): void
    {
        foreach (array_keys(StudentController::SORTS) as $sort) {
            self::assertMatchesRegularExpression('/s\.(name|id) ASC$/', StudentController::orderBy($sort), $sort);
        }
    }
}
