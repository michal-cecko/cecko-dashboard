<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-generation "brief": what the athlete asked for when this plan was built —
 * the picked option, a goals snapshot, the free-text note, the model used, and the
 * AI cost. Kept ON the block (1 generation = 1 block), so it's disposed of with the
 * block (delete / reset). Nullable + additive; existing blocks stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_blocks', function (Blueprint $table) {
            $table->json('brief')->nullable()->after('stats');
        });
    }

    public function down(): void
    {
        Schema::table('stride_blocks', function (Blueprint $table) {
            $table->dropColumn('brief');
        });
    }
};
