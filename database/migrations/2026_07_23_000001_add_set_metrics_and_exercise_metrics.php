<?php

use App\Enums\Stride\SetMetric;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metric-aware set logging.
 *
 * - stride_exercises.metrics: ordered list of SetMetric values this exercise
 *   logs (backfilled from metric_type; "band" exercises additionally log the
 *   band's pull in the stretched position).
 * - stride_sets.band_kg: prescribed band resistance for band-assisted work.
 * - stride_set_metrics: what the athlete actually did — one row per set+metric,
 *   replacing the flat actual_* column-per-metric approach going forward
 *   (actual_reps/actual_kg stay as a mirror for volume computation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_sets', function (Blueprint $table) {
            $table->decimal('band_kg', 6, 2)->nullable()->after('kg');
        });

        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->json('metrics')->nullable()->after('metric_type');
        });

        Schema::create('stride_set_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('stride_sets')->cascadeOnDelete();
            $table->string('metric', 32); // SetMetric value
            $table->decimal('value', 8, 2);
            $table->timestamps();
            $table->unique(['set_id', 'metric']);
        });

        // Backfill per-exercise metric definitions from the coarse metric_type.
        DB::table('stride_exercises')->select('id', 'name', 'metric_type')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $metrics = match ($row->metric_type) {
                        'reps' => [SetMetric::REPS->value],
                        'hold' => [SetMetric::SECONDS->value, SetMetric::WEIGHT_KG->value],
                        'run', 'sprint' => [SetMetric::DISTANCE_M->value, SetMetric::DURATION_SEC->value],
                        default => [SetMetric::REPS->value, SetMetric::WEIGHT_KG->value], // load, machine
                    };
                    if (preg_match('/band/i', (string) $row->name)) {
                        $metrics[] = SetMetric::BAND_KG->value;
                    }
                    DB::table('stride_exercises')->where('id', $row->id)
                        ->update(['metrics' => json_encode($metrics)]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stride_set_metrics');
        Schema::table('stride_exercises', function (Blueprint $table) {
            $table->dropColumn('metrics');
        });
        Schema::table('stride_sets', function (Blueprint $table) {
            $table->dropColumn('band_kg');
        });
    }
};
