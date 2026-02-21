<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing service_catalog_items data to translations (sk)
        DB::table('service_catalog_items')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('service_catalog_item_translations')
                    ->whereColumn('service_catalog_item_translations.parent_id', 'service_catalog_items.id')
                    ->where('service_catalog_item_translations.locale', 'sk');
            })
            ->orderBy('id')
            ->each(function ($item) {
                DB::table('service_catalog_item_translations')->insert([
                    'parent_id' => $item->id,
                    'locale' => 'sk',
                    'name' => $item->name,
                    'description' => $item->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Migrate existing invoice_items data to translations (sk)
        DB::table('invoice_items')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoice_item_translations')
                    ->whereColumn('invoice_item_translations.parent_id', 'invoice_items.id')
                    ->where('invoice_item_translations.locale', 'sk');
            })
            ->orderBy('id')
            ->each(function ($item) {
                DB::table('invoice_item_translations')->insert([
                    'parent_id' => $item->id,
                    'locale' => 'sk',
                    'description' => $item->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Migrate existing company_payment_methods data to translations (sk)
        DB::table('company_payment_methods')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('company_payment_method_translations')
                    ->whereColumn('company_payment_method_translations.parent_id', 'company_payment_methods.id')
                    ->where('company_payment_method_translations.locale', 'sk');
            })
            ->orderBy('id')
            ->each(function ($item) {
                if ($item->details) {
                    DB::table('company_payment_method_translations')->insert([
                        'parent_id' => $item->id,
                        'locale' => 'sk',
                        'details' => $item->details,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Drop columns from parent tables
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('company_payment_methods', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }

    public function down(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->string('name')->nullable()->after('company_id');
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('service_catalog_item_id');
        });

        Schema::table('company_payment_methods', function (Blueprint $table) {
            $table->text('details')->nullable()->after('is_default');
        });
    }
};
