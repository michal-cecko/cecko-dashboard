<?php

use App\Models\Common\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stride_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('user_api_tokens')->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('stride_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('stride_push_subscriptions')->cascadeOnDelete();
            $table->string('type', 32);
            $table->date('local_date');
            $table->string('slot', 32)->default('daily');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'type', 'local_date', 'slot'], 'stride_push_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_push_deliveries');
        Schema::dropIfExists('stride_push_subscriptions');
    }
};
