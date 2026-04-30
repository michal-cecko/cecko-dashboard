<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Driver-aware JSON column factory.
 *
 * On PostgreSQL we want `jsonb` for binary storage + faster queries.
 * On SQLite (used by the test suite) `jsonb` is unrecognized; `json` works.
 *
 * Use inside migrations:
 *   JsonColumn::add($table, 'capabilities')->default('[]');
 */
class JsonColumn
{
    public static function add(Blueprint $table, string $column): ColumnDefinition
    {
        return Schema::getConnection()->getDriverName() === 'pgsql'
            ? $table->jsonb($column)
            : $table->json($column);
    }
}
