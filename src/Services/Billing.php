<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Regras de mensalidade e acesso do aluno.
 *
 * - Registrar pagamento: cria mensalidade 'paid' e avança access_until em 1 mês
 *   (a partir do vencimento atual, ou de hoje se já venceu). Também reativa o aluno.
 * - Renovação automática: alunos com auto_renew vencidos ganham +1 mês e uma
 *   mensalidade 'pending' para o personal marcar como paga depois.
 * - Cancelar mensalidade: se for a última do período, devolve o acesso ao fim anterior.
 */
final class Billing
{
    /** Avança N meses mantendo o dia; se o dia não existir no mês, usa o último (31/01 -> 28/02). */
    public static function addMonths(DateTimeImmutable $date, int $months = 1): DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify("+{$months} month");
        $day = min((int) $date->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }

    /**
     * Início do próximo período: dia seguinte ao vencimento atual, ou hoje se já venceu / nunca pagou.
     */
    public static function nextPeriodStart(?string $accessUntil, ?DateTimeImmutable $today = null): DateTimeImmutable
    {
        $today ??= new DateTimeImmutable('today');

        if ($accessUntil === null) {
            return $today;
        }

        $next = (new DateTimeImmutable($accessUntil))->modify('+1 day');

        return $next > $today ? $next : $today;
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} [início, fim] de um período mensal */
    public static function period(DateTimeImmutable $start): array
    {
        return [$start, self::addMonths($start)->modify('-1 day')];
    }

    /**
     * Personal registra pagamento: libera mais `$months` meses de acesso.
     *
     * @return list<int> ids das mensalidades criadas
     */
    public static function registerPayment(
        int $studentId,
        float $amountPerMonth,
        int $months = 1,
        ?string $method = null,
        ?string $paidAt = null,
        ?string $notes = null,
    ): array {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $student = self::lockStudent($studentId);
            $start = self::nextPeriodStart($student['access_until']);
            $paidAt ??= date('Y-m-d H:i:s');
            $ids = [];

            for ($i = 0; $i < max(1, $months); $i++) {
                [$from, $to] = self::period($start);

                Database::execute(
                    'INSERT INTO payments (student_id, amount, period_start, period_end, due_date, status, paid_at, method, origin, notes)
                     VALUES (?, ?, ?, ?, ?, \'paid\', ?, ?, \'manual\', ?)',
                    [$studentId, $amountPerMonth, $from->format('Y-m-d'), $to->format('Y-m-d'), $from->format('Y-m-d'), $paidAt, $method, $notes],
                );
                $ids[] = (int) $pdo->lastInsertId();
                $start = $to->modify('+1 day');
            }

            Database::execute(
                'UPDATE students SET access_until = ?, status = \'active\' WHERE id = ?',
                [$start->modify('-1 day')->format('Y-m-d'), $studentId],
            );

            $pdo->commit();

            return $ids;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Marca uma mensalidade pendente (ex.: gerada pela renovação automática) como paga. */
    public static function markAsPaid(int $paymentId, ?string $method = null, ?string $paidAt = null): void
    {
        $updated = Database::execute(
            'UPDATE payments SET status = \'paid\', paid_at = ?, method = COALESCE(?, method)
             WHERE id = ? AND status = \'pending\'',
            [$paidAt ?? date('Y-m-d H:i:s'), $method, $paymentId],
        );

        if ($updated === 0) {
            throw new RuntimeException('Mensalidade não encontrada ou não está pendente.');
        }
    }

    /** Cancela uma mensalidade. Se ela for o período mais recente, o acesso volta ao fim do período anterior. */
    public static function cancel(int $paymentId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $payment = Database::select('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId])[0] ?? null;
            if ($payment === null || $payment['status'] === 'cancelled') {
                throw new RuntimeException('Mensalidade não encontrada ou já cancelada.');
            }

            $student = self::lockStudent((int) $payment['student_id']);

            Database::execute('UPDATE payments SET status = \'cancelled\' WHERE id = ?', [$paymentId]);

            if ($student['access_until'] === $payment['period_end']) {
                $previous = (new DateTimeImmutable($payment['period_start']))->modify('-1 day')->format('Y-m-d');
                Database::execute('UPDATE students SET access_until = ? WHERE id = ?', [$previous, $student['id']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Renova alunos com auto_renew cujo acesso venceu. Se ficou vários meses sem
     * ninguém acessar o sistema, gera um período para cada mês até cobrir hoje.
     *
     * @return int quantidade de mensalidades geradas
     */
    public static function processRenewals(?DateTimeImmutable $today = null): int
    {
        $today ??= new DateTimeImmutable('today');
        $created = 0;

        $students = Database::select(
            'SELECT id FROM students
             WHERE auto_renew = 1 AND status = \'active\' AND deleted_at IS NULL
               AND access_until IS NOT NULL AND access_until < ?',
            [$today->format('Y-m-d')],
        );

        foreach ($students as $row) {
            $pdo = Database::connection();
            $pdo->beginTransaction();

            try {
                $student = self::lockStudent((int) $row['id']);
                $until = new DateTimeImmutable($student['access_until']);

                while ($until < $today) {
                    [$from, $to] = self::period($until->modify('+1 day'));

                    Database::execute(
                        'INSERT INTO payments (student_id, amount, period_start, period_end, due_date, status, origin)
                         VALUES (?, ?, ?, ?, ?, \'pending\', \'auto_renewal\')',
                        [$student['id'], $student['monthly_fee'], $from->format('Y-m-d'), $to->format('Y-m-d'), $from->format('Y-m-d')],
                    );
                    $until = $to;
                    $created++;
                }

                Database::execute('UPDATE students SET access_until = ? WHERE id = ?', [$until->format('Y-m-d'), $student['id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        return $created;
    }

    /** Executa processRenewals no máximo uma vez por dia (controle por arquivo). */
    public static function runDailyRenewals(): void
    {
        $marker = BASE_PATH . '/storage/cache/renewals-last-run';
        $today = date('Y-m-d');

        if (is_file($marker) && trim((string) file_get_contents($marker)) === $today) {
            return;
        }

        // Grava antes para que requisições simultâneas não rodem em dobro.
        file_put_contents($marker, $today, LOCK_EX);
        self::processRenewals();
    }

    /** @return array<string, mixed> */
    private static function lockStudent(int $id): array
    {
        $student = Database::select('SELECT * FROM students WHERE id = ? FOR UPDATE', [$id])[0] ?? null;

        if ($student === null) {
            throw new RuntimeException('Aluno não encontrado.');
        }

        return $student;
    }
}
