<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('target_currency', 3);
            $table->decimal('rate', 12, 6);
            $table->date('date');
            $table->string('source', 50)->default('api');
            $table->timestamps();

            $table->unique(['base_currency', 'target_currency', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
