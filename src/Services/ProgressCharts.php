<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Gráficos de evolução: séries de peso e de carga máxima por exercício,
 * e a geometria do gráfico de linha (SVG gerado no servidor, sem
 * dependência de JS ou biblioteca externa).
 */
final class ProgressCharts
{
    /** Mínimo de pontos para desenhar uma linha. */
    public const MIN_POINTS = 2;

    /** Início da área do gráfico (os rótulos do eixo Y ficam à esquerda). */
    public const AXIS_LEFT = 58;

    /**
     * Série do peso a partir dos registros de medidas.
     *
     * @param list<array<string, mixed>> $records registros (measured_on, weight_kg) em qualquer ordem
     * @return list<array{date: string, value: float}>
     */
    public static function weightSeries(array $records): array
    {
        $points = [];
        foreach ($records as $r) {
            if (($r['weight_kg'] ?? null) !== null) {
                $points[] = ['date' => (string) $r['measured_on'], 'value' => (float) $r['weight_kg']];
            }
        }
        usort($points, static fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $points;
    }

    /**
     * Carga máxima por treino realizado de um exercício.
     * Séries sem carga (peso do corpo) não entram; treinos sem nenhuma carga ficam de fora.
     *
     * @param list<array<string, mixed>> $rows séries feitas (session_id, started_at, load_kg, reps)
     * @return list<array{date: string, value: float, reps: ?int, session_id: int}>
     */
    public static function loadSeries(array $rows): array
    {
        $bySession = [];
        foreach ($rows as $r) {
            if ($r['load_kg'] === null) {
                continue;
            }
            $id = (int) $r['session_id'];
            $load = (float) $r['load_kg'];
            $reps = $r['reps'] === null ? null : (int) $r['reps'];
            $current = $bySession[$id] ?? null;

            // Maior carga; no empate, a série com mais repetições.
            if ($current === null || $load > $current['value'] || ($load === $current['value'] && ($reps ?? 0) > ($current['reps'] ?? 0))) {
                $bySession[$id] = [
                    'date'       => substr((string) $r['started_at'], 0, 10),
                    'value'      => $load,
                    'reps'       => $reps,
                    'session_id' => $id,
                    '_at'        => (string) $r['started_at'],
                ];
            }
        }

        $points = array_values($bySession);
        usort($points, static fn ($a, $b) => strcmp($a['_at'], $b['_at']) ?: $a['session_id'] <=> $b['session_id']);

        return array_map(static function (array $p): array {
            unset($p['_at']);

            return $p;
        }, $points);
    }

    /**
     * Geometria do gráfico de linha. O eixo X é proporcional ao tempo
     * (um intervalo de 2 meses ocupa mais espaço que um de 1 semana).
     *
     * @param list<array{date: string, value: float}> $points em ordem cronológica
     * @return array{width: int, height: int, path: string, area: string, dots: list<array{x: float, y: float, date: string, value: float}>, ticks: list<array{y: float, value: float}>, min: float, max: float}|null
     *         null quando há menos de MIN_POINTS pontos
     */
    public static function geometry(array $points, int $width = 600, int $height = 240, int $pad = 34): ?array
    {
        if (count($points) < self::MIN_POINTS) {
            return null;
        }

        $values = array_column($points, 'value');
        $min = min($values);
        $max = max($values);
        // Margem de 10% (ou 1 unidade quando tudo é igual) para a linha não colar nas bordas.
        $margin = $max > $min ? ($max - $min) * 0.1 : 1.0;
        $lo = $min - $margin;
        $hi = $max + $margin;

        $times = array_map(static fn ($p) => (new DateTimeImmutable($p['date']))->getTimestamp(), $points);
        $t0 = min($times);
        $t1 = max($times);

        $left = self::AXIS_LEFT;   // espaço para os rótulos do eixo Y
        $right = $width - $pad / 2;
        $top = $pad / 2;
        $bottom = $height - $pad;

        $x = static fn (int $t, int $i, int $n): float => $t1 > $t0
            ? $left + ($t - $t0) / ($t1 - $t0) * ($right - $left)
            : $left + ($n === 1 ? 0.5 : $i / ($n - 1)) * ($right - $left); // todas na mesma data: distribui
        $y = static fn (float $v): float => $bottom - ($v - $lo) / ($hi - $lo) * ($bottom - $top);

        $dots = [];
        $n = count($points);
        foreach ($points as $i => $p) {
            $dots[] = ['x' => round($x($times[$i], $i, $n), 1), 'y' => round($y($p['value']), 1), 'date' => $p['date'], 'value' => $p['value']];
        }

        $path = 'M' . implode(' L', array_map(static fn ($d) => $d['x'] . ',' . $d['y'], $dots));
        $area = $path . ' L' . $dots[$n - 1]['x'] . ',' . $bottom . ' L' . $dots[0]['x'] . ',' . $bottom . ' Z';

        $ticks = [];
        foreach ([$min, ($min + $max) / 2, $max] as $v) {
            $ticks[] = ['y' => round($y($v), 1), 'value' => round($v, 1)];
        }
        if ($max === $min) {
            $ticks = [$ticks[0]];
        }

        return [
            'width'  => $width,
            'height' => $height,
            'path'   => $path,
            'area'   => $area,
            'dots'   => $dots,
            'ticks'  => $ticks,
            'min'    => $min,
            'max'    => $max,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Banco                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Tudo que a seção de gráficos precisa (app e painel).
     *
     * @param list<array<string, mixed>> $records registros de peso e medidas do aluno
     * @return array{weight: list<array<string, mixed>>, exercises: list<array<string, mixed>>, exercise: ?array, load: list<array<string, mixed>>}
     */
    public static function forStudent(int $studentId, array $records, mixed $requestedExercise): array
    {
        $exercises = self::exercisesWithLoad($studentId);
        $exercise = self::pickExercise($exercises, $requestedExercise);

        return [
            'weight'    => self::weightSeries($records),
            'exercises' => $exercises,
            'exercise'  => $exercise,
            'load'      => $exercise === null ? [] : self::loadSeries(self::loadRows($studentId, $exercise['id'])),
        ];
    }

    /**
     * Exercícios que o aluno já fez com carga, do mais frequente para o menos.
     *
     * @return list<array{id: int, name: string, sessions: int}>
     */
    public static function exercisesWithLoad(int $studentId): array
    {
        $rows = Database::select(
            "SELECT e.id, e.name, COUNT(DISTINCT ws.id) AS sessions
             FROM session_sets ss
             JOIN workout_sessions ws ON ws.id = ss.session_id
             JOIN exercises e ON e.id = ss.exercise_id
             WHERE ws.student_id = ? AND ws.status = 'completed' AND ss.completed = 1 AND ss.load_kg IS NOT NULL
             GROUP BY e.id, e.name
             ORDER BY sessions DESC, e.name",
            [$studentId],
        );

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'sessions' => (int) $r['sessions']], $rows);
    }

    /** @return list<array<string, mixed>> séries feitas do exercício, para loadSeries() */
    public static function loadRows(int $studentId, int $exerciseId): array
    {
        return Database::select(
            "SELECT ss.session_id, ws.started_at, ss.load_kg, ss.reps
             FROM session_sets ss JOIN workout_sessions ws ON ws.id = ss.session_id
             WHERE ws.student_id = ? AND ss.exercise_id = ? AND ws.status = 'completed' AND ss.completed = 1",
            [$studentId, $exerciseId],
        );
    }

    /**
     * Exercício escolhido (?exercicio=) se o aluno tiver carga nele; senão o mais frequente.
     *
     * @param list<array{id: int, name: string, sessions: int}> $exercises
     * @return array{id: int, name: string, sessions: int}|null
     */
    public static function pickExercise(array $exercises, mixed $requested): ?array
    {
        $id = filter_var($requested, FILTER_VALIDATE_INT);
        foreach ($exercises as $e) {
            if ($id !== false && $e['id'] === $id) {
                return $e;
            }
        }

        return $exercises[0] ?? null;
    }
}
