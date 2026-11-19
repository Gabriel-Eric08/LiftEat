<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Aplica os arquivos database/migrations/*.sql em ordem alfabética,
 * registrando cada um em schema_migrations para não rodar duas vezes.
 *
 * Observação: DDL no MySQL faz commit implícito, então não há rollback.
 * Se um arquivo falhar no meio, corrija o banco e o arquivo antes de rodar de novo.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTable();

        $applied = $this->pdo->query('SELECT migration FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff($this->available(), $applied));
    }

    /**
     * @return list<string> migrações aplicadas nesta execução
     */
    public function migrate(): array
    {
        $done = [];

        foreach ($this->pending() as $migration) {
            $sql = (string) file_get_contents($this->directory . '/' . $migration);

            foreach ($this->statements($sql) as $index => $statement) {
                try {
                    $this->pdo->exec($statement);
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        sprintf('Falha em %s (comando %d): %s', $migration, $index + 1, $e->getMessage()),
                        0,
                        $e,
                    );
                }
            }

            $this->pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')
                ->execute([$migration]);

            $done[] = $migration;
        }

        return $done;
    }

    /** @return list<string> */
    private function available(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);

        return $names;
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        // Remove comentários de linha inteira e comentários no fim da linha.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        $sql = preg_replace('/\s+--\s.*$/m', '', $sql) ?? '';

        $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration  VARCHAR(190) NOT NULL,
                applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
