<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_number_sequence_id')->constrained('invoice_number_sequences')->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('interval', 20);
            $table->unsignedSmallInteger('day_of_month');
            $table->string('currency', 3);
            $table->string('payment_method', 50)->nullable();
            $table->unsignedInteger('due_days')->default(14);
            $table->text('notes')->nullable();
            $table->jsonb('items_template');
            $table->date('last_generated_at')->nullable();
            $table->date('next_generation_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
