<?php

use App\Models\Common\User;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stride — Phase 2 AI coach.
 *
 * Conversations + messages (with a rolling summary for compaction), durable
 * learned facts (coach_memory), an AI change-log (ai_adjustments) the coach
 * writes when it mutates the plan, and per-call cost/usage telemetry (ai_usage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stride_coach_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('persona_key')->default('calm');
            // Rolling summary of turns older than the kept window (compaction).
            $table->longText('summary')->nullable();
            $table->unsignedBigInteger('summarized_through_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('stride_coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CoachConversation::class, 'conversation_id')
                ->constrained('stride_coach_conversations')->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->longText('content');
            $table->json('cards')->nullable();        // structured UI cards (session/injury/...)
            $table->json('adjustments')->nullable();  // ids/summaries of plan changes this turn made
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        // Durable facts the coach has learned, carried across conversations.
        Schema::create('stride_coach_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();    // optional dedupe key
            $table->text('fact');
            $table->string('source')->default('coach'); // coach | user | system
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // The AI change-log surfaced on Home/Plan ("why" the coach changed things).
        Schema::create('stride_ai_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Session::class)->nullable()->constrained('stride_sessions')->nullOnDelete();
            $table->string('scope')->default('today'); // today | week | block
            $table->string('kind');                    // Swapped | Lowered intensity | Added | ...
            $table->string('target')->nullable();      // "Today · Push"
            $table->string('text');                    // what changed
            $table->text('why')->nullable();           // rationale
            $table->string('source')->default('coach');
            $table->timestamps();

            $table->index(['user_id', 'scope']);
        });

        // Per-provider-call telemetry for cost control + observability.
        Schema::create('stride_ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CoachConversation::class, 'conversation_id')
                ->nullable()->constrained('stride_coach_conversations')->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('purpose')->default('chat'); // chat | summary | generate
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_ai_usage');
        Schema::dropIfExists('stride_ai_adjustments');
        Schema::dropIfExists('stride_coach_memory');
        Schema::dropIfExists('stride_coach_messages');
        Schema::dropIfExists('stride_coach_conversations');
    }
};
