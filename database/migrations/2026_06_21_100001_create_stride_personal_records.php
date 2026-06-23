<?php

use App\Models\Common\User;
use App\Models\Stride\Exercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal records (PRs) — the athlete's current/past bests, logged per exercise.
 * Type-aware: `metric_type` says how to read `metrics` (a JSON bag holding any of
 * weight / reps / seconds / distance_m / calories / watts). PRs carry the date they
 * were achieved, since they go stale ("3 front-lever pull-ups 3 years ago").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stride_personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Exercise::class)->nullable()->constrained('stride_exercises')->nullOnDelete();
            $table->string('label');                 // denormalised exercise/PR name (display + AI)
            $table->string('metric_type');           // load | reps | hold | run | sprint | machine
            $table->json('metrics');                 // { weight, reps, seconds, distance_m, calories, watts }
            $table->date('achieved_on')->nullable(); // when the best was hit (staleness)
            $table->string('source')->default('user'); // user | ai-question | coach
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_personal_records');
    }
};
