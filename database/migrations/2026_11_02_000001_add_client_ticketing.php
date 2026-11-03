<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('creators-ticketing.table_prefix');

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('active_company_id')->constrained()->nullOnDelete();
            $table->boolean('ticket_notices_enabled')->default(false)->after('customer_id');
        });

        Schema::table($prefix.'departments', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan')->default('none');
        });

        Schema::table($prefix.'tickets', function (Blueprint $table): void {
            $table->timestamp('public_updated_at')->nullable();
        });

        Schema::create('ticket_user_reads', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_alerts', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained($prefix.'departments')->cascadeOnDelete();
            $table->string('event');
            $table->boolean('is_urgent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'department_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('creators-ticketing.table_prefix');

        Schema::dropIfExists('ticket_alerts');
        Schema::dropIfExists('ticket_user_reads');

        Schema::table($prefix.'tickets', function (Blueprint $table): void {
            $table->dropColumn('public_updated_at');
        });

        Schema::table($prefix.'departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('plan');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('ticket_notices_enabled');
        });
    }
};
