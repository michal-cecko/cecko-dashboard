<?php

use App\Models\Common\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily check-in: how the athlete feels today (energy 1-5) plus an optional note.
 * Previously lived only in the phone's localStorage, so it was lost on reinstall
 * and invisible to the coach on any other device. One row per user per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stride_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedTinyInteger('energy')->nullable();  // 1 wrecked … 5 primed
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_checkins');
    }
};
