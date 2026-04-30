<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('nickname');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('trim')->nullable();
            $table->smallInteger('year_of_manufacture')->nullable();
            $table->date('first_registration_date')->nullable();
            $table->string('vin_or_serial')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('color')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price_eur', 10, 2)->nullable();
            $table->unsignedInteger('purchase_mileage_km')->nullable();
            $table->unsignedInteger('current_odometer_km')->nullable();
            $table->timestamp('current_odometer_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
