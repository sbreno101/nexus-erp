<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Após migrar o banco de testes, popula a tabela de permissões
     * (necessária para criar os perfis padrão das empresas).
     */
    protected bool $seed = true;

    protected string $seeder = PermissionSeeder::class;
}
