<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->text('description')->nullable()->after('video_url');
            $table->string('source_url')->nullable()->after('description'); // credit / origin link (e.g. IG post)
        });
    }

    public function down(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->dropColumn(['description', 'source_url']);
        });
    }
};
