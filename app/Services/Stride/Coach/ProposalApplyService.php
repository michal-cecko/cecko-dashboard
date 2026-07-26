<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\Exercise;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\ExerciseCategory;
use App\Services\Stride\PlanGenerationService;
use App\Services\Stride\SessionVolume;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'add_exercise' => $this->applyAddExercise($user, $proposal, $touched),
                'remove_set' => $this->applyRemoveSet($user, $proposal, $touched),
                'remove_exercise' => $this->applyRemoveExercise($user, $proposal, $touched),
                'swap' => $this->applySwap($user, $proposal, $touched),
                'reorder' => $this->applyReorder($user, $proposal, $touched),
                'scale_load' => $this->applyScaleLoad($user, $proposal, $touched),
                'regenerate_session' => $this->applyRegenerate($user, $proposal, $touched),
                'change_session_kind' => $this->applyChangeKind($user, $proposal, $touched),
                'set_warmup_style' => $this->applyWarmupStyle($user, $proposal, $touched),
                'move_session' => $this->applyMoveSession($user, $proposal, $touched),
                'log_past_session' => $this->applyLogPastSession($user, $proposal, $touched),
                'shift_plan' => $this->applyShiftPlan($user, $proposal, $touched),
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

            // Twin proposals staging the exact same edit are now moot — dismiss
            // them so the user never confirms (or fails to confirm) a change twice.
            AiAdjustment::query()->pendingDuplicatesOf($proposal)->update(['status' => 'dismissed']);

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

    /** payload: { session_id, name, tag, sets, reps, kg } — append a new exercise + default working sets. */
    private function applyAddExercise(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        if ($session === null) {
            return null;
        }
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return 'No exercise name was given.';
        }

        $sets = max(1, min(6, (int) ($payload['sets'] ?? 3)));
        $reps = max(1, min(50, (int) ($payload['reps'] ?? 8)));
        $kg = (float) ($payload['kg'] ?? 0);

        $exercise = $session->exercises()->create([
            'exercise_id' => Exercise::query()->where('name', $name)->value('id'),
            'name' => $name,
            'tag' => in_array($payload['tag'] ?? '', ['Compound', 'Isolation'], true) ? $payload['tag'] : 'Compound',
            'note' => '',
            'position' => (int) $session->exercises()->max('position') + 1,
        ]);

        for ($i = 0; $i < $sets; $i++) {
            $exercise->sets()->create([
                'kind' => 'Working', 'reps' => $reps, 'kg' => $kg, 'rest_sec' => 90, 'position' => $i,
            ]);
        }
        $touched[] = $session->id;

        return "Added {$name} ({$sets}×{$reps}) to the session.";
    }

    /** payload: { session_id, exercise_name } — drop the last not-done set. */
    private function applyRemoveSet(User $user, AiAdjustment $proposal, array &$touched): ?string
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

        // Never delete performed work — only the last still-pending set goes.
        $set = $exercise->sets()->where('is_done', false)->orderByDesc('position')->first();
        if ($set === null) {
            return "All sets of {$exercise->name} are already done — nothing to drop.";
        }
        $set->delete();
        $touched[] = $session->id;

        return "Dropped a set from {$exercise->name}.";
    }

    /** payload: { session_id, exercise_name } — drop a whole exercise ("cut it short"). */
    private function applyRemoveExercise(User $user, AiAdjustment $proposal, array &$touched): ?string
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
        if ($exercise->sets()->where('is_done', false)->doesntExist()) {
            return "{$exercise->name} is already fully done — nothing to drop.";
        }

        $name = $exercise->name;
        $exercise->delete(); // cascades sets + their metric rows
        $touched[] = $session->id;

        return "Dropped {$name} from the session.";
    }

    /** payload: { session_id, option } — rebuild one session from scratch. */
    private function applyRegenerate(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        if ($session === null) {
            return null;
        }
        if ($refusal = $this->rebuildRefusal($session, 'a full rebuild')) {
            return $refusal;
        }

        app(PlanGenerationService::class)->regenerateInto($user, $session);
        $touched[] = $session->id;

        return "Rebuilt {$session->title}.";
    }

    /**
     * payload: { style } — flip the warm-up structure for the whole plan. Saves the
     * preference (so newly generated sessions follow it) and restructures today's +
     * every upcoming UNSTARTED session, exactly like the Training-preferences toggle.
     */
    private function applyWarmupStyle(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $style = ($proposal->payload['style'] ?? '') === 'grouped' ? 'grouped' : 'per_exercise';

        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->preferences = array_merge($profile->preferences ?? [], ['warmup_style' => $style]);
        $profile->save();

        $planner = app(PlanGenerationService::class);
        $sessions = $planner->restructurableSessions($user);

        foreach ($sessions as $session) {
            $planner->applyWarmupStyle($user, $session, $style);
            $touched[] = $session->id;
        }

        $label = $style === 'grouped' ? 'one block before the session' : 'a set on each exercise';

        return "Warm-ups are now {$label} — updated {$sessions->count()} upcoming ".Str::plural('session', $sessions->count()).'.';
    }

    /** payload: { session_id, date } — same rules as the app's Move action. */
    private function applyMoveSession(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        $date = (string) ($payload['date'] ?? '');
        if ($session === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $target = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        $session->forceFill([
            'scheduled_date' => $target,
            // Landing on today makes it today's session again; anything else is planned.
            'status' => $session->status === 'done' ? 'done' : ($target->isToday() ? 'today' : 'planned'),
            'skip_reason' => null,
        ])->save();
        $touched[] = $session->id;

        return "Moved {$session->title} to {$target->isoFormat('ddd D MMM')}.";
    }

    /**
     * payload: { session_id, date, rpe? } — mark a session done on a PAST (or
     * today's) date (already trained). scheduled_date drives where history places
     * it, so it moves to the trained day; completed_at anchors the completion.
     */
    private function applyLogPastSession(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        $date = (string) ($payload['date'] ?? '');
        if ($session === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $when = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        // You can only have "already done" today or a past session, never a future one.
        if ($when->isAfter(today())) {
            return null;
        }

        $session->forceFill([
            'status' => 'done',
            'scheduled_date' => $when,
            'started_at' => $session->started_at ?? $when,
            'completed_at' => $when->copy()->endOfDay(),
            'skip_reason' => null,
        ]);
        if (isset($payload['rpe']) && $payload['rpe'] !== '' && $payload['rpe'] !== null) {
            $session->rpe = (int) $payload['rpe'];
        }
        $session->save();
        $touched[] = $session->id;

        return "Logged {$session->title} as done on {$when->isoFormat('ddd D MMM')}.";
    }

    /**
     * payload: { days, from } — slide the remaining plan, order and spacing kept.
     * Only upcoming, unstarted sessions move; finished work stays on its date.
     */
    private function applyShiftPlan(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $days = (int) ($payload['days'] ?? 0);
        if ($days === 0) {
            return null;
        }
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($payload['from'] ?? ''))
            ? Carbon::createFromFormat('Y-m-d', $payload['from'])->startOfDay()
            : today();

        $sessions = Session::ownedBy($user)
            ->whereHas('block', fn ($q) => $q->where('status', 'active'))
            ->whereIn('status', ['today', 'planned'])
            ->whereNull('started_at')
            ->whereDate('scheduled_date', '>=', $from)
            ->orderBy('scheduled_date')
            ->get();

        $moved = 0;
        foreach ($sessions as $session) {
            $target = $session->scheduled_date->copy()->addDays($days)->startOfDay();
            // Never shove a session into the past — clamp at today.
            if ($target->isBefore(today())) {
                $target = today();
            }
            $session->forceFill([
                'scheduled_date' => $target,
                'status' => $target->isToday() ? 'today' : 'planned',
            ])->save();
            $touched[] = $session->id;
            $moved++;
        }

        $label = abs($days).' '.(abs($days) === 1 ? 'day' : 'days').' '.($days < 0 ? 'earlier' : 'later');

        return "Shifted {$moved} upcoming ".Str::plural('session', $moved)." {$label}.";
    }

    /** payload: { session_id, new_kind } — retarget what the session trains, then rebuild it */
    private function applyChangeKind(User $user, AiAdjustment $proposal, array &$touched): ?string
    {
        $payload = $proposal->payload ?? [];
        $session = $this->ownedSession($user, $payload['session_id'] ?? null);
        $newKind = trim((string) ($payload['new_kind'] ?? ''));
        if ($session === null || $newKind === '') {
            return null;
        }
        // Changing the kind rebuilds the session, so the same guard applies.
        if ($refusal = $this->rebuildRefusal($session, 'changing what it trains')) {
            return $refusal;
        }

        $oldKind = $session->kind;
        $session->update(['kind' => $newKind]);
        app(PlanGenerationService::class)->regenerateInto($user, $session->refresh());
        $touched[] = $session->id;

        return "Changed {$session->title} from {$oldKind} to {$newKind} and rebuilt it.";
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

    /**
     * Why a rebuild must be refused, or null when it's safe. A rebuild deletes the
     * exercises and their sets, so both change_session_kind and regenerate_session
     * have to stop for work that is mid-flight OR already logged — the latter is
     * how a finished session once got wiped by a "move" that changed its kind.
     */
    private function rebuildRefusal(Session $session, string $what): ?string
    {
        if ($session->started_at !== null && $session->status !== 'done') {
            return "{$session->title} is in progress — {$what} would wipe logged work. Adjust it with smaller edits instead.";
        }

        $logged = $session->exercises()
            ->whereHas('sets', fn ($q) => $q->where('is_done', true))
            ->exists();

        return $logged
            ? "{$session->title} already has logged sets — {$what} would erase them. To put a workout on another day use move_session."
            : null;
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
