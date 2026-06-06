<?php

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\Exercise;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stride — Phase 1 training domain (no AI).
 *
 * Periodised blocks → sessions → exercises → sets, plus goals, injuries (with a
 * journal) and bodyweight history. Mirrors the prototype's STRIDE_BLOCKS,
 * STRIDE_GOALS, STRIDE_INJURIES and STRIDE_WEIGHT_HISTORY shapes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mesocycles.
        Schema::create('stride_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phase')->nullable();          // "Accumulation", ...
            $table->string('status')->default('upcoming'); // done | active | upcoming
            $table->unsignedSmallInteger('weeks')->default(0);
            $table->unsignedSmallInteger('week_of')->nullable(); // current week within an active block
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('summary')->nullable();
            $table->string('accent')->nullable();
            $table->json('stats')->nullable();             // [{label, value}, ...]
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Training sessions (a day of the plan).
        Schema::create('stride_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Block::class)->nullable()->constrained()->nullOnDelete();
            $table->string('kind');                        // Push | Pull | Legs | Rest | ...
            $table->string('title');
            $table->string('status')->default('planned');  // planned | today | done | skipped
            $table->date('scheduled_date')->nullable();
            $table->unsignedInteger('volume_kg')->default(0);
            $table->unsignedSmallInteger('duration_min')->default(0);
            $table->decimal('rpe', 3, 1)->nullable();
            $table->string('skip_reason')->nullable();
            $table->string('mood')->nullable();
            $table->text('skip_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'scheduled_date']);
        });

        // Exercises within a session (snapshot of the library item + per-session note).
        Schema::create('stride_session_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Session::class)->constrained('stride_sessions')->cascadeOnDelete();
            $table->foreignIdFor(Exercise::class)->nullable()->constrained('stride_exercises')->nullOnDelete();
            $table->string('name');
            $table->string('tag')->nullable();             // Compound | Isolation
            $table->string('note')->nullable();
            $table->string('video_cue')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('session_id');
        });

        // Individual sets (planned + logged).
        Schema::create('stride_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SessionExercise::class, 'session_exercise_id')
                ->constrained('stride_session_exercises')->cascadeOnDelete();
            $table->string('kind')->default('Working');    // Warm-up | Working | AMRAP | Drop
            $table->unsignedSmallInteger('reps')->default(0);
            $table->decimal('kg', 6, 2)->default(0);
            $table->unsignedSmallInteger('rest_sec')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('actual_reps')->nullable();
            $table->decimal('actual_kg', 6, 2)->nullable();
            $table->timestamps();

            $table->index('session_exercise_id');
        });

        // Tracked goals with progress rings.
        Schema::create('stride_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->nullable();        // Strength | Body comp | Cardio | ...
            $table->decimal('progress', 4, 3)->default(0); // 0.000 – 1.000
            $table->string('current_label')->nullable();
            $table->string('target_label')->nullable();
            $table->date('deadline')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_achieved')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        // Injuries the coach programs around.
        Schema::create('stride_injuries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('body_part');
            $table->string('label')->nullable();           // diagnosis
            $table->string('severity')->nullable();        // Mild | Moderate | Severe
            $table->string('status')->default('active');   // active | monitoring | resolved
            $table->date('since')->nullable();
            $table->text('note')->nullable();
            $table->json('avoid')->nullable();             // movements to avoid
            $table->json('safe')->nullable();              // safe alternatives
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Per-injury journal entries.
        Schema::create('stride_injury_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Injury::class)->constrained('stride_injuries')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('trend')->nullable();           // better | same | worse
            $table->text('text');
            $table->timestamps();

            $table->index('injury_id');
        });

        // Bodyweight history.
        Schema::create('stride_weight_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->decimal('kg', 5, 1);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_weight_entries');
        Schema::dropIfExists('stride_injury_journal_entries');
        Schema::dropIfExists('stride_injuries');
        Schema::dropIfExists('stride_goals');
        Schema::dropIfExists('stride_sets');
        Schema::dropIfExists('stride_session_exercises');
        Schema::dropIfExists('stride_sessions');
        Schema::dropIfExists('stride_blocks');
    }
};
