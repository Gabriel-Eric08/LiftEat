<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Planos alimentares do aluno: validação, regra de plano ativo e persistência.
 *
 * Regra: cada aluno tem no máximo UM plano ativo. O banco não garante isso
 * (não há índice único), então toda ativação passa por activate(), que
 * desativa os outros planos do aluno na mesma transação.
 */
final class DietPlans
{
    public const NAME_MAX = 120;
    public const NOTES_MAX = 2000;

    /** Limites das metas diárias: [campo => [rótulo, máximo]]. */
    public const TARGETS = [
        'target_kcal'      => ['Calorias', 10000],
        'target_protein_g' => ['Proteína', 1000],
        'target_carbs_g'   => ['Carboidrato', 1500],
        'target_fat_g'     => ['Gordura', 1000],
    ];

    /**
     * Normaliza e valida os dados do formulário do plano.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: list<string>} [dados limpos, erros]
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $name = self::text($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Dê um nome ao plano.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors[] = 'O nome do plano pode ter no máximo ' . self::NAME_MAX . ' caracteres.';
        }

        $notes = self::text($input['notes'] ?? '');
        if (mb_strlen($notes) > self::NOTES_MAX) {
            $errors[] = 'As observações podem ter no máximo ' . self::NOTES_MAX . ' caracteres.';
        }

        $data = ['name' => $name, 'notes' => $notes === '' ? null : $notes];

        foreach (self::TARGETS as $field => [$label, $max]) {
            $raw = self::text($input[$field] ?? '');
            if ($raw === '') {
                $data[$field] = null;
                continue;
            }

            $value = filter_var($raw, FILTER_VALIDATE_INT);
            if ($value === false || $value < 0 || $value > $max) {
                $errors[] = "{$label}: informe um número inteiro entre 0 e {$max}.";
                $data[$field] = null;
                continue;
            }

            $data[$field] = $value;
        }

        return [$data, $errors];
    }

    /**
     * O plano salvo deve ficar ativo? O primeiro plano do aluno sempre fica,
     * para o app não ficar vazio; depois disso, só se o personal pedir.
     */
    public static function shouldActivate(bool $requested, bool $studentHasActivePlan): bool
    {
        return $requested || !$studentHasActivePlan;
    }

    /**
     * Ids dos planos do aluno que precisam ser desativados para que
     * $activeId seja o único ativo.
     *
     * @param list<array{id: int|string, is_active: int|string|bool}> $plans planos do aluno
     * @return list<int>
     */
    public static function idsToDeactivate(array $plans, int $activeId): array
    {
        $ids = [];
        foreach ($plans as $plan) {
            if ((int) $plan['id'] !== $activeId && (bool) (int) $plan['is_active']) {
                $ids[] = (int) $plan['id'];
            }
        }

        return $ids;
    }

    /**
     * Nome da cópia: no mesmo aluno ganha " (cópia)"; para outro aluno
     * mantém o nome. Sempre dentro do limite de caracteres.
     */
    public static function copyName(string $name, bool $sameStudent): string
    {
        if (!$sameStudent) {
            return mb_substr($name, 0, self::NAME_MAX);
        }

        $suffix = ' (cópia)';

        return mb_substr($name, 0, self::NAME_MAX - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Dados do plano de origem no formato de validate(), para a cópia.
     *
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public static function copyData(array $plan, string $name): array
    {
        $data = ['name' => $name, 'notes' => $plan['notes'] === null || $plan['notes'] === '' ? null : (string) $plan['notes']];
        foreach (array_keys(self::TARGETS) as $field) {
            $data[$field] = $plan[$field] === null ? null : (int) $plan[$field];
        }

        return $data;
    }

    /* ------------------------------------------------------------------ */
    /* Consultas (dependem do banco; regras acima são testadas sem ele)    */
    /* ------------------------------------------------------------------ */

    /**
     * Copia o plano (dados, refeições, opções e alimentos) para um aluno.
     * A cópia só fica ativa se o aluno destino não tiver plano ativo.
     *
     * @return array{0: int, 1: bool} [id da cópia, ficou ativa]
     */
    public static function duplicate(int $planId, int $targetStudentId, ?string $name = null): array
    {
        $plan = self::find($planId) ?? throw new \RuntimeException("Plano {$planId} não encontrado.");
        $name ??= self::copyName((string) $plan['name'], (int) $plan['student_id'] === $targetStudentId);

        $activate = self::shouldActivate(false, self::hasActive($targetStudentId));
        $newId = self::create($targetStudentId, self::copyData($plan, $name), $activate);
        self::copyMeals($planId, $newId);

        return [$newId, $activate];
    }

    /** Copia as refeições de um plano para outro, passando pela validação do montador. */
    public static function copyMeals(int $fromPlanId, int $toPlanId): void
    {
        [$meals] = DietMeals::validate(DietMeals::toInput(DietMeals::tree($fromPlanId)));
        if ($meals !== []) {
            DietMeals::save($toPlanId, $meals);
        }
    }

    /** @return array<string, mixed>|null */
    public static function find(int $planId): ?array
    {
        return Database::select('SELECT * FROM diet_plans WHERE id = ?', [$planId])[0] ?? null;
    }

    /** Plano ativo do aluno (sem as refeições), ou null. */
    public static function activeFor(int $studentId): ?array
    {
        return Database::select(
            'SELECT * FROM diet_plans WHERE student_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
            [$studentId],
        )[0] ?? null;
    }

    /**
     * Planos do aluno, o ativo primeiro, com a quantidade de refeições.
     *
     * @return list<array<string, mixed>>
     */
    public static function forStudent(int $studentId): array
    {
        return Database::select(
            'SELECT p.*, (SELECT COUNT(*) FROM diet_meals m WHERE m.diet_plan_id = p.id) AS meal_count
             FROM diet_plans p WHERE p.student_id = ?
             ORDER BY p.is_active DESC, p.updated_at IS NULL, p.updated_at DESC, p.id DESC',
            [$studentId],
        );
    }

    /**
     * Todos os planos de alunos não removidos, para a tela /admin/dietas.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::select(
            'SELECT p.*, s.name AS student_name, s.status AS student_status,
                    (SELECT COUNT(*) FROM diet_meals m WHERE m.diet_plan_id = p.id) AS meal_count
             FROM diet_plans p JOIN students s ON s.id = p.student_id
             WHERE s.deleted_at IS NULL
             ORDER BY s.name, p.is_active DESC, p.id DESC'
        );
    }

    public static function hasActive(int $studentId, ?int $exceptPlanId = null): bool
    {
        $sql = 'SELECT COUNT(*) AS c FROM diet_plans WHERE student_id = ? AND is_active = 1';
        $params = [$studentId];
        if ($exceptPlanId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptPlanId;
        }

        return (int) Database::select($sql, $params)[0]['c'] > 0;
    }

    /** @param array<string, mixed> $data saída de validate() */
    public static function create(int $studentId, array $data, bool $activate): int
    {
        Database::execute(
            'INSERT INTO diet_plans (student_id, name, notes, target_kcal, target_protein_g, target_carbs_g, target_fat_g, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)',
            [$studentId, $data['name'], $data['notes'], $data['target_kcal'], $data['target_protein_g'], $data['target_carbs_g'], $data['target_fat_g']],
        );
        $id = (int) Database::connection()->lastInsertId();

        if ($activate) {
            self::activate($id, $studentId);
        }

        return $id;
    }

    /** @param array<string, mixed> $data saída de validate() */
    public static function update(int $planId, array $data): void
    {
        Database::execute(
            'UPDATE diet_plans SET name = ?, notes = ?, target_kcal = ?, target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?
             WHERE id = ?',
            [$data['name'], $data['notes'], $data['target_kcal'], $data['target_protein_g'], $data['target_carbs_g'], $data['target_fat_g'], $planId],
        );
    }

    /** Ativa o plano e desativa os demais do aluno, numa transação. */
    public static function activate(int $planId, int $studentId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $plans = Database::select('SELECT id, is_active FROM diet_plans WHERE student_id = ? FOR UPDATE', [$studentId]);
            foreach (self::idsToDeactivate($plans, $planId) as $id) {
                Database::execute('UPDATE diet_plans SET is_active = 0 WHERE id = ?', [$id]);
            }
            Database::execute('UPDATE diet_plans SET is_active = 1 WHERE id = ? AND student_id = ?', [$planId, $studentId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Exclui o plano; refeições, opções e itens saem junto (ON DELETE CASCADE). */
    public static function delete(int $planId): void
    {
        Database::execute('DELETE FROM diet_plans WHERE id = ?', [$planId]);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
