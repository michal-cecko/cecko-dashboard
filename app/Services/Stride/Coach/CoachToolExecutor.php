<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;

/**
 * Executes coach tool calls against the user's data, writing an AiAdjustment
 * row (the "why") for every change that mutates the plan. Everything is
 * user-scoped — a tool can only ever touch the calling user's records.
 *
 * Each execute() returns ['result' => string, 'adjustment' => ?AiAdjustment].
 * `result` is the tool_result text fed back to the model.
 */
class CoachToolExecutor
{
    public function execute(User $user, string $tool, array $input): array
    {
        return match ($tool) {
            'set_load' => $this->setLoad($user, $input),
            'swap_exercise' => $this->swapExercise($user, $input),
            'add_set' => $this->addSet($user, $input),
            'log_injury' => $this->logInjury($user, $input),
            'remember_fact' => $this->rememberFact($user, $input),
            default => ['result' => "Unknown tool: {$tool}.", 'adjustment' => null],
        };
    }

    private function setLoad(User $user, array $input): array
    {
        [$session, $exercise] = $this->findExercise($user, $input['exercise_name'] ?? '');

        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '');
        }

        $kg = (float) $input['kg'];
        $reps = isset($input['reps']) ? (int) $input['reps'] : null;

        $before = (float) ($exercise->sets()->where('kind', 'Working')->value('kg') ?? 0);
        $exercise->sets()->where('kind', 'Working')->update(array_filter([
            'kg' => $kg,
            'reps' => $reps,
        ], fn ($v) => $v !== null));

        $repsLabel = $reps !== null ? " × {$reps}" : '';
        $kind = $kg < $before ? 'Lowered intensity' : 'Raised intensity';
        $text = "{$exercise->name} → {$kg} kg{$repsLabel}";

        $adjustment = $this->logAdjustment($user, $session, $kind, $text, $input['reason'] ?? null);

        return ['result' => "Updated {$exercise->name} working sets to {$kg} kg{$repsLabel}.", 'adjustment' => $adjustment];
    }

    private function swapExercise(User $user, array $input): array
    {
        [$session, $exercise] = $this->findExercise($user, $input['from_exercise'] ?? '');

        if ($exercise === null) {
            return $this->miss($input['from_exercise'] ?? '');
        }

        $to = (string) $input['to_exercise'];
        $from = $exercise->name;
        $exercise->update(['name' => $to, 'exercise_id' => null]);

        $adjustment = $this->logAdjustment($user, $session, 'Swapped', "{$from} → {$to}", $input['reason'] ?? null);

        return ['result' => "Swapped {$from} for {$to} in today's session.", 'adjustment' => $adjustment];
    }

    private function addSet(User $user, array $input): array
    {
        [$session, $exercise] = $this->findExercise($user, $input['exercise_name'] ?? '');

        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '');
        }

        $position = (int) $exercise->sets()->max('position') + 1;
        $exercise->sets()->create([
            'kind' => $input['kind'] ?? 'Working',
            'reps' => (int) ($input['reps'] ?? 0),
            'kg' => (float) ($input['kg'] ?? 0),
            'position' => $position,
        ]);

        $adjustment = $this->logAdjustment($user, $session, 'Added', "Added a set to {$exercise->name}", $input['reason'] ?? null);

        return ['result' => "Added a set to {$exercise->name}.", 'adjustment' => $adjustment];
    }

    private function logInjury(User $user, array $input): array
    {
        $injury = Injury::create([
            'user_id' => $user->id,
            'body_part' => (string) $input['body_part'],
            'label' => $input['note'] ?? null,
            'severity' => $input['severity'] ?? 'Mild',
            'status' => 'monitoring',
            'since' => now()->toDateString(),
            'note' => $input['note'] ?? null,
            'avoid' => $input['avoid'] ?? [],
        ]);

        $injury->journalEntries()->create([
            'entry_date' => now()->toDateString(),
            'trend' => 'same',
            'text' => $input['note'] ?? 'Flagged via coach.',
        ]);

        $adjustment = $this->logAdjustment(
            $user, null, 'Logged injury', "Flagged {$injury->body_part}", $input['note'] ?? null,
        );

        return ['result' => "Logged {$injury->body_part}; I'll program around it.", 'adjustment' => $adjustment];
    }

    private function rememberFact(User $user, array $input): array
    {
        CoachMemory::create([
            'user_id' => $user->id,
            'fact' => (string) $input['fact'],
            'source' => 'coach',
            'last_used_at' => now(),
        ]);

        return ['result' => 'Noted — I will remember that.', 'adjustment' => null];
    }

    /**
     * Locate an exercise in the user's active session by fuzzy name match.
     *
     * @return array{0: ?Session, 1: ?SessionExercise}
     */
    private function findExercise(User $user, string $name): array
    {
        $session = Session::ownedBy($user)->where('status', 'today')->first();

        if ($session === null || $name === '') {
            return [$session, null];
        }

        $exercise = $session->exercises()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
            ->first();

        return [$session, $exercise];
    }

    private function miss(string $name): array
    {
        return ['result' => "No exercise matching \"{$name}\" in today's session.", 'adjustment' => null];
    }

    private function logAdjustment(User $user, ?Session $session, string $kind, string $text, ?string $why): AiAdjustment
    {
        return AiAdjustment::create([
            'user_id' => $user->id,
            'session_id' => $session?->id,
            'scope' => 'today',
            'kind' => $kind,
            'target' => $session ? "Today · {$session->kind}" : 'Profile',
            'text' => $text,
            'why' => $why,
            'source' => 'coach',
        ]);
    }
}
