<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('invoice_items')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_translations');
    }
};
