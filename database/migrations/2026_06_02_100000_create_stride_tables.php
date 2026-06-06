<?php

use App\Models\Common\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stride — AI training companion. Phase 0 foundation tables.
 *
 * Scope here is the seedable reference catalogue (equipment, exercises,
 * official spots) plus the per-user training profile that the AI coach reads
 * for context. Sessions / blocks / goals / injuries / weight / coach tables
 * arrive in later phases.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-user training profile (1:1 with users). Feeds the coach's context.
        Schema::create('stride_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('goal_weight_kg', 5, 1)->nullable();
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->string('persona_key')->default('calm'); // coach | calm | nerd | buddy
            $table->string('units')->default('metric');     // metric | imperial
            $table->unsignedInteger('streak_days')->default(0);
            $table->json('preferences')->nullable();         // free-form coach prefs
            $table->timestamps();

            $table->unique('user_id');
        });

        // Global equipment catalogue (grouped). Shared across spots + sessions.
        Schema::create('stride_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // slug, e.g. "barbell"
            $table->string('name');            // "Barbell"
            $table->string('group');           // "Free weights"
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index('group');
        });

        // Exercise library (flattened from the prototype's nested tree).
        Schema::create('stride_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');          // strength | calisthenics | conditioning | cardio | mobility
            $table->string('group')->nullable(); // "Chest", "Back", ...
            $table->string('tag')->nullable();   // Compound | Isolation
            $table->string('difficulty')->nullable(); // Beginner | Intermediate | Advanced
            $table->string('equipment_label')->nullable(); // human-readable, e.g. "Barbell + bench"
            $table->json('primary_muscles')->nullable();
            $table->json('secondary_muscles')->nullable();
            $table->string('video_url')->nullable();
            $table->json('cues')->nullable();
            $table->json('mistakes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('group');
        });

        // Training spots. user_id NULL = official/curated directory entry.
        Schema::create('stride_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('gym');  // gym | home | park | ...
            $table->string('size')->nullable();       // Compact | Medium | Large | Open-air
            $table->string('blurb')->nullable();
            $table->boolean('is_official')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('photo_path')->nullable();
            $table->json('equipment')->nullable();    // array of equipment keys/labels
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_official');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_spots');
        Schema::dropIfExists('stride_exercises');
        Schema::dropIfExists('stride_equipment');
        Schema::dropIfExists('stride_profiles');
    }
};
