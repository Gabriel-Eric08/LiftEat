<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDOException;

/**
 * Check-in da dieta: o aluno marca as refeições do plano ativo que fez no dia.
 *
 * As refeições são identificadas pelo nome (chave normalizada), porque o
 * montador do plano regrava os ids a cada salvamento. Por isso o montador
 * não aceita duas refeições com o mesmo nome no plano.
 */
final class DietCheckins
{
    /** Chave de comparação do nome: minúsculas, sem espaços extras. */
    public static function key(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * Confere o check-in contra o plano ativo e devolve o que deve ser gravado.
     *
     * @param list<array<string, mixed>> $meals árvore do plano (DietMeals::tree)
     * @return array{meal_key: string, meal_name: string, option_name: ?string}|string dados ou mensagem de erro
     */
    public static function resolve(array $meals, mixed $mealName, mixed $optionName): array|string
    {
        $mealKey = self::key(is_scalar($mealName) ? (string) $mealName : '');
        $meal = null;
        foreach ($meals as $m) {
            if ($mealKey !== '' && self::key((string) $m['name']) === $mealKey) {
                $meal = $m;
                break;
            }
        }

        if ($meal === null) {
            return 'Essa refeição não está no seu plano atual. Atualize a página.';
        }

        $options = $meal['options'] ?? [];
        $option = null;

        if (count($options) === 1) {
            $option = $options[0];
        } elseif (count($options) > 1) {
            $optionKey = self::key(is_scalar($optionName) ? (string) $optionName : '');
            foreach ($options as $o) {
                if ($optionKey !== '' && self::key((string) $o['name']) === $optionKey) {
                    $option = $o;
                    break;
                }
            }
            if ($option === null) {
                return 'Escolha qual opção de ' . $meal['name'] . ' você seguiu.';
            }
        }

        return [
            'meal_key'    => self::key((string) $meal['name']),
            'meal_name'   => (string) $meal['name'],
            'option_name' => $option === null ? null : (string) $option['name'],
        ];
    }

    /**
     * Progresso do dia: quantas refeições do plano atual já foram feitas.
     * Check-ins de refeições que não estão mais no plano não contam.
     *
     * @param list<array<string, mixed>> $meals árvore do plano
     * @param list<array<string, mixed>> $logs  check-ins do dia (meal_key, option_name)
     * @return array{done: int, total: int, percent: int, byMeal: array<string, ?string>} byMeal: chave da refeição => opção seguida
     */
    public static function progress(array $meals, array $logs): array
    {
        $logged = [];
        foreach ($logs as $log) {
            $logged[(string) $log['meal_key']] = $log['option_name'] ?? null;
        }

        $byMeal = [];
        foreach ($meals as $meal) {
            $key = self::key((string) $meal['name']);
            if (array_key_exists($key, $logged)) {
                $byMeal[$key] = $logged[$key];
            }
        }

        $total = count($meals);
        $done = count($byMeal);

        return [
            'done'    => $done,
            'total'   => $total,
            'percent' => $total === 0 ? 0 : (int) round($done * 100 / $total),
            'byMeal'  => $byMeal,
        ];
    }

    /**
     * Próxima refeição a mostrar na home: a de horário mais cedo a partir de
     * agora que ainda não foi feita; senão, a primeira não feita do plano.
     * Funciona com o plano em qualquer ordem (o personal define a ordem).
     *
     * @param list<array<string, mixed>> $meals árvore do plano
     * @param list<string> $doneKeys chaves das refeições já feitas hoje
     * @return array<string, mixed>|null
     */
    public static function nextMeal(array $meals, string $now, array $doneKeys = []): ?array
    {
        $pending = array_values(array_filter(
            $meals,
            static fn ($m) => !in_array(self::key((string) $m['name']), $doneKeys, true),
        ));

        $best = null;
        foreach ($pending as $meal) {
            $time = $meal['meal_time'] ?? null;
            if ($time !== null && $time >= $now && ($best === null || $time < $best['meal_time'])) {
                $best = $meal;
            }
        }

        return $best ?? $pending[0] ?? null;
    }

    /* ------------------------------------------------------------------ */
    /* Banco                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Check-ins do aluno no dia, ou null se a tabela ainda não existe
     * (deploy feito, migration 002 ainda não aplicada na instalação).
     *
     * @return list<array<string, mixed>>|null
     */
    public static function forDay(int $studentId, string $date): ?array
    {
        try {
            return Database::select(
                'SELECT meal_key, meal_name, option_name, created_at FROM diet_meal_logs WHERE student_id = ? AND log_date = ?',
                [$studentId, $date],
            );
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Grava (ou troca a opção de) um check-in.
     *
     * @param array{meal_key: string, meal_name: string, option_name: ?string} $checkin saída de resolve()
     * @return bool false se a tabela ainda não existe
     */
    public static function check(int $studentId, int $planId, string $date, array $checkin, int $planMealCount): bool
    {
        return self::guard(static fn () => Database::execute(
            'INSERT INTO diet_meal_logs (student_id, diet_plan_id, log_date, meal_key, meal_name, option_name, plan_meal_count)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE diet_plan_id = VALUES(diet_plan_id), meal_name = VALUES(meal_name),
                                     option_name = VALUES(option_name), plan_meal_count = VALUES(plan_meal_count)',
            [$studentId, $planId, $date, $checkin['meal_key'], $checkin['meal_name'], $checkin['option_name'], $planMealCount],
        ));
    }

    /** @return bool false se a tabela ainda não existe */
    public static function uncheck(int $studentId, string $date, string $mealKey): bool
    {
        return self::guard(static fn () => Database::execute(
            'DELETE FROM diet_meal_logs WHERE student_id = ? AND log_date = ? AND meal_key = ?',
            [$studentId, $date, $mealKey],
        ));
    }

    /** Id HTML estável da refeição na tela de dieta (para voltar rolando até ela). */
    public static function anchor(string $mealName): string
    {
        return 'refeicao-' . substr(md5(self::key($mealName)), 0, 8);
    }

    public static function isMissingTable(PDOException $e): bool
    {
        $message = $e->getMessage();

        return $e->getCode() === '42S02' || str_contains($message, '42S02') || str_contains($message, ' 1146 ');
    }

    private static function guard(callable $write): bool
    {
        try {
            $write();

            return true;
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return false;
            }
            throw $e;
        }
    }
}
