<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn AiAdjustment into a propose→confirm record: a coach edit is first STAGED
 * (status 'proposed' + a machine-applicable `payload`), then deterministically
 * APPLIED on the user's confirmation (status 'applied'). `block_id` lets a single
 * proposal span every session in a block. All additive + defaulted so the five
 * existing rows (and any non-proposal logging) keep showing as 'applied' history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_ai_adjustments', function (Blueprint $table) {
            $table->foreignId('block_id')->nullable()->after('session_id')->constrained('stride_blocks')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->after('block_id')->constrained('stride_coach_conversations')->nullOnDelete();
            $table->string('status')->default('applied')->after('scope'); // proposed | applied | dismissed
            $table->string('operation')->nullable()->after('kind');        // set_load | swap | add_set | reorder | scale_load | regenerate_session | edit
            $table->json('payload')->nullable()->after('why');             // deterministic params to apply on confirm
            $table->json('preview')->nullable()->after('payload');         // optional before/after summary for the card
            $table->timestamp('applied_at')->nullable()->after('preview');

            $table->index(['user_id', 'status']);
            $table->index(['block_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('stride_ai_adjustments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['block_id', 'status']);
            $table->dropConstrainedForeignId('block_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn(['status', 'operation', 'payload', 'preview', 'applied_at']);
        });
    }
};
