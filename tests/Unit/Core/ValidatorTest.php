<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testDadosValidosNaoGeramErros(): void
    {
        $errors = Validator::check(
            ['name' => 'Ana', 'email' => 'ana@exemplo.com', 'monthly_fee' => '150,00'],
            ['name' => 'required|max:120', 'email' => 'required|email', 'monthly_fee' => 'required|numeric'],
        );

        self::assertSame([], $errors);
    }

    public function testCampoObrigatorioVazioUsaORotuloEmPortugues(): void
    {
        $errors = Validator::check(['email' => '   '], ['email' => 'required|email']);

        self::assertSame(['email' => 'Informe o e-mail.'], $errors);
    }

    public function testCampoOpcionalVazioEhIgnorado(): void
    {
        self::assertSame([], Validator::check(['birth_date' => ''], ['birth_date' => 'date']));
    }

    public function testParaNaPrimeiraRegraQueFalha(): void
    {
        $errors = Validator::check(['email' => 'x'], ['email' => 'email|min:5']);

        self::assertSame(['email' => 'E-mail inválido.'], $errors);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function regrasInvalidas(): iterable
    {
        yield 'email' => ['email', 'nao-e-email', 'E-mail inválido.'];
        yield 'min' => ['min:8', 'abc', 'Campo precisa ter pelo menos 8 caracteres.'];
        yield 'max' => ['max:3', 'abcd', 'Campo pode ter no máximo 3 caracteres.'];
        yield 'numeric' => ['numeric', 'dez', 'Campo precisa ser um número.'];
        yield 'integer' => ['integer', '1.5', 'Campo precisa ser um número inteiro.'];
        yield 'date com dia inexistente' => ['date', '2026-02-30', 'Campo inválida.'];
        yield 'date em formato BR' => ['date', '20/11/2026', 'Campo inválida.'];
        yield 'in' => ['in:pix,dinheiro', 'cheque', 'Campo inválido.'];
    }

    #[DataProvider('regrasInvalidas')]
    public function testRegraInvalidaRetornaMensagem(string $rule, string $value, string $message): void
    {
        self::assertSame(['campo' => $message], Validator::check(['campo' => $value], ['campo' => $rule]));
    }

    /** @return iterable<string, array{string, string}> */
    public static function regrasValidas(): iterable
    {
        yield 'min com acento conta caracteres' => ['min:4', 'ação'];
        yield 'numeric com vírgula' => ['numeric', '1.234,56'];
        yield 'numeric com R$' => ['numeric', 'R$ 99,90'];
        yield 'integer' => ['integer', '42'];
        yield 'date' => ['date', '2026-11-20'];
        yield 'in' => ['in:pix,dinheiro', 'pix'];
    }

    #[DataProvider('regrasValidas')]
    public function testRegraValidaNaoGeraErro(string $rule, string $value): void
    {
        self::assertSame([], Validator::check(['campo' => $value], ['campo' => $rule]));
    }

    public function testConfirmedComparaComCampoDeConfirmacao(): void
    {
        $rules = ['password' => 'required|confirmed'];

        self::assertSame([], Validator::check(['password' => 'segredo1', 'password_confirmation' => 'segredo1'], $rules));
        self::assertSame(
            ['password' => 'A confirmação não confere.'],
            Validator::check(['password' => 'segredo1', 'password_confirmation' => 'outro'], $rules),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function decimais(): iterable
    {
        yield 'formato brasileiro com milhar' => ['1.234,56', '1234.56'];
        yield 'vírgula decimal' => ['1234,56', '1234.56'];
        yield 'ponto decimal' => ['1234.56', '1234.56'];
        yield 'com R$ e espaços' => [' R$ 99,90 ', '99.90'];
    }

    #[DataProvider('decimais')]
    public function testDecimalNormalizaValoresMonetarios(string $input, string $expected): void
    {
        self::assertSame($expected, Validator::decimal($input));
    }
}
