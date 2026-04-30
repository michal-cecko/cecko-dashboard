<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_concerns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->string('vehicle_type_match')->nullable();
            $table->string('engine_code_match')->nullable();
            $table->string('bike_category_match')->nullable();
            $table->decimal('shop_diagnostic_cost_min_eur', 8, 2)->nullable();
            $table->decimal('shop_diagnostic_cost_max_eur', 8, 2)->nullable();
            $table->smallInteger('self_check_minutes')->nullable();
            $table->smallInteger('recheck_after_days')->nullable();
            $table->smallInteger('recheck_after_km')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'trigger_type']);
            $table->index('vehicle_type_match');
            $table->index('engine_code_match');
        });

        Schema::create('concern_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_concern_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('order')->default(0);
            $table->string('name');
            $table->text('instruction')->nullable();
            $table->string('input_type');
            $table->json('input_options')->nullable();
            $table->text('ai_assessment_prompt')->nullable();
            $table->text('pass_criteria')->nullable();
            $table->text('fail_criteria')->nullable();
            $table->text('uncertain_criteria')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['maintenance_concern_id', 'order']);
        });

        Schema::create('concern_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_concern_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('verdict')->default('open');
            $table->text('verdict_summary')->nullable();
            $table->decimal('savings_eur', 8, 2)->nullable();
            $table->date('next_due_at')->nullable();
            $table->unsignedInteger('next_due_km')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'verdict']);
            $table->index('opened_at');
        });

        Schema::create('assessment_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concern_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concern_check_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('order')->default(0);
            $table->string('name');
            $table->string('input_type');
            $table->json('user_input')->nullable();
            $table->text('user_notes')->nullable();
            $table->json('ai_assessment')->nullable();
            $table->string('outcome')->default('pending');
            $table->timestamps();

            $table->index('concern_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_check_results');
        Schema::dropIfExists('concern_assessments');
        Schema::dropIfExists('concern_checks');
        Schema::dropIfExists('maintenance_concerns');
    }
};
