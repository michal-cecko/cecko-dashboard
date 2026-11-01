<?php

use App\Models\Common\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routines are interval timers (work, rest and loop steps) for classes the user
 * leads. A finished run is logged as a done stride_sessions row of kind
 * "Routine"; client_id makes a retried upload return the first row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stride_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('kind', 20);
            $table->json('steps');
            $table->unsignedInteger('total_seconds');
            $table->unsignedSmallInteger('step_count');
            $table->timestamps();
        });

        Schema::table('stride_sessions', function (Blueprint $table) {
            $table->foreignId('routine_id')->nullable()->after('block_id')->constrained('stride_routines')->nullOnDelete();
            $table->uuid('client_id')->nullable()->after('routine_id');
            $table->boolean('ended_early')->default(false)->after('completed_at');
            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stride_sessions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_id']);
            $table->dropConstrainedForeignId('routine_id');
            $table->dropColumn(['client_id', 'ended_early']);
        });

        Schema::dropIfExists('stride_routines');
    }
};
