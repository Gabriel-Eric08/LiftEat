<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDOException;

/**
 * Adesão à dieta: quanto do plano o aluno marcou como feito em cada dia.
 *
 * Total de refeições de um dia:
 *  - com check-in: o tamanho do plano gravado no próprio check-in
 *    (plan_meal_count), para não mudar o passado quando o plano é trocado;
 *  - sem check-in: o tamanho do plano ativo hoje;
 *  - antes do primeiro plano do aluno: dia sem plano (não entra na conta).
 */
final class DietAdherence
{
    public const DAYS = 7;

    /** Ordenações de /admin/dietas: chave da URL => rótulo. */
    public const SORTS = ['aluno' => 'Aluno', 'adesao' => 'Menor adesão'];

    /**
     * Adesão dia a dia, do mais antigo para hoje.
     *
     * @param list<array<string, mixed>> $logs check-ins do aluno no período (log_date, meal_key, plan_meal_count)
     * @param int     $currentMealCount refeições do plano ativo hoje (0 = sem plano ativo)
     * @param ?string $planSince data (Y-m-d) do primeiro plano do aluno; null = nunca teve plano
     * @return list<array{date: string, done: int, total: int, percent: ?int}> percent null = dia sem plano
     */
    public static function days(array $logs, string $today, int $currentMealCount, ?string $planSince, int $days = self::DAYS): array
    {
        $byDay = [];
        foreach ($logs as $log) {
            $date = (string) $log['log_date'];
            $byDay[$date]['keys'][(string) $log['meal_key']] = true;
            $byDay[$date]['total'] = max($byDay[$date]['total'] ?? 0, (int) $log['plan_meal_count']);
        }

        $end = new DateTimeImmutable($today);
        $out = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $end->modify("-{$i} days")->format('Y-m-d');

            if (isset($byDay[$date])) {
                $done = count($byDay[$date]['keys']);
                // Se o plano encolheu depois do check-in, nunca passa de 100%.
                $total = max($byDay[$date]['total'], $done);
            } elseif ($planSince !== null && $date >= $planSince && $currentMealCount > 0) {
                $done = 0;
                $total = $currentMealCount;
            } else {
                $out[] = ['date' => $date, 'done' => 0, 'total' => 0, 'percent' => null];
                continue;
            }

            $out[] = ['date' => $date, 'done' => $done, 'total' => $total, 'percent' => (int) round($done * 100 / $total)];
        }

        return $out;
    }

    /**
     * Resumo do período a partir de days().
     *
     * @param list<array{date: string, done: int, total: int, percent: ?int}> $days
     * @return array{done: int, total: int, percent: ?int, daysWithCheckin: int, daysWithPlan: int}
     */
    public static function summary(array $days): array
    {
        $done = 0;
        $total = 0;
        $withCheckin = 0;
        $withPlan = 0;

        foreach ($days as $day) {
            if ($day['percent'] === null) {
                continue;
            }
            $withPlan++;
            $done += $day['done'];
            $total += $day['total'];
            if ($day['done'] > 0) {
                $withCheckin++;
            }
        }

        return [
            'done'            => $done,
            'total'           => $total,
            'percent'         => $total === 0 ? null : (int) round($done * 100 / $total),
            'daysWithCheckin' => $withCheckin,
            'daysWithPlan'    => $withPlan,
        ];
    }

    public static function parseSort(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::SORTS) ? $value : 'aluno';
    }

    /**
     * Ordena as linhas de /admin/dietas. Em "adesao", os planos ativos vêm
     * primeiro, do menor para o maior percentual (sem dado = 0%), e os
     * inativos depois; o desempate é sempre pelo nome do aluno.
     *
     * @param list<array<string, mixed>> $rows linhas com student_name, is_active e adherence (summary ou null)
     * @return list<array<string, mixed>>
     */
    public static function sortRows(array $rows, string $sort): array
    {
        usort($rows, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'adesao') {
                $byActive = (int) (bool) $b['is_active'] <=> (int) (bool) $a['is_active'];
                if ($byActive !== 0) {
                    return $byActive;
                }
                $pa = $a['adherence']['percent'] ?? 0;
                $pb = $b['adherence']['percent'] ?? 0;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
            }

            return strcmp(mb_strtolower((string) $a['student_name']), mb_strtolower((string) $b['student_name']))
                ?: ((int) $a['id'] <=> (int) $b['id']);
        });

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /* Banco                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Adesão dos últimos DAYS dias de cada aluno informado.
     * Null se a tabela de check-ins ainda não existe.
     *
     * @param array<int, int> $activeMealCounts aluno => refeições do plano ativo (0 = sem plano ativo)
     * @return array<int, array{days: list<array<string, mixed>>, summary: array<string, mixed>}>|null
     */
    public static function forStudents(array $activeMealCounts, string $today): ?array
    {
        $ids = array_map('intval', array_keys($activeMealCounts));
        $logs = self::logsByStudent($today, $ids);
        if ($logs === null) {
            return null;
        }

        $since = $ids === [] ? [] : self::planSince(count($ids) === 1 ? $ids[0] : null);
        $out = [];
        foreach ($activeMealCounts as $studentId => $count) {
            $days = self::days($logs[$studentId] ?? [], $today, (int) $count, $since[$studentId] ?? null);
            $out[$studentId] = ['days' => $days, 'summary' => self::summary($days)];
        }

        return $out;
    }

    /**
     * Check-ins dos últimos DAYS dias, agrupados por aluno.
     * Null se a tabela ainda não existe (migration 002 pendente).
     *
     * @param list<int>|null $studentIds null = todos
     * @return array<int, list<array<string, mixed>>>|null
     */
    public static function logsByStudent(string $today, ?array $studentIds = null): ?array
    {
        $from = (new DateTimeImmutable($today))->modify('-' . (self::DAYS - 1) . ' days')->format('Y-m-d');
        $sql = 'SELECT student_id, log_date, meal_key, plan_meal_count FROM diet_meal_logs WHERE log_date BETWEEN ? AND ?';
        $params = [$from, $today];

        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }
            $sql .= ' AND student_id IN (' . implode(',', array_map('intval', $studentIds)) . ')';
        }

        try {
            $rows = Database::select($sql, $params);
        } catch (PDOException $e) {
            if (DietCheckins::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['student_id']][] = $row;
        }

        return $out;
    }

    /**
     * Data do primeiro plano de cada aluno (Y-m-d).
     *
     * @return array<int, string>
     */
    public static function planSince(?int $studentId = null): array
    {
        $rows = Database::select(
            'SELECT student_id, DATE(MIN(created_at)) AS since FROM diet_plans'
            . ($studentId !== null ? ' WHERE student_id = ' . $studentId : '')
            . ' GROUP BY student_id'
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['student_id']] = (string) $row['since'];
        }

        return $out;
    }
}
