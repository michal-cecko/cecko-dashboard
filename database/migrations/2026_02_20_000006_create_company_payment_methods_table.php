<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('method', 50);
            $table->boolean('is_default')->default(false);
            $table->text('details')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_methods');
    }
};
