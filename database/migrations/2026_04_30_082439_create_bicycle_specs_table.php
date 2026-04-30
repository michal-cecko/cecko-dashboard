<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bicycle_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bike_category')->nullable();
            $table->string('frame_material')->nullable();
            $table->string('frame_size')->nullable();
            $table->string('wheel_size')->nullable();
            $table->string('drivetrain_brand')->nullable();
            $table->string('drivetrain_speeds')->nullable();
            $table->string('front_brake_type')->nullable();
            $table->string('rear_brake_type')->nullable();
            $table->string('suspension_type')->nullable();
            $table->smallInteger('fork_travel_mm')->nullable();
            $table->smallInteger('rear_travel_mm')->nullable();
            $table->boolean('has_dropper_post')->default(false);
            $table->string('dropper_brand_model')->nullable();
            $table->string('tire_type')->nullable();
            $table->smallInteger('tire_width_mm')->nullable();
            $table->decimal('tire_pressure_bar', 3, 1)->nullable();
            $table->boolean('is_electric')->default(false);
            $table->string('motor_brand')->nullable();
            $table->string('motor_model')->nullable();
            $table->smallInteger('battery_wh')->nullable();
            $table->smallInteger('range_km_estimated')->nullable();
            $table->json('ride_modes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bicycle_specs');
    }
};
