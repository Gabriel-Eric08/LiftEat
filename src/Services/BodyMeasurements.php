<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use DateTimeImmutable;
use PDOException;

/**
 * Peso e medidas corporais do aluno: validação, variação e persistência.
 */
final class BodyMeasurements
{
    /** Campos medidos: coluna => [rótulo, unidade, mínimo, máximo, casas decimais]. */
    public const FIELDS = [
        'weight_kg'    => ['Peso', 'kg', 20, 400, 1],
        'body_fat_pct' => ['Gordura', '%', 2, 70, 1],
        'chest_cm'     => ['Peitoral', 'cm', 30, 250, 1],
        'waist_cm'     => ['Cintura', 'cm', 30, 250, 1],
        'hip_cm'       => ['Quadril', 'cm', 30, 250, 1],
        'arm_cm'       => ['Braço', 'cm', 10, 100, 1],
        'thigh_cm'     => ['Coxa', 'cm', 20, 150, 1],
    ];

    public const NOTES_MAX = 255;
    public const OLDEST_DATE = '2000-01-01';

    /**
     * Valida um registro vindo do formulário.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: list<string>} [dados limpos, erros]
     */
    public static function validate(array $input, string $today): array
    {
        $errors = [];

        $date = self::text($input['measured_on'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($date === '' || $parsed === false || $parsed->format('Y-m-d') !== $date) {
            $errors[] = 'Informe uma data válida.';
            $date = null;
        } elseif ($date > $today) {
            $errors[] = 'A data não pode ser no futuro.';
        } elseif ($date < self::OLDEST_DATE) {
            $errors[] = 'Data muito antiga.';
        }

        $data = ['measured_on' => $date];
        $filled = 0;

        foreach (self::FIELDS as $field => [$label, $unit, $min, $max]) {
            $raw = self::text($input[$field] ?? '');
            if ($raw === '') {
                $data[$field] = null;
                continue;
            }

            $normalized = Validator::decimal($raw);
            if (!is_numeric($normalized)) {
                $errors[] = "{$label}: informe um número (ex.: " . ($unit === 'kg' ? '72,5' : '80') . ').';
                $data[$field] = null;
                continue;
            }

            $value = round((float) $normalized, 1);
            if ($value < $min || $value > $max) {
                $errors[] = "{$label}: informe entre {$min} e {$max} {$unit}.";
                $data[$field] = null;
                continue;
            }

            $data[$field] = $value;
            $filled++;
        }

        if ($filled === 0 && $errors === []) {
            $errors[] = 'Preencha pelo menos o peso ou uma medida.';
        }

        $notes = self::text($input['notes'] ?? '');
        if (mb_strlen($notes) > self::NOTES_MAX) {
            $errors[] = 'Observação com no máximo ' . self::NOTES_MAX . ' caracteres.';
        }
        $data['notes'] = $notes === '' ? null : $notes;

        return [$data, $errors];
    }

    /**
     * Variação de cada medida entre o primeiro e o último registro que a têm.
     *
     * @param list<array<string, mixed>> $records em qualquer ordem
     * @return array<string, array{first: float, last: float, delta: float, firstDate: string, lastDate: string, count: int}>
     */
    public static function variation(array $records): array
    {
        usort($records, static fn ($a, $b) => strcmp((string) $a['measured_on'], (string) $b['measured_on']));

        $out = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $points = array_values(array_filter($records, static fn ($r) => ($r[$field] ?? null) !== null));
            if ($points === []) {
                continue;
            }

            $first = $points[0];
            $last = $points[count($points) - 1];
            $out[$field] = [
                'first'     => (float) $first[$field],
                'last'      => (float) $last[$field],
                'delta'     => round((float) $last[$field] - (float) $first[$field], 1),
                'firstDate' => (string) $first['measured_on'],
                'lastDate'  => (string) $last['measured_on'],
                'count'     => count($points),
            ];
        }

        return $out;
    }

    /**
     * Peso do registro mais recente que tem peso (vai para o cadastro do aluno).
     *
     * @param list<array<string, mixed>> $records
     */
    public static function latestWeight(array $records): ?float
    {
        $best = null;
        foreach ($records as $r) {
            if (($r['weight_kg'] ?? null) !== null && ($best === null || $r['measured_on'] > $best['measured_on'])) {
                $best = $r;
            }
        }

        return $best === null ? null : (float) $best['weight_kg'];
    }

    /** "72,5 kg", "18 %"… para exibição. */
    public static function format(string $field, float|string|null $value, bool $signed = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = (float) $value;
        $text = rtrim(rtrim(number_format(abs($value), 1, ',', '.'), '0'), ',');
        $sign = $signed ? ($value > 0 ? '+' : ($value < 0 ? '−' : '')) : ($value < 0 ? '−' : '');

        return $sign . $text . ' ' . self::FIELDS[$field][1];
    }

    /* ------------------------------------------------------------------ */
    /* Banco                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Registros do aluno, do mais recente para o mais antigo.
     * Null se a tabela ainda não existe (migration 003 pendente).
     *
     * @return list<array<string, mixed>>|null
     */
    public static function forStudent(int $studentId): ?array
    {
        try {
            return Database::select(
                'SELECT * FROM body_measurements WHERE student_id = ? ORDER BY measured_on DESC, id DESC',
                [$studentId],
            );
        } catch (PDOException $e) {
            if (DietCheckins::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id, int $studentId): ?array
    {
        return Database::select('SELECT * FROM body_measurements WHERE id = ? AND student_id = ?', [$id, $studentId])[0] ?? null;
    }

    /**
     * Cria ou atualiza. Sem $id, registrar numa data que já tem registro
     * atualiza esse registro (um por dia). Com $id e data já usada por
     * outro registro, devolve false.
     *
     * @param array<string, mixed> $data saída de validate()
     */
    public static function save(int $studentId, array $data, ?int $id = null): bool
    {
        $cols = array_keys(self::FIELDS);
        $values = array_map(static fn ($c) => $data[$c], $cols);

        if ($id === null) {
            $set = implode(', ', array_map(static fn ($c) => "{$c} = VALUES({$c})", [...$cols, 'notes']));
            Database::execute(
                'INSERT INTO body_measurements (student_id, measured_on, ' . implode(', ', $cols) . ', notes)
                 VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?)
                 ON DUPLICATE KEY UPDATE ' . $set,
                [$studentId, $data['measured_on'], ...$values, $data['notes']],
            );
        } else {
            $taken = Database::select(
                'SELECT id FROM body_measurements WHERE student_id = ? AND measured_on = ? AND id <> ?',
                [$studentId, $data['measured_on'], $id],
            );
            if ($taken !== []) {
                return false;
            }
            Database::execute(
                'UPDATE body_measurements SET measured_on = ?, ' . implode(', ', array_map(static fn ($c) => "{$c} = ?", $cols)) . ', notes = ?
                 WHERE id = ? AND student_id = ?',
                [$data['measured_on'], ...$values, $data['notes'], $id, $studentId],
            );
        }

        self::syncWeight($studentId);

        return true;
    }

    public static function delete(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM body_measurements WHERE id = ? AND student_id = ?', [$id, $studentId]);
        self::syncWeight($studentId);
    }

    /** Leva o peso mais recente para o cadastro do aluno (se houver algum). */
    private static function syncWeight(int $studentId): void
    {
        $weight = self::latestWeight(Database::select(
            'SELECT measured_on, weight_kg FROM body_measurements WHERE student_id = ? AND weight_kg IS NOT NULL',
            [$studentId],
        ));

        if ($weight !== null) {
            Database::execute('UPDATE students SET weight_kg = ? WHERE id = ?', [$weight, $studentId]);
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
