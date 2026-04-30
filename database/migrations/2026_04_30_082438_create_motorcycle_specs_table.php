<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('engine_layout')->nullable();
            $table->smallInteger('displacement_ccm')->nullable();
            $table->smallInteger('power_kw')->nullable();
            $table->string('cooling')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->tinyInteger('gear_count')->nullable();
            $table->string('final_drive')->nullable();
            $table->string('oil_spec')->nullable();
            $table->string('tire_front')->nullable();
            $table->string('tire_rear')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_specs');
    }
};
