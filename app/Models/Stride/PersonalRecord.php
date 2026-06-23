<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A personal record: the athlete's current/past best for an exercise. Type-aware
 * via `metric_type` (which fields of `metrics` are meaningful) and dated, since
 * PRs go stale.
 */
class PersonalRecord extends Model
{
    protected $table = 'stride_personal_records';

    protected $fillable = [
        'user_id',
        'exercise_id',
        'label',
        'metric_type',
        'metrics',
        'achieved_on',
        'form_quality',
        'source',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'achieved_on' => 'date',
            'form_quality' => 'integer',
        ];
    }

    /** "form 3/5" when a quality was logged, else null — for AI/list annotations. */
    public function formNote(): ?string
    {
        return $this->form_quality ? "form {$this->form_quality}/5" : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Human-readable value, e.g. "100kg × 5", "12s", "5km · 22:00 (4:24/km)". */
    public function display(): string
    {
        $m = $this->metrics ?? [];
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return match ($this->metric_type) {
            'load' => trim(($m['weight'] ?? null) !== null ? $num($m['weight']).'kg' : '')
                .(($m['reps'] ?? null) ? ' × '.(int) $m['reps'] : '') ?: '—',
            'reps' => ($m['reps'] ?? null) !== null ? ((int) $m['reps']).' reps' : '—',
            'hold' => ($m['seconds'] ?? null) !== null ? self::clock((int) $m['seconds']) : '—',
            'run', 'sprint' => self::distanceTime($m, $num, $this->metric_type === 'run'),
            'machine' => self::machine($m, $num),
            default => '—',
        };
    }

    /** Distance + time (+ derived pace for runs). */
    private static function distanceTime(array $m, callable $num, bool $withPace): string
    {
        $parts = [];
        $dist = $m['distance_m'] ?? null;
        $secs = $m['seconds'] ?? null;
        if ($dist !== null) {
            $parts[] = $dist >= 1000 ? $num($dist / 1000).'km' : ((int) $dist).'m';
        }
        if ($secs !== null) {
            $parts[] = self::clock((int) $secs);
        }
        $out = implode(' · ', $parts) ?: '—';
        if ($withPace && $dist > 0 && $secs > 0) {
            $pacePerKm = (int) round($secs / ($dist / 1000));
            $out .= ' ('.self::clock($pacePerKm).'/km)';
        }

        return $out;
    }

    private static function machine(array $m, callable $num): string
    {
        $parts = [];
        if (($m['calories'] ?? null) !== null) {
            $parts[] = ((int) $m['calories']).' cal';
        }
        if (($m['distance_m'] ?? null) !== null) {
            $parts[] = ((int) $m['distance_m']).'m';
        }
        if (($m['watts'] ?? null) !== null) {
            $parts[] = ((int) $m['watts']).'W';
        }
        if (($m['seconds'] ?? null) !== null) {
            $parts[] = self::clock((int) $m['seconds']);
        }

        return implode(' · ', $parts) ?: '—';
    }

    /** Seconds → "12s" (<60) or "m:ss". */
    private static function clock(int $secs): string
    {
        if ($secs < 60) {
            return $secs.'s';
        }

        return intdiv($secs, 60).':'.str_pad((string) ($secs % 60), 2, '0', STR_PAD_LEFT);
    }
}
