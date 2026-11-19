<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validação mínima. Regras separadas por "|":
 *   required, email, min:N, max:N, numeric, integer, date, in:a,b,c,
 *   confirmed (compara com campo_confirmation), unique:tabela,coluna[,idIgnorado]
 * Campos vazios e não-required são ignorados.
 */
final class Validator
{
    private const LABELS = [
        'name'         => 'nome',
        'email'        => 'e-mail',
        'password'     => 'senha',
        'monthly_fee'  => 'mensalidade',
        'birth_date'   => 'data de nascimento',
        'height_cm'    => 'altura',
        'weight_kg'    => 'peso',
        'amount'       => 'valor',
        'months'       => 'meses',
        'paid_at'      => 'data do pagamento',
    ];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @return array<string, string> erros por campo (vazio = válido)
     */
    public static function check(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $label = self::LABELS[$field] ?? str_replace('_', ' ', $field);
            $rulesList = explode('|', $ruleString);
            $empty = $value === null || $value === '';

            if ($empty) {
                if (in_array('required', $rulesList, true)) {
                    $errors[$field] = "Informe o {$label}.";
                }
                continue;
            }

            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

                $error = match ($name) {
                    'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'E-mail inválido.',
                    'min'       => mb_strlen((string) $value) >= (int) $arg ? null : ucfirst($label) . " precisa ter pelo menos {$arg} caracteres.",
                    'max'       => mb_strlen((string) $value) <= (int) $arg ? null : ucfirst($label) . " pode ter no máximo {$arg} caracteres.",
                    'numeric'   => is_numeric(self::decimal((string) $value)) ? null : ucfirst($label) . ' precisa ser um número.',
                    'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : ucfirst($label) . ' precisa ser um número inteiro.',
                    'date'      => self::isDate((string) $value) ? null : ucfirst($label) . ' inválida.',
                    'in'        => in_array((string) $value, explode(',', (string) $arg), true) ? null : ucfirst($label) . ' inválido.',
                    'confirmed' => $value === ($data[$field . '_confirmation'] ?? null) ? null : 'A confirmação não confere.',
                    'unique'    => self::isUnique((string) $value, (string) $arg) ? null : ucfirst($label) . ' já está em uso.',
                    default     => null,
                };

                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    /** Aceita "1.234,56", "1234,56" e "1234.56". */
    public static function decimal(string $value): string
    {
        $value = trim(str_replace(['R$', ' '], '', $value));

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }

    private static function isDate(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private static function isUnique(string $value, string $arg): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $arg), 3, null);

        if (!preg_match('/^\w+$/', (string) $table) || !preg_match('/^\w+$/', (string) $column)) {
            return false;
        }

        $sql = "SELECT COUNT(*) AS c FROM {$table} WHERE {$column} = ?";
        $params = [$value];

        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $ignoreId;
        }

        return (int) (Database::select($sql, $params)[0]['c'] ?? 0) === 0;
    }
}
