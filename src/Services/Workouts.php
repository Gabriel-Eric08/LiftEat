<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Consultas e regras de treinos compartilhadas entre painel e app.
 */
final class Workouts
{
    /** Grupos musculares sugeridos no cadastro de exercícios. */
    public const MUSCLE_GROUPS = [
        'Peito', 'Costas', 'Ombros', 'Bíceps', 'Tríceps', 'Antebraço', 'Abdômen',
        'Quadríceps', 'Posterior', 'Glúteos', 'Panturrilha', 'Corpo inteiro', 'Cardio', 'Mobilidade',
    ];

    public const TECHNIQUES = ['Drop-set', 'Bi-set', 'Tri-set', 'Rest-pause', 'Pirâmide', 'Isometria', 'Negativa', 'Cluster'];

    /**
     * Agenda da semana: [weekday => ['id', 'name', 'notes', 'exercise_count']]
     *
     * @return array<int, array<string, mixed>>
     */
    public static function schedule(int $studentId, bool $onlyActive = true): array
    {
        $rows = Database::select(
            'SELECT ws.weekday, w.id, w.name, w.notes, w.is_active,
                    (SELECT COUNT(*) FROM workout_exercises we WHERE we.workout_id = w.id) AS exercise_count
             FROM workout_schedule ws JOIN workouts w ON w.id = ws.workout_id
             WHERE ws.student_id = ?' . ($onlyActive ? ' AND w.is_active = 1' : ''),
            [$studentId],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['weekday']] = $row;
        }

        return $out;
    }

    /**
     * Treinos do aluno com contagem de exercícios e dias da semana.
     *
     * @return list<array<string, mixed>>
     */
    public static function forStudent(int $studentId): array
    {
        $workouts = Database::select(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM workout_exercises we WHERE we.workout_id = w.id) AS exercise_count,
                    (SELECT COALESCE(SUM(we.sets), 0) FROM workout_exercises we WHERE we.workout_id = w.id) AS set_count,
                    (SELECT GROUP_CONCAT(ws.weekday ORDER BY ws.weekday) FROM workout_schedule ws WHERE ws.workout_id = w.id) AS days,
                    (SELECT MAX(s.started_at) FROM workout_sessions s WHERE s.workout_id = w.id AND s.status = \'completed\') AS last_done_at
             FROM workouts w WHERE w.student_id = ? ORDER BY w.is_active DESC, w.name',
            [$studentId],
        );

        foreach ($workouts as &$w) {
            $w['days'] = $w['days'] === null ? [] : array_map('intval', explode(',', (string) $w['days']));
        }

        return $workouts;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $workoutId): ?array
    {
        return Database::select('SELECT * FROM workouts WHERE id = ?', [$workoutId])[0] ?? null;
    }

    /**
     * Exercícios prescritos de um treino, na ordem.
     *
     * @return list<array<string, mixed>>
     */
    public static function items(int $workoutId): array
    {
        return Database::select(
            'SELECT we.*, e.name, e.muscle_group, e.equipment, e.video_url, e.description, e.is_active AS exercise_active
             FROM workout_exercises we JOIN exercises e ON e.id = we.exercise_id
             WHERE we.workout_id = ? ORDER BY we.position, we.id',
            [$workoutId],
        );
    }

    /** @return list<int> dias da semana (1..7) em que o treino está agendado */
    public static function days(int $workoutId): array
    {
        return array_map('intval', array_column(
            Database::select('SELECT weekday FROM workout_schedule WHERE workout_id = ? ORDER BY weekday', [$workoutId]),
            'weekday',
        ));
    }

    /**
     * Salva o treino inteiro (dados, exercícios e dias) numa transação.
     * Exercícios existentes mantêm o id (o histórico continua ligado à prescrição).
     *
     * @param list<array<string, mixed>> $items [id?, exercise_id, sets, reps, load_kg, rest_seconds, technique, notes]
     * @param list<int> $days
     */
    public static function save(int $workoutId, int $studentId, string $name, ?string $notes, array $items, array $days): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Database::execute('UPDATE workouts SET name = ?, notes = ? WHERE id = ?', [$name, $notes, $workoutId]);

            $keep = [];
            foreach (array_values($items) as $position => $item) {
                $params = [
                    (int) $item['exercise_id'], $position, (int) $item['sets'], (string) $item['reps'],
                    $item['load_kg'], $item['rest_seconds'], $item['technique'], $item['notes'],
                ];

                $id = (int) ($item['id'] ?? 0);
                $exists = $id > 0 && Database::select(
                    'SELECT id FROM workout_exercises WHERE id = ? AND workout_id = ?',
                    [$id, $workoutId],
                ) !== [];

                if ($exists) {
                    Database::execute(
                        'UPDATE workout_exercises SET exercise_id = ?, position = ?, sets = ?, reps = ?, load_kg = ?,
                                rest_seconds = ?, technique = ?, notes = ? WHERE id = ?',
                        [...$params, $id],
                    );
                    $keep[] = $id;
                } else {
                    Database::execute(
                        'INSERT INTO workout_exercises (exercise_id, position, sets, reps, load_kg, rest_seconds, technique, notes, workout_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [...$params, $workoutId],
                    );
                    $keep[] = (int) $pdo->lastInsertId();
                }
            }

            // Remove os exercícios que saíram do treino.
            $keepSql = $keep === [] ? '0' : implode(',', array_map('intval', $keep));
            Database::execute("DELETE FROM workout_exercises WHERE workout_id = ? AND id NOT IN ({$keepSql})", [$workoutId]);

            self::assignDays($workoutId, $studentId, $days);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Define os dias do treino. Um dia só tem um treino: se o dia era de outro treino, passa a ser deste.
     *
     * @param list<int> $days
     */
    public static function assignDays(int $workoutId, int $studentId, array $days): void
    {
        Database::execute('DELETE FROM workout_schedule WHERE workout_id = ?', [$workoutId]);

        foreach (array_unique($days) as $day) {
            if ($day < 1 || $day > 7) {
                continue;
            }
            Database::execute(
                'INSERT INTO workout_schedule (student_id, weekday, workout_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE workout_id = VALUES(workout_id)',
                [$studentId, $day, $workoutId],
            );
        }
    }

    /** Copia um treino (exercícios inclusos, sem dias) para um aluno. Retorna o id novo. */
    public static function duplicate(int $workoutId, int $targetStudentId, ?string $name = null): int
    {
        $workout = self::find($workoutId) ?? throw new RuntimeException('Treino não encontrado.');
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Database::execute(
                'INSERT INTO workouts (student_id, name, notes) VALUES (?, ?, ?)',
                [$targetStudentId, $name ?? $workout['name'], $workout['notes']],
            );
            $newId = (int) $pdo->lastInsertId();

            Database::execute(
                'INSERT INTO workout_exercises (workout_id, exercise_id, position, sets, reps, load_kg, rest_seconds, technique, notes)
                 SELECT ?, exercise_id, position, sets, reps, load_kg, rest_seconds, technique, notes
                 FROM workout_exercises WHERE workout_id = ?',
                [$newId, $workoutId],
            );

            $pdo->commit();

            return $newId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Última carga/reps que o aluno fez em cada exercício (do treino concluído mais recente).
     *
     * @param list<int> $exerciseIds
     * @return array<int, array<int, array{reps: ?int, load_kg: ?float}>> [exercise_id => [set_number => ...]]
     */
    public static function lastPerformance(int $studentId, array $exerciseIds, ?int $excludeSessionId = null): array
    {
        if ($exerciseIds === []) {
            return [];
        }

        $in = implode(',', array_map('intval', array_unique($exerciseIds)));
        $rows = Database::select(
            "SELECT ss.exercise_id, ss.set_number, ss.reps, ss.load_kg, ws.id AS session_id
             FROM session_sets ss
             JOIN workout_sessions ws ON ws.id = ss.session_id
             WHERE ws.student_id = ? AND ws.status = 'completed' AND ss.completed = 1
               AND ss.exercise_id IN ({$in}) AND ws.id <> ?
             ORDER BY ws.started_at DESC, ss.set_number ASC",
            [$studentId, $excludeSessionId ?? 0],
        );

        $out = [];
        $sessionOf = [];
        foreach ($rows as $r) {
            $ex = (int) $r['exercise_id'];
            // Só a sessão mais recente de cada exercício.
            $sessionOf[$ex] ??= (int) $r['session_id'];
            if ($sessionOf[$ex] !== (int) $r['session_id']) {
                continue;
            }
            $out[$ex][(int) $r['set_number']] = [
                'reps'    => $r['reps'] === null ? null : (int) $r['reps'],
                'load_kg' => $r['load_kg'] === null ? null : (float) $r['load_kg'],
            ];
        }

        return $out;
    }

    /**
     * Sessão com séries agrupadas por exercício, fotos e totais.
     *
     * @return array<string, mixed>|null
     */
    public static function sessionDetail(int $sessionId): ?array
    {
        $session = Database::select(
            'SELECT ws.*, s.name AS student_name FROM workout_sessions ws JOIN students s ON s.id = ws.student_id WHERE ws.id = ?',
            [$sessionId],
        )[0] ?? null;

        if ($session === null) {
            return null;
        }

        $sets = Database::select(
            'SELECT ss.*, e.name, e.muscle_group, we.sets AS planned_sets, we.reps AS planned_reps, we.load_kg AS planned_load, we.position
             FROM session_sets ss
             JOIN exercises e ON e.id = ss.exercise_id
             LEFT JOIN workout_exercises we ON we.id = ss.workout_exercise_id
             WHERE ss.session_id = ?
             ORDER BY COALESCE(we.position, 999), ss.exercise_id, ss.set_number',
            [$sessionId],
        );

        $exercises = [];
        $volume = 0.0;
        $done = 0;
        foreach ($sets as $set) {
            $key = (int) $set['exercise_id'];
            $exercises[$key] ??= [
                'name'         => $set['name'],
                'muscle_group' => $set['muscle_group'],
                'planned'      => $set['planned_sets'] !== null ? $set['planned_sets'] . ' × ' . $set['planned_reps'] : null,
                'sets'         => [],
            ];
            $exercises[$key]['sets'][] = $set;
            if ($set['completed']) {
                $done++;
                $volume += (float) $set['load_kg'] * (int) $set['reps'];
            }
        }

        $session['exercises'] = array_values($exercises);
        $session['photos'] = Database::select('SELECT * FROM session_photos WHERE session_id = ? ORDER BY id', [$sessionId]);
        $session['sets_done'] = $done;
        $session['volume'] = $volume;
        $session['minutes'] = $session['finished_at']
            ? (int) round((strtotime($session['finished_at']) - strtotime($session['started_at'])) / 60)
            : null;

        return $session;
    }

    /**
     * Sessão em andamento do aluno (se houver). Sessões esquecidas abertas há mais de
     * 6 horas são encerradas: concluídas se tiveram séries, abandonadas se não.
     *
     * @return array<string, mixed>|null
     */
    public static function activeSession(int $studentId): ?array
    {
        $stale = date('Y-m-d H:i:s', time() - 6 * 3600);
        foreach (Database::select(
            'SELECT ws.id, MAX(ss.created_at) AS last_set, COUNT(ss.id) AS sets
             FROM workout_sessions ws LEFT JOIN session_sets ss ON ss.session_id = ws.id AND ss.completed = 1
             WHERE ws.student_id = ? AND ws.status = \'in_progress\' AND ws.started_at < ?
             GROUP BY ws.id',
            [$studentId, $stale],
        ) as $old) {
            Database::execute(
                'UPDATE workout_sessions SET status = ?, finished_at = COALESCE(?, started_at) WHERE id = ?',
                [(int) $old['sets'] > 0 ? 'completed' : 'abandoned', $old['last_set'], $old['id']],
            );
        }

        return Database::select(
            'SELECT * FROM workout_sessions WHERE student_id = ? AND status = \'in_progress\' ORDER BY started_at DESC LIMIT 1',
            [$studentId],
        )[0] ?? null;
    }
}
