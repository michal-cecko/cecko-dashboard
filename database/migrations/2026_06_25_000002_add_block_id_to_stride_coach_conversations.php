<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation can be scoped to a Block — that chat edits the WHOLE block (all
 * its sessions) and unlocks block-wide tools. The global coach chat keeps
 * block_id = null and behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_coach_conversations', function (Blueprint $table) {
            $table->foreignId('block_id')->nullable()->after('user_id')->constrained('stride_blocks')->nullOnDelete();
            $table->index(['user_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stride_coach_conversations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'block_id']);
            $table->dropConstrainedForeignId('block_id');
        });
    }
};
