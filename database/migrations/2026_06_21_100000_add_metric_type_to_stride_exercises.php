<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each exercise carries a metric type that drives how its personal records are
 * entered + displayed: load (weight×reps), reps, hold (time), run (distance+time),
 * sprint (set distance, fastest time), machine (calories/distance/watts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->string('metric_type')->default('load')->after('tag');
        });
    }

    public function down(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->dropColumn('metric_type');
        });
    }
};
