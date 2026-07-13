<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\Exercise;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;
use App\Services\Stride\ExerciseCategory;
use App\Services\Stride\PlanGenerationService;
use App\Services\Stride\SessionVolume;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministically APPLIES a staged proposal (AiAdjustment status='proposed') to
 * the plan when the user confirms. Every payload is re-resolved against live data
 * here (never trusts stale ids), so a proposal that sat for a while still targets
 * the right rows or degrades to a counted no-op. The whole apply is atomic.
 *
 * Block-scoped proposals (block_id set) fan out across EVERY session in the block;
 * today-scoped ones touch a single session.
 */
class ProposalApplyService
{
    /**
     * @return array{ok: bool, result: string, session_ids: array<int>}
     */
    public function apply(User $user, AiAdjustment $proposal): array
    {
        abort_unless($proposal->user_id === $user->id, 404);
        abort_unless($proposal->status === 'proposed', 409, 'This change is no longer pending.');

        return DB::transaction(function () use ($user, $proposal) {
            $touched = [];

            $result = match ($proposal->operation) {
                'set_load' => $this->applySetLoad($user, $proposal, $touched),
                'add_set' => $this->applyAddSet($user, $proposal, $touched),
                'swap' => $this->applySwap($user, $proposal, $touched),
                'reorder' => $this->applyReorder($user, $proposal, $touched),
                'scale_load' => $this->applyScaleLoad($user, $proposal, $touched),
                'regenerate_session' => $this->applyRegenerate($user, $proposal, $touched),
                default => null,
            };

            abort_if($result === null, 422, "Cannot apply operation: {$proposal->operation}.");

            $sessionIds = array_values(array_unique($touched));
            foreach ($sessionIds as $sid) {
                if ($session = Session::find($sid)) {
                    $session->update(['volume_kg' => SessionVolume::recompute($session)]);
                }
            }

            $proposal->update(['status' => 'applied', 'applied_at' => now()]);

            return ['ok' => true, 'result' => $result, 'session_ids' => $sessionIds];
        });
    }

    // ── single-session ops (today) ─────────────────────────────────────────────

    /** payload: { session_id, exercise_name, kg, reps? } */
    private function applySetLoad(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        if ($session === null) {
            return null;
        }
        $exercise = $this->findExercise($session, $payload['exercise_name'] ?? '');
        if ($exercise === null) {
            return "No exercise matching \"{$payload['exercise_name']}\" anymore.";
        }

        $update = ['kg' => (float) $payload['kg']];
        if (isset($payload['reps'])) {
            $update['reps'] = (int) $payload['reps'];
        }
        $exercise->sets()->where('kind', 'Working')->update($update);
        $touched[] = $session->id;

        return "Set {$exercise->name} working sets to {$payload['kg']} kg.";
    }

    /** payload: { session_id, exercise_name, kind, reps, kg } */
    private function applyAddSet(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        if ($session === null) {
            return null;
        }
        $exercise = $this->findExercise($session, $payload['exercise_name'] ?? '');
        if ($exercise === null) {
            return "No exercise matching \"{$payload['exercise_name']}\" anymore.";
        }

        $exercise->sets()->create([
            'kind' => $payload['kind'] ?? 'Working',
            'reps' => (int) ($payload['reps'] ?? 0),
            'kg' => (float) ($payload['kg'] ?? 0),
            'position' => (int) $exercise->sets()->max('position') + 1,
        ]);
        $touched[] = $session->id;

        return "Added a set to {$exercise->name}.";
    }

    /** payload: { session_id, option } — rebuild one session from scratch. */
    private function applyRegenerate(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        if ($session === null) {
            return null;
        }

        app(PlanGenerationService::class)->regenerateInto($user, $session);
        $touched[] = $session->id;

        return "Rebuilt {$session->title}.";
    }

    // ── block-wide ops (fan out across the block) ──────────────────────────────

    /** payload: { from:{name_like}, to:{name, exercise_id?} } */
    private function applySwap(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $fromLike = mb_strtolower(trim($payload['from']['name_like'] ?? ''));
        $toName = trim((string) ($payload['to']['name'] ?? ''));
        if ($fromLike === '' || $toName === '') {
            return null;
        }

        $toExerciseId = $payload['to']['exercise_id']
            ?? Exercise::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($toName)])->value('id');

        $count = 0;
        foreach ($this->targetSessions($user, $proposal) as $session) {
            $matches = $session->exercises()->whereRaw('LOWER(name) LIKE ?', ['%'.$fromLike.'%'])->get();
            foreach ($matches as $exercise) {
                $exercise->update(['name' => $toName, 'exercise_id' => $toExerciseId]);
                $count++;
            }
            if ($matches->isNotEmpty()) {
                $touched[] = $session->id;
            }
        }

        return "Swapped to {$toName} in {$count} place(s).";
    }

    /** payload: { match:{by, value}, position:'first'|'last' } */
    private function applyReorder(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $by = ($payload['match']['by'] ?? 'category');
        $value = (string) ($payload['match']['value'] ?? '');
        $last = ($payload['position'] ?? 'first') === 'last';
        if ($value === '') {
            return null;
        }

        $reordered = 0;
        foreach ($this->targetSessions($user, $proposal) as $session) {
            $exercises = $session->exercises()->orderBy('position')->get();
            $matched = $exercises->filter(fn (SessionExercise $e) => ExerciseCategory::matches($e, $by, $value));
            if ($matched->isEmpty()) {
                continue;
            }
            $rest = $exercises->reject(fn (SessionExercise $e) => ExerciseCategory::matches($e, $by, $value));
            $ordered = $last ? $rest->concat($matched) : $matched->concat($rest);

            $position = 0;
            foreach ($ordered as $exercise) {
                $exercise->update(['position' => $position++]);
            }
            $reordered++;
            $touched[] = $session->id;
        }

        return "Reordered {$reordered} session(s) — {$value} ".($last ? 'last' : 'first').'.';
    }

    /** payload: { pct, only_category? } */
    private function applyScaleLoad(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $pct = (int) ($payload['pct'] ?? 0);
        if ($pct === 0) {
            return null;
        }
        $factor = 1 + $pct / 100;
        $onlyCategory = $payload['only_category'] ?? null;

        $changed = 0;
        foreach ($this->targetSessions($user, $proposal) as $session) {
            $any = false;
            foreach ($session->exercises as $exercise) {
                if ($onlyCategory && ExerciseCategory::of($exercise) !== mb_strtolower($onlyCategory)) {
                    continue;
                }
                foreach ($exercise->sets()->where('kind', 'Working')->where('kg', '>', 0)->get() as $set) {
                    $set->update(['kg' => $this->roundTo((float) $set->kg * $factor, 2.5)]);
                    $changed++;
                    $any = true;
                }
            }
            if ($any) {
                $touched[] = $session->id;
            }
        }

        return "Scaled {$changed} working set(s) by {$pct}%.";
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** Sessions a proposal targets: the whole block (block_id) or a listed subset. */
    private function targetSessions(User $user, AiAdjustment $proposal): Collection
    {
        if ($proposal->block_id) {
            return Session::ownedBy($user)->where('block_id', $proposal->block_id)->with('exercises.sets')->get();
        }

        $payload = $proposal->payload ?? [];
        $ids = isset($payload['session_id']) ? [$payload['session_id']] : ($payload['session_ids'] ?? []);

        return Session::ownedBy($user)->whereIn('id', $ids)->with('exercises.sets')->get();
    }

    private function ownedSession(User $user, ?int $id): ?Session
    {
        return $id ? Session::ownedBy($user)->find($id) : null;
    }

    private function findExercise(Session $session, string $name): ?SessionExercise
    {
        $name = mb_strtolower(trim($name));

        return $name === '' ? null
            : $session->exercises()->whereRaw('LOWER(name) LIKE ?', ['%'.$name.'%'])->first();
    }

    private function roundTo(float $value, float $step): float
    {
        return round($value / $step) * $step;
    }
}
