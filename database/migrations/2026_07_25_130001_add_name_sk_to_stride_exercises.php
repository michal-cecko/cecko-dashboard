<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slovak display names for the gym catalogue. Display-only: `name` stays the
 * canonical English the coach, plan generator and PR matching all key on, and
 * the app swaps in `name_sk` when the athlete's language is Slovak.
 *
 * Backfilled here (not only in the seeder) so a deploy localises the existing
 * catalogue. Source of truth: database/seeders/Stride/data/gym_exercise_names_sk.json
 * (regenerate with tools/sk_names.py when new exercises are imported).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stride_exercises', 'name_sk')) {
            Schema::table('stride_exercises', function (Blueprint $table) {
                $table->string('name_sk')->nullable()->after('name');
            });
        }

        foreach ($this->translations() as $slug => $nameSk) {
            DB::table('stride_exercises')->where('slug', $slug)->update(['name_sk' => $nameSk]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stride_exercises', 'name_sk')) {
            Schema::table('stride_exercises', function (Blueprint $table) {
                $table->dropColumn('name_sk');
            });
        }
    }

    /** @return array<string, string> slug → Slovak name */
    private function translations(): array
    {
        $path = database_path('seeders/Stride/data/gym_exercise_names_sk.json');
        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
};
