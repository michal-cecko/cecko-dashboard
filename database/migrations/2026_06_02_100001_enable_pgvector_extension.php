<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable the pgvector extension on PostgreSQL.
 *
 * Driver-guarded so the test suite (SQLite in-memory) and any non-Postgres
 * environment skip it as a no-op. The Stride coach does NOT use vector search
 * in the MVP (it injects the full, compact training memory), but enabling the
 * extension now means adding semantic memory retrieval later is a column add,
 * not a database migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Tolerate environments where the pgvector package isn't installed on the
        // server yet — it's unused in the MVP, so a missing extension must not
        // block the rest of the migration run. Install it later (e.g. on RDS:
        // CREATE EXTENSION vector;) when semantic memory is needed.
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (Throwable $e) {
            logger()->warning('Stride: pgvector extension not available — skipping. '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
