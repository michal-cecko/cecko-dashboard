<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation can pin its own model, so a user can switch model mid-chat
 * (e.g. Gemini Flash → Pro) for that thread only. Null = use the user's
 * connection default. Must belong to the connection's provider family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_coach_conversations', function (Blueprint $table): void {
            $table->string('model')->nullable()->after('persona_key');
        });
    }

    public function down(): void
    {
        Schema::table('stride_coach_conversations', function (Blueprint $table): void {
            $table->dropColumn('model');
        });
    }
};
