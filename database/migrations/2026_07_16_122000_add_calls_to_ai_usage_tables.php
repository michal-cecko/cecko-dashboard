<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI usage rows are bucketed per hour: repeated calls with the same key
     * (user, purpose, provider, model, …) within an hour of the bucket's
     * creation merge into one row and increment this counter.
     */
    public function up(): void
    {
        Schema::table('stride_ai_usage', function (Blueprint $table): void {
            $table->integer('calls')->default(1)->after('cost_usd');
        });

        Schema::table('garaz_ai_usage', function (Blueprint $table): void {
            $table->integer('calls')->default(1)->after('cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('stride_ai_usage', function (Blueprint $table): void {
            $table->dropColumn('calls');
        });

        Schema::table('garaz_ai_usage', function (Blueprint $table): void {
            $table->dropColumn('calls');
        });
    }
};
