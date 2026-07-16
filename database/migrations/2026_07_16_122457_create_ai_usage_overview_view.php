<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Read-only union of the per-panel AI usage tables so the shared
     * "AI útrata" resource can list spend across every panel from one model.
     * The synthetic text id keeps keys unique across the source tables.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW ai_usage_overview AS
            SELECT
                'stride-' || id AS id,
                'stride' AS panel,
                user_id,
                CAST(NULL AS bigint) AS vehicle_id,
                purpose,
                provider,
                model,
                input_tokens,
                output_tokens,
                cache_creation_tokens,
                cache_read_tokens,
                latency_ms,
                cost_usd,
                calls,
                created_at
            FROM stride_ai_usage
            UNION ALL
            SELECT
                'garaz-' || id AS id,
                'garaz' AS panel,
                user_id,
                vehicle_id,
                purpose,
                provider,
                model,
                input_tokens,
                output_tokens,
                cache_creation_tokens,
                cache_read_tokens,
                latency_ms,
                cost_usd,
                calls,
                created_at
            FROM garaz_ai_usage
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ai_usage_overview');
    }
};
