<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('performed_at');
            $table->unsignedInteger('mileage_km')->nullable();
            $table->string('category');
            $table->string('source')->default('shop');
            $table->string('shop_name')->nullable();
            $table->string('technician')->nullable();
            $table->text('work_summary')->nullable();
            $table->json('parts')->nullable();
            $table->decimal('labor_hours', 5, 2)->nullable();
            $table->decimal('parts_cost_eur', 10, 2)->nullable();
            $table->decimal('labor_cost_eur', 10, 2)->nullable();
            $table->decimal('total_eur', 10, 2)->nullable();
            $table->json('confidence_flags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
            $table->index('category');
            $table->index('source');
        });

        Schema::create('service_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->json('extraction_result')->nullable();
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_imports');
        Schema::dropIfExists('service_records');
    }
};
