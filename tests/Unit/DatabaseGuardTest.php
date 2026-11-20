<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Garante que o bootstrap dos testes mantém o banco bloqueado:
 * localmente o .env aponta para o banco de produção.
 */
final class DatabaseGuardTest extends TestCase
{
    public function testConexaoComOBancoFalhaDuranteOsTestes(): void
    {
        $this->expectException(PDOException::class);

        Database::connection();
    }
}
