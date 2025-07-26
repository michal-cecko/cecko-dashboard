<?php

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
        Schema::create('song_tag_song_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId("song_id")->nullable();
            $table->foreign("song_id")->references("id")->on("songs")->nullOnDelete();
            $table->foreignId("tag_id")->nullable();
            $table->foreign("tag_id")->references("id")->on("song_tags")->nullOnDelete();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_tag_song_pivot');
    }
};
