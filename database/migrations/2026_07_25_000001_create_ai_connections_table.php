<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user AI connection: which provider/model the user brings (BYOK) and their
 * encrypted credentials. One row per user. A user with no row (or provider
 * "free") falls back to the app-subsidised free tier. Credentials are stored
 * with Laravel's encrypted cast and never leave the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // anthropic | gemini | openai | free
            $table->string('provider')->default('free');
            // api_key | oauth | none
            $table->string('auth_type')->default('none');
            // Encrypted JSON: {api_key} or {access_token, refresh_token, expires_at, scope}.
            $table->text('credentials')->nullable();
            // The user's chosen model for this provider (null → provider default map).
            $table->string('model')->nullable();
            // unverified | active | invalid
            $table->string('status')->default('unverified');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
    }
};
