<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('creators-ticketing.table_prefix');

        Schema::table($prefix.'departments', function (Blueprint $table): void {
            $table->string('plan_billing')->default('monthly');
            $table->date('plan_started_on')->nullable();
        });

        Schema::create('support_plan_requests', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('department_id')->constrained($prefix.'departments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_plan');
            $table->string('to_plan');
            $table->string('billing')->nullable();
            $table->date('effective_on')->nullable();
            $table->string('status');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_plan_requests');

        Schema::table(config('creators-ticketing.table_prefix').'departments', function (Blueprint $table): void {
            $table->dropColumn(['plan_billing', 'plan_started_on']);
        });
    }
};
