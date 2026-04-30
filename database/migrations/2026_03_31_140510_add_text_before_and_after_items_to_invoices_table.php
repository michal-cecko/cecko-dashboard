<?php

use App\Support\Migrations\JsonColumn;
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
        Schema::table('invoices', function (Blueprint $table) {
            JsonColumn::add($table, 'text_before_items')->nullable()->after('order_number');
            JsonColumn::add($table, 'text_after_items')->nullable()->after('text_before_items');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['text_before_items', 'text_after_items']);
        });
    }
};
