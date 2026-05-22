<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_share_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_share_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission')->default('view');
            $table->timestamps();

            $table->unique(['file_share_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_share_user');
    }
};
