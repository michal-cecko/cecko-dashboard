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
            $table->unsignedSmallInteger('month_of_year')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_generation_date')->nullable();
            $table->timestamp('last_generated_at')->nullable();

            $table->string('currency', 3);
            $table->string('payment_method', 50)->nullable();
            $table->unsignedInteger('due_days')->default(14);
            $table->text('description')->nullable();
            $table->string('order_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('text_before_items')->nullable();
            $table->jsonb('text_after_items')->nullable();

            $table->jsonb('items_template');

            $table->boolean('auto_send')->default(true);
            $table->string('email_recipient')->nullable();
            $table->jsonb('email_cc')->nullable();
            $table->jsonb('email_bcc')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->string('email_locale', 5)->nullable();

            $table->timestamps();

            $table->index(['is_active', 'next_generation_date']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_invoice_id')
                ->nullable()
                ->constrained('recurring_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_invoice_id');
        });

        Schema::dropIfExists('recurring_invoices');
    }
};
