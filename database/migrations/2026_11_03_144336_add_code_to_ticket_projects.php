<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('creators-ticketing.table_prefix').'departments', function (Blueprint $table): void {
            $table->string('code', 6)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table(config('creators-ticketing.table_prefix').'departments', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
