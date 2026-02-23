<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->where('default_locale', 'cz')->update(['default_locale' => 'cs']);
        DB::table('invoice_item_translations')->where('locale', 'cz')->update(['locale' => 'cs']);
        DB::table('service_catalog_item_translations')->where('locale', 'cz')->update(['locale' => 'cs']);
        DB::table('company_payment_method_translations')->where('locale', 'cz')->update(['locale' => 'cs']);
    }

    public function down(): void
    {
        DB::table('companies')->where('default_locale', 'cs')->update(['default_locale' => 'cz']);
        DB::table('invoice_item_translations')->where('locale', 'cs')->update(['locale' => 'cz']);
        DB::table('service_catalog_item_translations')->where('locale', 'cs')->update(['locale' => 'cz']);
        DB::table('company_payment_method_translations')->where('locale', 'cs')->update(['locale' => 'cz']);
    }
};
