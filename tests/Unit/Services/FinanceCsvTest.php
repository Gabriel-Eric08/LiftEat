<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FinanceCsv;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FinanceCsvTest extends TestCase
{
    private const METHODS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro'];

    /** @return array<string, mixed> */
    private static function payment(array $overrides = []): array
    {
        return [
            'student_name'    => 'Ana Souza',
            'student_deleted' => null,
            'amount'          => '1250.50',
            'due_date'        => '2026-11-10',
            'paid_at'         => '2026-11-08 14:35:00',
            'method'          => 'pix',
            'status'          => 'paid',
            'origin'          => 'manual',
            ...$overrides,
        ];
    }

    public function testComecaComBomECabecalhoSeparadoPorPontoEVirgula(): void
    {
        $csv = FinanceCsv::build([], self::METHODS);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertSame("\xEF\xBB\xBFAluno;Valor;Vencimento;\"Pago em\";Forma;Situação;Origem\r\n", $csv);
    }

    public function testLinhaDePagamentoPagoNoFormatoBrasileiro(): void
    {
        self::assertSame(
            ['Ana Souza', '1250,50', '10/11/2026', '08/11/2026 14:35', 'Pix', 'Pago', 'Manual'],
            FinanceCsv::row(self::payment(), self::METHODS),
        );
    }

    public function testPendenteSemPagamentoDeixaPagoEmEFormaVazios(): void
    {
        $row = FinanceCsv::row(
            self::payment(['status' => 'pending', 'paid_at' => null, 'method' => null, 'origin' => 'auto_renewal']),
            self::METHODS,
        );

        self::assertSame(['Ana Souza', '1250,50', '10/11/2026', '', '', 'Pendente', 'Renovação automática'], $row);
    }

    public function testAlunoRemovidoFicaIdentificado(): void
    {
        $row = FinanceCsv::row(self::payment(['student_deleted' => '2026-11-01 10:00:00']), self::METHODS);

        self::assertSame('Ana Souza (removido)', $row[0]);
    }

    public function testFormaDesconhecidaUsaOCodigoGravado(): void
    {
        self::assertSame('boleto', FinanceCsv::row(self::payment(['method' => 'boleto']), self::METHODS)[4]);
    }

    public function testNomeQueEhFormulaEhNeutralizado(): void
    {
        self::assertSame("'=HYPERLINK(\"x\")", FinanceCsv::row(self::payment(['student_name' => '=HYPERLINK("x")']), self::METHODS)[0]);
        self::assertSame("'-Ana", FinanceCsv::row(self::payment(['student_name' => '-Ana']), self::METHODS)[0]);
    }

    public function testCampoComPontoEVirgulaEAspasEhEscapado(): void
    {
        $csv = FinanceCsv::build([self::payment(['student_name' => 'Ana "Tata"; Souza'])], self::METHODS);

        self::assertStringContainsString("\"Ana \"\"Tata\"\"; Souza\";1250,50;", $csv);
        self::assertSame(2, substr_count($csv, "\r\n"));
    }

    public function testNomeDoArquivoUsaOMes(): void
    {
        self::assertSame('financeiro-2026-03.csv', FinanceCsv::filename(new DateTimeImmutable('2026-03-01')));
    }
}
