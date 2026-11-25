<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Refeições, opções e alimentos de um plano alimentar.
 *
 * Estrutura: plano → refeições (nome, horário) → opções (o aluno escolhe uma)
 * → itens (alimento + quantidade). O montador do painel envia a árvore inteira
 * em JSON; validate() limpa e valida, save() regrava tudo numa transação.
 * A ordem da lista é a ordem que o aluno vê no app.
 */
final class DietMeals
{
    public const MAX_MEALS = 15;
    public const MAX_OPTIONS = 6;
    public const MAX_ITEMS = 40;

    public const MEAL_NAME_MAX = 80;
    public const OPTION_NAME_MAX = 80;
    public const FOOD_MAX = 150;
    public const QUANTITY_MAX = 50;
    public const NOTES_MAX = 255;

    /**
     * Valida a árvore enviada pelo montador.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>} [refeições limpas, erros]
     */
    public static function validate(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [[], ['Não foi possível ler as refeições enviadas. Recarregue a página e tente de novo.']];
        }

        $errors = [];
        $meals = [];
        $seenNames = [];

        if (count($raw) > self::MAX_MEALS) {
            $errors[] = 'O plano pode ter no máximo ' . self::MAX_MEALS . ' refeições.';
        }

        foreach (array_values(array_slice($raw, 0, self::MAX_MEALS)) as $m => $meal) {
            $meal = is_array($meal) ? $meal : [];
            $label = 'Refeição ' . ($m + 1);

            $name = self::text($meal['name'] ?? '');
            if ($name === '') {
                $errors[] = "{$label}: dê um nome (ex.: Café da manhã).";
            } elseif (mb_strlen($name) > self::MEAL_NAME_MAX) {
                $errors[] = "{$label}: nome com no máximo " . self::MEAL_NAME_MAX . ' caracteres.';
            } else {
                $label = $name;
                // O check-in da dieta identifica a refeição pelo nome.
                $key = DietCheckins::key($name);
                if (isset($seenNames[$key])) {
                    $errors[] = "Há duas refeições chamadas {$name}. Use nomes diferentes (ex.: Lanche da manhã e Lanche da tarde).";
                }
                $seenNames[$key] = true;
            }

            $time = self::text($meal['meal_time'] ?? '');
            $parsedTime = $time === '' ? null : self::time($time);
            if ($time !== '' && $parsedTime === null) {
                $errors[] = "{$label}: horário inválido (use HH:MM).";
            }

            $notes = self::notes($meal['notes'] ?? '', $label, $errors);

            $rawOptions = is_array($meal['options'] ?? null) ? array_values($meal['options']) : [];
            if ($rawOptions === []) {
                $rawOptions = [['name' => '', 'items' => []]];
            }
            if (count($rawOptions) > self::MAX_OPTIONS) {
                $errors[] = "{$label}: no máximo " . self::MAX_OPTIONS . ' opções.';
                $rawOptions = array_slice($rawOptions, 0, self::MAX_OPTIONS);
            }

            $options = [];
            foreach ($rawOptions as $o => $option) {
                $option = is_array($option) ? $option : [];
                $optName = self::text($option['name'] ?? '');
                $optName = $optName === '' ? 'Opção ' . ($o + 1) : $optName;
                $optLabel = count($rawOptions) > 1 ? "{$label}, {$optName}" : $label;

                if (mb_strlen($optName) > self::OPTION_NAME_MAX) {
                    $errors[] = "{$label}: nome da opção " . ($o + 1) . ' com no máximo ' . self::OPTION_NAME_MAX . ' caracteres.';
                }

                $rawItems = is_array($option['items'] ?? null) ? array_values($option['items']) : [];
                if (count($rawItems) > self::MAX_ITEMS) {
                    $errors[] = "{$optLabel}: no máximo " . self::MAX_ITEMS . ' alimentos.';
                    $rawItems = array_slice($rawItems, 0, self::MAX_ITEMS);
                }

                $items = [];
                foreach ($rawItems as $i => $item) {
                    $item = is_array($item) ? $item : [];
                    $food = self::text($item['food'] ?? '');
                    $quantity = self::text($item['quantity'] ?? '');
                    $itemNotes = self::text($item['notes'] ?? '');

                    // Linha totalmente vazia: o personal clicou em "adicionar" e desistiu.
                    if ($food === '' && $quantity === '' && $itemNotes === '') {
                        continue;
                    }

                    $n = $i + 1;
                    if ($food === '') {
                        $errors[] = "{$optLabel}: informe o alimento do item {$n}.";
                    } elseif (mb_strlen($food) > self::FOOD_MAX) {
                        $errors[] = "{$optLabel}: alimento do item {$n} com no máximo " . self::FOOD_MAX . ' caracteres.';
                    }
                    if ($quantity === '') {
                        $errors[] = "{$optLabel}: informe a quantidade de " . ($food !== '' ? $food : "item {$n}") . ' (ex.: 100 g).';
                    } elseif (mb_strlen($quantity) > self::QUANTITY_MAX) {
                        $errors[] = "{$optLabel}: quantidade do item {$n} com no máximo " . self::QUANTITY_MAX . ' caracteres.';
                    }

                    $items[] = [
                        'food'     => $food,
                        'quantity' => $quantity,
                        'notes'    => self::notes($itemNotes, $optLabel, $errors),
                    ];
                }

                $options[] = [
                    'name'  => $optName,
                    'notes' => self::notes($option['notes'] ?? '', $optLabel, $errors),
                    'items' => $items,
                ];
            }

            $meals[] = [
                'name'      => $name,
                'meal_time' => $parsedTime,
                'notes'     => $notes,
                'options'   => $options,
            ];
        }

        return [$meals, array_values(array_unique($errors))];
    }

    /**
     * Junta as linhas das três tabelas na árvore refeição → opções → itens,
     * mantendo a ordem em que vieram.
     *
     * @param list<array<string, mixed>> $meals
     * @param list<array<string, mixed>> $options
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function assemble(array $meals, array $options, array $items): array
    {
        $itemsByOption = [];
        foreach ($items as $item) {
            $itemsByOption[(int) $item['option_id']][] = $item;
        }

        $optionsByMeal = [];
        foreach ($options as $opt) {
            $opt['items'] = $itemsByOption[(int) $opt['id']] ?? [];
            $optionsByMeal[(int) $opt['meal_id']][] = $opt;
        }

        $tree = [];
        foreach ($meals as $meal) {
            $meal['options'] = $optionsByMeal[(int) $meal['id']] ?? [];
            $tree[] = $meal;
        }

        return $tree;
    }

    /**
     * Converte a árvore do banco (tree()) no formato de entrada de validate(),
     * para copiar as refeições de um plano para outro com as mesmas regras.
     *
     * @param list<array<string, mixed>> $tree
     * @return list<array<string, mixed>>
     */
    public static function toInput(array $tree): array
    {
        return array_map(static fn (array $m) => [
            'name'      => (string) $m['name'],
            'meal_time' => $m['meal_time'] === null ? '' : substr((string) $m['meal_time'], 0, 5),
            'notes'     => (string) ($m['notes'] ?? ''),
            'options'   => array_map(static fn (array $o) => [
                'name'  => (string) $o['name'],
                'notes' => (string) ($o['notes'] ?? ''),
                'items' => array_map(static fn (array $i) => [
                    'food'     => (string) $i['food'],
                    'quantity' => (string) $i['quantity'],
                    'notes'    => (string) ($i['notes'] ?? ''),
                ], $o['items'] ?? []),
            ], $m['options'] ?? []),
        ], $tree);
    }

    /** Aceita "7:30", "07:30" e "07:30:00"; devolve "HH:MM:00" ou null. */
    public static function time(string $value): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::00)?$/', trim($value), $m)) {
            return null;
        }

        $h = (int) $m[1];
        $min = (int) $m[2];

        return $h <= 23 && $min <= 59 ? sprintf('%02d:%02d:00', $h, $min) : null;
    }

    /* ------------------------------------------------------------------ */
    /* Banco                                                              */
    /* ------------------------------------------------------------------ */

    /** @return list<array<string, mixed>> refeições do plano com opções e itens */
    public static function tree(int $planId): array
    {
        $meals = Database::select('SELECT * FROM diet_meals WHERE diet_plan_id = ? ORDER BY position, id', [$planId]);
        if ($meals === []) {
            return [];
        }

        $in = implode(',', array_map('intval', array_column($meals, 'id')));
        $options = Database::select("SELECT * FROM diet_meal_options WHERE meal_id IN ({$in}) ORDER BY position, id");

        $items = [];
        if ($options !== []) {
            $inOpt = implode(',', array_map('intval', array_column($options, 'id')));
            $items = Database::select("SELECT * FROM diet_meal_items WHERE option_id IN ({$inOpt}) ORDER BY position, id");
        }

        return self::assemble($meals, $options, $items);
    }

    /**
     * Regrava todas as refeições do plano (saída de validate()).
     * Nada no sistema referencia os ids de refeição/opção/item, então
     * apagar e inserir de novo é seguro e mantém a ordem simples.
     *
     * @param list<array<string, mixed>> $meals
     */
    public static function save(int $planId, array $meals): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Database::execute('DELETE FROM diet_meals WHERE diet_plan_id = ?', [$planId]);

            foreach ($meals as $m => $meal) {
                Database::execute(
                    'INSERT INTO diet_meals (diet_plan_id, name, meal_time, position, notes) VALUES (?, ?, ?, ?, ?)',
                    [$planId, $meal['name'], $meal['meal_time'], $m, $meal['notes']],
                );
                $mealId = (int) $pdo->lastInsertId();

                foreach ($meal['options'] as $o => $option) {
                    Database::execute(
                        'INSERT INTO diet_meal_options (meal_id, name, position, notes) VALUES (?, ?, ?, ?)',
                        [$mealId, $option['name'], $o, $option['notes']],
                    );
                    $optionId = (int) $pdo->lastInsertId();

                    foreach ($option['items'] as $i => $item) {
                        Database::execute(
                            'INSERT INTO diet_meal_items (option_id, food, quantity, position, notes) VALUES (?, ?, ?, ?, ?)',
                            [$optionId, $item['food'], $item['quantity'], $i, $item['notes']],
                        );
                    }
                }
            }

            // Marca o plano como atualizado mesmo que só as refeições tenham mudado.
            Database::execute('UPDATE diet_plans SET updated_at = NOW() WHERE id = ?', [$planId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param list<string> $errors */
    private static function notes(mixed $value, string $label, array &$errors): ?string
    {
        $notes = self::text($value);
        if (mb_strlen($notes) > self::NOTES_MAX) {
            $errors[] = "{$label}: observação com no máximo " . self::NOTES_MAX . ' caracteres.';
        }

        return $notes === '' ? null : $notes;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
