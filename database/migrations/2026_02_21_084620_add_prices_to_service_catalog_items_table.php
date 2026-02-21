<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->jsonb('prices')->nullable()->after('default_unit_price');
        });

        // Migrate existing default_unit_price into prices JSON
        $items = DB::table('service_catalog_items')->whereNotNull('default_unit_price')->get();
        foreach ($items as $item) {
            $company = DB::table('companies')->find($item->company_id);
            $currency = $company?->default_currency ?? 'EUR';
            DB::table('service_catalog_items')
                ->where('id', $item->id)
                ->update(['prices' => json_encode([$currency => (float) $item->default_unit_price])]);
        }

        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropColumn('default_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->decimal('default_unit_price', 12, 2)->nullable()->after('description');
        });

        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropColumn('prices');
        });
    }
};
