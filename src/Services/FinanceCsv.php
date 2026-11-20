<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Gera o CSV das mensalidades do financeiro (abre direto no Excel em pt-BR):
 * UTF-8 com BOM, separador ";", valores com vírgula decimal e datas dd/mm/aaaa.
 */
final class FinanceCsv
{
    public const HEADER = ['Aluno', 'Valor', 'Vencimento', 'Pago em', 'Forma', 'Situação', 'Origem'];

    private const STATUSES = ['paid' => 'Pago', 'pending' => 'Pendente', 'cancelled' => 'Cancelado'];
    private const ORIGINS = ['manual' => 'Manual', 'auto_renewal' => 'Renovação automática'];

    /**
     * @param list<array<string, mixed>> $payments linhas de payments + student_name + student_deleted
     * @param array<string, string>      $methods  código da forma => rótulo
     */
    public static function build(array $payments, array $methods): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::HEADER, ';', '"', '', "\r\n");

        foreach ($payments as $p) {
            fputcsv($out, self::row($p, $methods), ';', '"', '', "\r\n");
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @param array<string, mixed>  $p
     * @param array<string, string> $methods
     * @return list<string>
     */
    public static function row(array $p, array $methods): array
    {
        $name = (string) $p['student_name'];
        if (!empty($p['student_deleted'])) {
            $name .= ' (removido)';
        }

        $method = $p['method'] ?? null;

        return [
            self::safe($name),
            number_format((float) $p['amount'], 2, ',', ''),
            self::date($p['due_date'] ?? null, 'd/m/Y'),
            self::date($p['paid_at'] ?? null, 'd/m/Y H:i'),
            $method === null || $method === '' ? '' : self::safe($methods[$method] ?? (string) $method),
            self::STATUSES[$p['status']] ?? (string) $p['status'],
            self::ORIGINS[$p['origin'] ?? 'manual'] ?? (string) $p['origin'],
        ];
    }

    public static function filename(DateTimeImmutable $month): string
    {
        return 'financeiro-' . $month->format('Y-m') . '.csv';
    }

    private static function date(mixed $value, string $format): string
    {
        return $value === null || $value === '' ? '' : (new DateTimeImmutable((string) $value))->format($format);
    }

    /** Evita que o Excel interprete texto como fórmula (CSV injection). */
    private static function safe(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }
}
