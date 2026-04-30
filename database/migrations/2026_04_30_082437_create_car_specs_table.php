<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fuel_type')->nullable();
            $table->string('engine_code')->nullable();
            $table->decimal('displacement_l', 3, 1)->nullable();
            $table->smallInteger('power_kw')->nullable();
            $table->string('transmission')->nullable();
            $table->tinyInteger('gear_count')->nullable();
            $table->string('drivetrain')->nullable();
            $table->string('oil_spec')->nullable();
            $table->string('oil_viscosity')->nullable();
            $table->decimal('oil_capacity_l', 3, 1)->nullable();
            $table->smallInteger('fuel_tank_l')->nullable();
            $table->string('tire_front')->nullable();
            $table->string('tire_rear')->nullable();
            $table->string('emission_standard')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_specs');
    }
};
