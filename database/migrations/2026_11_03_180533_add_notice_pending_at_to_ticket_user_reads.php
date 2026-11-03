<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_user_reads', function (Blueprint $table): void {
            $table->timestamp('notice_pending_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_user_reads', function (Blueprint $table): void {
            $table->dropIndex(['notice_pending_at']);
            $table->dropColumn('notice_pending_at');
        });
    }
};
