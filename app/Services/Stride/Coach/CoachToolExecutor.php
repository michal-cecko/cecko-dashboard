<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;

/**
 * Executes coach tool calls. Plan EDITS are never applied here — they are STAGED
 * as an AiAdjustment (status='proposed') with a machine `payload`; the user
 * confirms and ProposalApplyService applies it. Inputs/records that aren't plan
 * edits (logging an injury, remembering a fact) still happen immediately.
 *
 * Each execute() returns ['result' => string, 'adjustment' => ?AiAdjustment]; the
 * `result` text is fed back to the model so it phrases its reply as a proposal.
 */
class CoachToolExecutor
{
    public function execute(User $user, string $tool, array $input, CoachContext $ctx): array
    {
        return match ($tool) {
            'set_load' => $this->setLoad($user, $input, $ctx),
            'swap_exercise' => $this->swapExercise($user, $input, $ctx),
            'add_set' => $this->addSet($user, $input, $ctx),
            'reorder_block' => $this->reorderBlock($user, $input, $ctx),
            'swap_block' => $this->swapBlock($user, $input, $ctx),
            'scale_block_load' => $this->scaleBlockLoad($user, $input, $ctx),
            'regenerate_session' => $this->regenerateSession($user, $input, $ctx),
            'log_injury' => $this->logInjury($user, $input),
            'remember_fact' => $this->rememberFact($user, $input),
            default => ['result' => "Unknown tool: {$tool}.", 'adjustment' => null],
        };
    }

    private function reorderBlock(User $user, array $input, CoachContext $ctx): array
    {
        if ($ctx->block === null) {
            return ['result' => 'No block in context.', 'adjustment' => null];
        }
        $by = ($input['match_by'] ?? 'category') === 'name' ? 'name' : 'category';
        $value = trim((string) ($input['match_value'] ?? ''));
        $position = ($input['position'] ?? 'first') === 'last' ? 'last' : 'first';
        if ($value === '') {
            return ['result' => 'Need a category or movement to reorder by.', 'adjustment' => null];
        }

        $proposal = $this->propose(
            $user, $ctx, 'reorder', 'Reordered',
            "Put {$value} {$position} in every session", $input['reason'] ?? null, null,
            ['match' => ['by' => $by, 'value' => $value], 'position' => $position],
        );

        return ['result' => "Proposed: move {$value} to the {$position} of every session — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function swapBlock(User $user, array $input, CoachContext $ctx): array
    {
        if ($ctx->block === null) {
            return ['result' => 'No block in context.', 'adjustment' => null];
        }
        $from = trim((string) ($input['from_exercise'] ?? ''));
        $to = trim((string) ($input['to_exercise'] ?? ''));
        if ($from === '' || $to === '') {
            return ['result' => 'Need both the exercise to replace and its replacement.', 'adjustment' => null];
        }

        $proposal = $this->propose(
            $user, $ctx, 'swap', 'Swapped',
            "{$from} → {$to} (whole block)", $input['reason'] ?? null, null,
            ['from' => ['name_like' => $from], 'to' => ['name' => $to]],
        );

        return ['result' => "Proposed: swap {$from} for {$to} across the whole block — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function scaleBlockLoad(User $user, array $input, CoachContext $ctx): array
    {
        if ($ctx->block === null) {
            return ['result' => 'No block in context.', 'adjustment' => null];
        }
        $pct = (int) ($input['percent'] ?? 0);
        if ($pct === 0) {
            return ['result' => 'The percentage must be non-zero.', 'adjustment' => null];
        }
        $category = trim((string) ($input['only_category'] ?? ''));
        $sign = $pct > 0 ? '+' : '';
        $kind = $pct < 0 ? 'Lowered intensity' : 'Raised intensity';
        $text = "Working loads {$sign}{$pct}%".($category ? " ({$category})" : '').' across the block';

        $proposal = $this->propose(
            $user, $ctx, 'scale_load', $kind, $text, $input['reason'] ?? null, null,
            array_filter(['pct' => $pct, 'only_category' => $category ?: null], fn ($v) => $v !== null),
        );

        return ['result' => "Proposed: scale working loads {$sign}{$pct}% across the block — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function regenerateSession(User $user, array $input, CoachContext $ctx): array
    {
        if ($ctx->block === null) {
            return ['result' => 'No block in context.', 'adjustment' => null];
        }
        $session = $this->resolveBlockSession($ctx->block, (string) ($input['session_ref'] ?? ''));
        if ($session === null) {
            return ['result' => "No session matching \"{$input['session_ref']}\" in this block.", 'adjustment' => null];
        }

        $proposal = $this->propose(
            $user, $ctx, 'regenerate_session', 'Rebuilt',
            "Rebuild {$session->title} ({$session->kind})", $input['reason'] ?? null, null,
            ['session_id' => $session->id],
        );

        return ['result' => "Proposed: rebuild {$session->title} from scratch — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function resolveBlockSession(Block $block, string $ref): ?Session
    {
        $ref = mb_strtolower(trim($ref));
        if ($ref === '') {
            return null;
        }
        $block->loadMissing('sessions');

        return $block->sessions->first(fn (Session $s) => str_contains(mb_strtolower($s->title), $ref)
            || str_contains(mb_strtolower($s->kind), $ref)
            || ($s->scheduled_date && str_contains($s->scheduled_date->toDateString(), $ref)));
    }

    private function setLoad(User $user, array $input, CoachContext $ctx): array
    {
        $session = $ctx->todaySession;
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '');
        }

        $kg = (float) $input['kg'];
        $reps = isset($input['reps']) ? (int) $input['reps'] : null;
        $before = (float) ($exercise->sets()->where('kind', 'Working')->value('kg') ?? 0);
        $repsLabel = $reps !== null ? " × {$reps}" : '';
        $kind = $kg < $before ? 'Lowered intensity' : 'Raised intensity';

        $proposal = $this->propose(
            $user, $ctx, 'set_load', $kind,
            "{$exercise->name} → {$kg} kg{$repsLabel}", $input['reason'] ?? null, $session,
            array_filter([
                'session_id' => $session->id,
                'exercise_name' => $exercise->name,
                'kg' => $kg,
                'reps' => $reps,
            ], fn ($v) => $v !== null),
        );

        return ['result' => "Proposed: set {$exercise->name} to {$kg} kg{$repsLabel} — awaiting the athlete's confirmation.", 'adjustment' => $proposal];
    }

    private function swapExercise(User $user, array $input, CoachContext $ctx): array
    {
        $session = $ctx->todaySession;
        $exercise = $this->findExercise($session, $input['from_exercise'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['from_exercise'] ?? '');
        }

        $to = trim((string) $input['to_exercise']);
        $from = $exercise->name;

        $proposal = $this->propose(
            $user, $ctx, 'swap', 'Swapped',
            "{$from} → {$to}", $input['reason'] ?? null, $session,
            [
                'session_ids' => [$session->id],
                'from' => ['name_like' => $from],
                'to' => ['name' => $to],
            ],
        );

        return ['result' => "Proposed: swap {$from} for {$to} — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function addSet(User $user, array $input, CoachContext $ctx): array
    {
        $session = $ctx->todaySession;
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '');
        }

        $proposal = $this->propose(
            $user, $ctx, 'add_set', 'Added',
            "Add a set to {$exercise->name}", $input['reason'] ?? null, $session,
            [
                'session_id' => $session->id,
                'exercise_name' => $exercise->name,
                'kind' => $input['kind'] ?? 'Working',
                'reps' => (int) ($input['reps'] ?? 0),
                'kg' => (float) ($input['kg'] ?? 0),
            ],
        );

        return ['result' => "Proposed: add a set to {$exercise->name} — awaiting confirmation.", 'adjustment' => $proposal];
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

        // Recording an injury is an input, not a plan edit — applied immediately.
        $adjustment = AiAdjustment::create([
            'user_id' => $user->id,
            'scope' => 'today',
            'status' => 'applied',
            'kind' => 'Logged injury',
            'target' => 'Profile',
            'text' => "Flagged {$injury->body_part}",
            'why' => $input['note'] ?? null,
            'applied_at' => now(),
            'source' => 'coach',
        ]);

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

    /** Stage a plan edit as a pending proposal (no mutation happens until the user applies it). */
    private function propose(
        User $user,
        CoachContext $ctx,
        string $operation,
        string $kind,
        string $text,
        ?string $why,
        ?Session $session,
        array $payload,
    ): AiAdjustment {
        return AiAdjustment::create([
            'user_id' => $user->id,
            'session_id' => $ctx->block !== null ? null : $session?->id,
            'block_id' => $ctx->block?->id,
            'conversation_id' => $ctx->conversation?->id,
            'scope' => $ctx->block !== null ? 'block' : 'today',
            'status' => 'proposed',
            'kind' => $kind,
            'operation' => $operation,
            'target' => $ctx->block !== null ? "Block · {$ctx->block->name}" : ($session ? "Today · {$session->kind}" : 'Profile'),
            'text' => $text,
            'why' => $why,
            'payload' => $payload,
            'source' => 'coach',
        ]);
    }

    private function findExercise(?Session $session, string $name): ?SessionExercise
    {
        $name = mb_strtolower(trim($name));
        if ($session === null || $name === '') {
            return null;
        }

        return $session->exercises()->whereRaw('LOWER(name) LIKE ?', ['%'.$name.'%'])->first();
    }

    private function miss(string $name): array
    {
        return ['result' => "No exercise matching \"{$name}\" in today's session.", 'adjustment' => null];
    }
}
