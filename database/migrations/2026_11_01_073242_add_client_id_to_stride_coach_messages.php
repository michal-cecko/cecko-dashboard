<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app's id for a user message, sent again on every retry of that bubble,
 * so a retry never stores or answers the same message twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_coach_messages', function (Blueprint $table) {
            $table->string('client_id', 64)->nullable()->after('role');
            $table->unique(['conversation_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stride_coach_messages', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'client_id']);
            $table->dropColumn('client_id');
        });
    }
};
