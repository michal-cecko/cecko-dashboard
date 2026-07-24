<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a session exercise as part of the dedicated warm-up group ('warmup') or a
 * working movement ('working'). Drives the "grouped warm-up" preference; existing
 * rows default to 'working' so pre-existing sessions render exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_session_exercises', function (Blueprint $table) {
            $table->string('section')->default('working')->after('tag'); // warmup | working
        });
    }

    public function down(): void
    {
        Schema::table('stride_session_exercises', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
