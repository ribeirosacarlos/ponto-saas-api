<?php

namespace App\Support\Commercial;

use Illuminate\Support\Facades\DB;

/**
 * Resolve nomes de tabela do módulo Comercial.
 *
 * Em Postgres usamos um schema real ("commercial"."tabela"). O SQLite usado
 * nos testes (RefreshDatabase + :memory:) não suporta CREATE SCHEMA nem
 * tabelas qualificadas por schema, então caímos para tabelas prefixadas
 * ("commercial_tabela") nesse driver.
 */
class CommercialSchema
{
    public const SCHEMA = 'commercial';

    public static function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function ensureSchemaExists(): void
    {
        if (self::isPgsql()) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS '.self::SCHEMA);
        }
    }

    public static function table(string $name): string
    {
        return self::isPgsql()
            ? self::SCHEMA.'.'.$name
            : self::SCHEMA.'_'.$name;
    }

    public static function usersTable(): string
    {
        return self::isPgsql() ? 'public.users' : 'users';
    }

    public static function companiesTable(): string
    {
        return self::isPgsql() ? 'public.companies' : 'companies';
    }
}
