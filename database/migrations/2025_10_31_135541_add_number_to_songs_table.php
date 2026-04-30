<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS song_number_seq;');
            DB::statement('CREATE SEQUENCE song_number_seq START 1;');
        }

        Schema::table('songs', function (Blueprint $table) use ($isPgsql) {
            $column = $table->integer('number')->nullable();

            if ($isPgsql) {
                $column->default(DB::raw('nextval(\'song_number_seq\')'));
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP SEQUENCE IF EXISTS song_number_seq;');
            }
            $table->dropColumn(['number']);
        });
    }
};
