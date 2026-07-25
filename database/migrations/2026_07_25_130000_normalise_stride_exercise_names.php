<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time catalogue cleanup:
 *   1. "Dumbbell" → "Dumbell" everywhere it is shown to the athlete (exercise
 *      names, equipment labels/keys, descriptions, session snapshots, PR labels,
 *      spot equipment lists). Slugs and video/thumbnail URLs keep the old
 *      spelling — they are identities and live S3 paths.
 *   2. The dumbbell stiff-leg deadlift is a Romanian deadlift with dumbells, so
 *      it is renamed to say so (matching the barbell entry, whose own source
 *      slug is literally "stiff-leg-deadlift-aka-romanian-deadlift").
 *
 * Done in PHP rather than SQL replace() so it behaves identically on pgsql and
 * on the sqlite test database, JSON columns included.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMES = [
        'Dumbbell Stiff Leg Deadlift' => 'Dumbell Romanian Deadlift',
        'Dumbbell Stiff Leg Deadlift (On Bench)' => 'Dumbell Romanian Deadlift (On Bench)',
        'Dumbell Stiff Leg Deadlift' => 'Dumbell Romanian Deadlift',
        'Dumbell Stiff Leg Deadlift (On Bench)' => 'Dumbell Romanian Deadlift (On Bench)',
    ];

    public function up(): void
    {
        $this->rewrite('stride_exercises', ['name', 'equipment_label', 'description']);
        $this->rewrite('stride_equipment', ['name', 'key']);
        $this->rewrite('stride_session_exercises', ['name']);
        $this->rewrite('stride_personal_records', ['label']);
        $this->rewriteJsonList('stride_spots', 'equipment');
    }

    public function down(): void
    {
        // One-way cleanup: the old spelling is the thing being removed.
    }

    /** Rewrite plain text columns row by row. */
    private function rewrite(string $table, array $columns): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $update = [];
                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;
                    if (! is_string($value)) {
                        continue;
                    }
                    $new = $this->fix($value);
                    if ($new !== $value) {
                        $update[$column] = $new;
                    }
                }
                if ($update !== []) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            }
        });
    }

    /** Rewrite a JSON column holding a flat list of labels (spot equipment). */
    private function rewriteJsonList(string $table, string $column): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
            foreach ($rows as $row) {
                $raw = $row->{$column} ?? null;
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                $list = json_decode($raw, true);
                if (! is_array($list)) {
                    continue;
                }
                $new = array_map(fn ($v) => is_string($v) ? $this->fix($v) : $v, $list);
                if ($new !== $list) {
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($new)]);
                }
            }
        });
    }

    private function fix(string $value): string
    {
        $value = str_replace(
            ['Dumbbell', 'dumbbell', 'DUMBBELL'],
            ['Dumbell', 'dumbell', 'DUMBELL'],
            $value,
        );

        // Then the stiff-leg → Romanian rename, on the already-respelled text.
        return strtr($value, self::RENAMES);
    }
};
