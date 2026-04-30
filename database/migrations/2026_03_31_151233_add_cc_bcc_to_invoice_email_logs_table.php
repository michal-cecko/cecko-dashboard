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
        Schema::table('invoice_email_logs', function (Blueprint $table) {
            JsonColumn::add($table, 'cc')->nullable()->after('recipient_email');
            JsonColumn::add($table, 'bcc')->nullable()->after('cc');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_email_logs', function (Blueprint $table) {
            $table->dropColumn(['cc', 'bcc']);
        });
    }
};
