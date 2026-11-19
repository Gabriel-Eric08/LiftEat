<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Dados de demonstração para testar o sistema em produção.
 *
 * Tudo que é criado aqui fica identificável (alunos com e-mail @demo.lifteat)
 * e pode ser removido com remove() sem afetar dados reais.
 */
final class DemoSeeder
{
    public const DOMAIN = '@demo.lifteat';
    public const PASSWORD = 'aluno123';

    private const EXERCISES = [
        ['Supino reto', 'Peito', 'Barra', 'Desça a barra até o meio do peito, cotovelos a ~45°.'],
        ['Supino inclinado com halteres', 'Peito', 'Halter', null],
        ['Crucifixo na máquina', 'Peito', 'Máquina', null],
        ['Tríceps na polia (corda)', 'Tríceps', 'Polia', 'Abra a corda no final do movimento.'],
        ['Tríceps francês', 'Tríceps', 'Halter', null],
        ['Puxada frontal', 'Costas', 'Máquina', 'Puxe em direção ao peito, sem balançar o tronco.'],
        ['Remada curvada', 'Costas', 'Barra', null],
        ['Remada baixa', 'Costas', 'Polia', null],
        ['Rosca direta', 'Bíceps', 'Barra', null],
        ['Rosca martelo', 'Bíceps', 'Halter', null],
        ['Agachamento livre', 'Quadríceps', 'Barra', 'Desça até a coxa ficar paralela ao chão.'],
        ['Leg press 45°', 'Quadríceps', 'Máquina', null],
        ['Cadeira extensora', 'Quadríceps', 'Máquina', null],
        ['Mesa flexora', 'Posterior', 'Máquina', null],
        ['Elevação lateral', 'Ombros', 'Halter', null],
        ['Desenvolvimento com halteres', 'Ombros', 'Halter', null],
        ['Panturrilha em pé', 'Panturrilha', 'Máquina', null],
        ['Prancha', 'Abdômen', 'Peso corporal', 'Mantenha o quadril alinhado.'],
    ];

    public static function exists(): bool
    {
        return (int) Database::select(
            'SELECT COUNT(*) AS c FROM students WHERE email LIKE ?',
            ['%' . self::DOMAIN],
        )[0]['c'] > 0;
    }

    /** @return array{students: int, payments: int, sessions: int} */
    public static function seed(): array
    {
        if (self::exists()) {
            throw new RuntimeException('Os dados de teste já foram inseridos. Remova-os antes de inserir de novo.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $ex = self::exercises();
            $today = new DateTimeImmutable('today');

            // [nome, e-mail, mensalidade, renovação automática, status, meses pagos, início do último período (dias a partir de hoje)]
            $people = [
                'ana'    => ['Ana Souza (demo)', 'ana', 180.00, 1, 'active', 5, -10],
                'bruno'  => ['Bruno Lima (demo)', 'bruno', 150.00, 0, 'active', 3, -27],
                'carla'  => ['Carla Mendes (demo)', 'carla', 200.00, 1, 'active', 4, -40],
                'diego'  => ['Diego Rocha (demo)', 'diego', 160.00, 0, 'inactive', 2, -70],
                'eva'    => ['Eva Martins (demo)', 'eva', 170.00, 0, 'active', 0, 0],
                'felipe' => ['Felipe Costa (demo)', 'felipe', 220.00, 0, 'active', 2, -5],
            ];

            $ids = [];
            foreach ($people as $key => [$name, $user, $fee, $auto, $status, $months, $lastStartOffset]) {
                Database::execute(
                    'INSERT INTO students (name, email, password_hash, phone, birth_date, height_cm, weight_kg, goal, notes,
                                           status, monthly_fee, auto_renew, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $name, $user . self::DOMAIN, password_hash(self::PASSWORD, PASSWORD_DEFAULT), '(11) 98888-' . random_int(1000, 9999),
                        sprintf('%d-%02d-%02d', random_int(1985, 2004), random_int(1, 12), random_int(1, 28)),
                        random_int(158, 190), random_int(550, 950) / 10,
                        ['Hipertrofia', 'Emagrecimento', 'Condicionamento', 'Força'][random_int(0, 3)],
                        'Aluno de demonstração — pode ser removido na página de instalação.',
                        $status, $fee, $auto,
                        $today->modify('-' . max(1, $months) . ' months')->format('Y-m-d H:i:s'),
                    ],
                );
                $ids[$key] = (int) $pdo->lastInsertId();
                self::paymentHistory($ids[$key], $fee, $months, $today->modify("{$lastStartOffset} days"));
            }

            // Treinos e dieta da Ana (a aluna "completa" para testar o app)
            $a = self::workout($ids['ana'], 'Treino A — Peito e tríceps', [
                [$ex['Supino reto'], 4, '8-10', 40, 90, null],
                [$ex['Supino inclinado com halteres'], 3, '10-12', 16, 75, null],
                [$ex['Crucifixo na máquina'], 3, '12', 35, 60, null],
                [$ex['Tríceps na polia (corda)'], 3, '12', 25, 60, null],
                [$ex['Tríceps francês'], 3, 'até a falha', 12, 60, 'drop-set'],
            ], [1, 4]);
            $b = self::workout($ids['ana'], 'Treino B — Costas e bíceps', [
                [$ex['Puxada frontal'], 4, '10', 45, 90, null],
                [$ex['Remada curvada'], 3, '8-10', 30, 90, null],
                [$ex['Remada baixa'], 3, '12', 40, 60, null],
                [$ex['Rosca direta'], 3, '10', 20, 60, null],
                [$ex['Rosca martelo'], 3, '12', 10, 60, 'bi-set'],
            ], [2, 5]);
            $c = self::workout($ids['ana'], 'Treino C — Pernas e ombros', [
                [$ex['Agachamento livre'], 4, '8', 50, 120, null],
                [$ex['Leg press 45°'], 4, '12', 160, 90, null],
                [$ex['Cadeira extensora'], 3, '15', 40, 60, null],
                [$ex['Mesa flexora'], 3, '12', 35, 60, null],
                [$ex['Elevação lateral'], 3, '15', 8, 45, null],
                [$ex['Panturrilha em pé'], 4, '20', 60, 45, null],
            ], [3, 6]);
            self::workout($ids['felipe'], 'Full body', [
                [$ex['Agachamento livre'], 3, '10', 40, 90, null],
                [$ex['Supino reto'], 3, '10', 35, 90, null],
                [$ex['Puxada frontal'], 3, '10', 40, 90, null],
                [$ex['Desenvolvimento com halteres'], 3, '12', 12, 60, null],
                [$ex['Prancha'], 3, '40s', null, 45, null],
            ], [1, 3, 5]);

            $sessions = self::sessions($ids['ana'], [$a, $b, $c], 24);
            $sessions += self::sessions($ids['felipe'], [], 6);

            self::diet($ids['ana']);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Fora da transação: gera a renovação automática pendente da Carla (venceu há alguns dias).
        Billing::processRenewals();

        $payments = (int) Database::select(
            'SELECT COUNT(*) AS c FROM payments p JOIN students s ON s.id = p.student_id WHERE s.email LIKE ?',
            ['%' . self::DOMAIN],
        )[0]['c'];

        return ['students' => count($ids), 'payments' => $payments, 'sessions' => $sessions];
    }

    /** Remove todos os alunos de demonstração e o que pertence a eles. @return int alunos removidos */
    public static function remove(): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $ids = array_map('intval', array_column(Database::select(
                'SELECT id FROM students WHERE email LIKE ?',
                ['%' . self::DOMAIN],
            ), 'id'));

            if ($ids !== []) {
                $in = implode(',', $ids);
                // Pagamentos não têm cascade (protegem o histórico real), então saem primeiro.
                $pdo->exec("DELETE FROM payments WHERE student_id IN ({$in})");
                // Treinos, agenda, sessões (séries/fotos) e dieta saem em cascata.
                $pdo->exec("DELETE FROM students WHERE id IN ({$in})");
            }

            // Exercícios da demonstração que não ficaram em uso por alunos reais.
            $names = array_column(self::EXERCISES, 0);
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            Database::execute(
                "DELETE FROM exercises
                 WHERE name IN ({$placeholders})
                   AND id NOT IN (SELECT exercise_id FROM workout_exercises)
                   AND id NOT IN (SELECT exercise_id FROM session_sets)",
                $names,
            );

            $pdo->commit();

            return count($ids);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string, int> nome => id (reaproveita exercícios que já existam com o mesmo nome) */
    private static function exercises(): array
    {
        $out = [];
        foreach (self::EXERCISES as [$name, $group, $equipment, $description]) {
            $existing = Database::select('SELECT id FROM exercises WHERE name = ? LIMIT 1', [$name])[0]['id'] ?? null;

            if ($existing === null) {
                Database::execute(
                    'INSERT INTO exercises (name, muscle_group, equipment, description) VALUES (?, ?, ?, ?)',
                    [$name, $group, $equipment, $description],
                );
                $existing = Database::connection()->lastInsertId();
            }

            $out[$name] = (int) $existing;
        }

        return $out;
    }

    /** Mensalidades pagas em sequência, terminando no período que começa em $lastStart. */
    private static function paymentHistory(int $studentId, float $fee, int $months, DateTimeImmutable $lastStart): void
    {
        if ($months <= 0) {
            return;
        }

        $start = $lastStart;
        for ($i = 1; $i < $months; $i++) {
            $start = $start->modify('-1 month');
        }

        $now = new DateTimeImmutable();
        $methods = ['pix', 'pix', 'dinheiro', 'cartao', 'transferencia'];
        $end = $start;

        for ($i = 0; $i < $months; $i++) {
            [$from, $end] = Billing::period($start);
            $paidAt = $from->setTime(random_int(8, 20), random_int(0, 59));
            $paidAt = $paidAt > $now ? $now : $paidAt;

            Database::execute(
                'INSERT INTO payments (student_id, amount, period_start, period_end, due_date, status, paid_at, method, origin)
                 VALUES (?, ?, ?, ?, ?, \'paid\', ?, ?, \'manual\')',
                [$studentId, $fee, $from->format('Y-m-d'), $end->format('Y-m-d'), $from->format('Y-m-d'),
                 $paidAt->format('Y-m-d H:i:s'), $methods[array_rand($methods)]],
            );
            $start = $end->modify('+1 day');
        }

        Database::execute('UPDATE students SET access_until = ? WHERE id = ?', [$end->format('Y-m-d'), $studentId]);
    }

    /**
     * @param list<array{0: int, 1: int, 2: string, 3: float|int|null, 4: int, 5: string|null}> $items
     * @param list<int> $days
     */
    private static function workout(int $studentId, string $name, array $items, array $days): int
    {
        Database::execute(
            'INSERT INTO workouts (student_id, name, notes) VALUES (?, ?, ?)',
            [$studentId, $name, 'Aqueça 5 minutos antes. Controle a descida em todas as séries.'],
        );
        $id = (int) Database::connection()->lastInsertId();

        foreach ($items as $pos => [$exerciseId, $sets, $reps, $load, $rest, $technique]) {
            Database::execute(
                'INSERT INTO workout_exercises (workout_id, exercise_id, position, sets, reps, load_kg, rest_seconds, technique)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $exerciseId, $pos, $sets, $reps, $load, $rest, $technique],
            );
        }

        foreach ($days as $day) {
            Database::execute(
                'INSERT INTO workout_schedule (student_id, weekday, workout_id) VALUES (?, ?, ?)',
                [$studentId, $day, $id],
            );
        }

        return $id;
    }

    /**
     * Sessões concluídas nos últimos dias, com séries e cargas subindo aos poucos (para ver evolução).
     *
     * @param list<int> $workoutIds vazio = usa os treinos já cadastrados do aluno
     */
    private static function sessions(int $studentId, array $workoutIds, int $count): int
    {
        if ($workoutIds === []) {
            $workoutIds = array_map('intval', array_column(
                Database::select('SELECT id FROM workouts WHERE student_id = ?', [$studentId]),
                'id',
            ));
        }
        if ($workoutIds === []) {
            return 0;
        }

        $workouts = [];
        foreach ($workoutIds as $wid) {
            $workouts[$wid] = [
                'name'  => Database::select('SELECT name FROM workouts WHERE id = ?', [$wid])[0]['name'],
                'items' => Database::select('SELECT * FROM workout_exercises WHERE workout_id = ? ORDER BY position', [$wid]),
            ];
        }

        $created = 0;
        for ($i = $count; $i >= 1; $i--) {
            $wid = $workoutIds[$i % count($workoutIds)];
            $start = (new DateTimeImmutable('today'))
                ->modify('-' . ($i * 2) . ' days')
                ->setTime(random_int(6, 20), random_int(0, 59));
            $end = $start->modify('+' . random_int(45, 75) . ' minutes');

            Database::execute(
                'INSERT INTO workout_sessions (student_id, workout_id, workout_name, status, started_at, finished_at, notes)
                 VALUES (?, ?, ?, \'completed\', ?, ?, ?)',
                [$studentId, $wid, $workouts[$wid]['name'], $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'),
                 $i % 5 === 0 ? 'Treino pesado hoje, mas deu tudo certo.' : null],
            );
            $sessionId = (int) Database::connection()->lastInsertId();

            $progress = 1 + ($count - $i) * 0.015; // ~1,5% de carga a mais por sessão
            foreach ($workouts[$wid]['items'] as $item) {
                for ($set = 1; $set <= (int) $item['sets']; $set++) {
                    $load = $item['load_kg'] !== null ? round((float) $item['load_kg'] * $progress * 2) / 2 : null;
                    Database::execute(
                        'INSERT INTO session_sets (session_id, exercise_id, workout_exercise_id, set_number, reps, load_kg, completed, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                        [$sessionId, $item['exercise_id'], $item['id'], $set, random_int(8, 12), $load, $start->format('Y-m-d H:i:s')],
                    );
                }
            }
            $created++;
        }

        return $created;
    }

    private static function diet(int $studentId): void
    {
        Database::execute(
            'INSERT INTO diet_plans (student_id, name, notes, target_kcal, target_protein_g, target_carbs_g, target_fat_g)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$studentId, 'Hipertrofia — fase 1', "Beba pelo menos 3 L de água por dia.\nTempere com ervas e evite frituras.", 2400, 160, 280, 70],
        );
        $planId = (int) Database::connection()->lastInsertId();

        $meals = [
            ['Café da manhã', '07:00', [
                [['Ovos mexidos', '3 unidades'], ['Pão integral', '2 fatias'], ['Café sem açúcar', '200 ml']],
                [['Iogurte natural', '170 g'], ['Aveia', '40 g'], ['Banana', '1 unidade']],
            ]],
            ['Lanche da manhã', '10:00', [
                [['Fruta da estação', '1 unidade'], ['Castanhas', '20 g']],
            ]],
            ['Almoço', '12:30', [
                [['Arroz', '150 g'], ['Feijão', '100 g'], ['Frango grelhado', '150 g'], ['Salada verde', 'à vontade']],
                [['Batata-doce', '200 g'], ['Patinho moído', '150 g'], ['Legumes no vapor', '100 g']],
                [['Macarrão integral', '120 g'], ['Atum', '1 lata'], ['Brócolis', '100 g']],
            ]],
            ['Pré-treino', '16:30', [
                [['Pão integral', '2 fatias'], ['Pasta de amendoim', '20 g']],
                [['Tapioca', '60 g'], ['Queijo branco', '30 g']],
            ]],
            ['Pós-treino', '19:00', [
                [['Whey protein', '30 g'], ['Banana', '1 unidade']],
            ]],
            ['Jantar', '20:30', [
                [['Tilápia', '150 g'], ['Purê de abóbora', '150 g'], ['Salada', 'à vontade']],
                [['Omelete', '3 ovos'], ['Queijo branco', '30 g'], ['Tomate', '1 unidade']],
            ]],
        ];

        foreach ($meals as $mi => [$name, $time, $options]) {
            Database::execute(
                'INSERT INTO diet_meals (diet_plan_id, name, meal_time, position) VALUES (?, ?, ?, ?)',
                [$planId, $name, $time, $mi],
            );
            $mealId = (int) Database::connection()->lastInsertId();

            foreach ($options as $oi => $items) {
                Database::execute(
                    'INSERT INTO diet_meal_options (meal_id, name, position) VALUES (?, ?, ?)',
                    [$mealId, 'Opção ' . ($oi + 1), $oi],
                );
                $optionId = (int) Database::connection()->lastInsertId();

                foreach ($items as $ii => [$food, $qty]) {
                    Database::execute(
                        'INSERT INTO diet_meal_items (option_id, food, quantity, position) VALUES (?, ?, ?, ?)',
                        [$optionId, $food, $qty, $ii],
                    );
                }
            }
        }
    }
}
