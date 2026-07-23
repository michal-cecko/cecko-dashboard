<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->string('thumbnail_url')->nullable()->after('video_url'); // video poster frame (S3 jpg)
        });
    }

    public function down(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->dropColumn('thumbnail_url');
        });
    }
};
