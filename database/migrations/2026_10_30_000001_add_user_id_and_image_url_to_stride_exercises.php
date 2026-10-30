<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private exercises: a row with a user_id belongs to that user only. A null
 * user_id is the shared catalogue every user sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('image_url')->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('image_url');
        });
    }
};
