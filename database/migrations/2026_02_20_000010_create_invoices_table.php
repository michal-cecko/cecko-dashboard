<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_number_sequence_id')->constrained('invoice_number_sequences')->restrictOnDelete();
            $table->foreignId('recurring_invoice_id')->nullable()->constrained('recurring_invoices')->nullOnDelete();
            $table->string('invoice_number', 100);
            $table->string('status', 20)->default('new');
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('delivery_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('subtotal_base', 12, 2)->nullable();
            $table->decimal('vat_total_base', 12, 2)->nullable();
            $table->decimal('total_base', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('buyer_snapshot')->nullable();
            $table->jsonb('seller_snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
