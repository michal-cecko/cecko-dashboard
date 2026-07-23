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
        $outcome = $this->executeTool($user, $tool, $input, $ctx);

        $adjustment = $outcome['adjustment'];
        if ($adjustment !== null && ! $adjustment->wasRecentlyCreated && $adjustment->status === 'proposed') {
            $outcome['result'] = "Already staged: {$adjustment->text} — awaiting the athlete's confirmation. Do NOT stage this change again.";
        }

        return $outcome;
    }

    private function executeTool(User $user, string $tool, array $input, CoachContext $ctx): array
    {
        return match ($tool) {
            'set_load' => $this->setLoad($user, $input, $ctx),
            'swap_exercise' => $this->swapExercise($user, $input, $ctx),
            'add_set' => $this->addSet($user, $input, $ctx),
            'remove_set' => $this->removeSet($user, $input, $ctx),
            'remove_exercise' => $this->removeExercise($user, $input, $ctx),
            'reorder_block' => $this->reorderBlock($user, $input, $ctx),
            'swap_block' => $this->swapBlock($user, $input, $ctx),
            'scale_block_load' => $this->scaleBlockLoad($user, $input, $ctx),
            'regenerate_session' => $this->regenerateSession($user, $input, $ctx),
            'change_session_kind' => $this->changeSessionKind($user, $input, $ctx),
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

    private function changeSessionKind(User $user, array $input, CoachContext $ctx): array
    {
        if ($ctx->block === null) {
            return ['result' => 'No block in context.', 'adjustment' => null];
        }
        $session = $this->resolveBlockSession($ctx->block, (string) ($input['session_ref'] ?? ''));
        if ($session === null) {
            return ['result' => "No session matching \"{$input['session_ref']}\" in this block.", 'adjustment' => null];
        }
        $newKind = trim((string) ($input['new_kind'] ?? ''));
        if (! in_array($newKind, ['Push', 'Pull', 'Legs', 'Upper', 'Lower', 'Full body'], true)) {
            return ['result' => "Unsupported session kind: \"{$newKind}\".", 'adjustment' => null];
        }
        if ($newKind === $session->kind) {
            return ['result' => "{$session->title} already trains {$newKind}.", 'adjustment' => null];
        }

        $day = $session->scheduled_date?->toDateString() ?? $session->title;
        $proposal = $this->propose(
            $user, $ctx, 'change_session_kind', 'Reordered',
            "{$session->kind} → {$newKind} on {$day}", $input['reason'] ?? null, null,
            ['session_id' => $session->id, 'new_kind' => $newKind],
        );

        return ['result' => "Proposed: change {$session->title} from {$session->kind} to {$newKind} (the session gets rebuilt) — awaiting confirmation.", 'adjustment' => $proposal];
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
        $session = $this->targetSession($ctx, $input);
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '', $input);
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
        $session = $this->targetSession($ctx, $input);
        $exercise = $this->findExercise($session, $input['from_exercise'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['from_exercise'] ?? '', $input);
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
        $session = $this->targetSession($ctx, $input);
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '', $input);
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

    private function removeSet(User $user, array $input, CoachContext $ctx): array
    {
        $session = $this->targetSession($ctx, $input);
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '', $input);
        }

        $proposal = $this->propose(
            $user, $ctx, 'remove_set', 'Reduced volume',
            "Drop a set from {$exercise->name}", $input['reason'] ?? null, $session,
            ['session_id' => $session->id, 'exercise_name' => $exercise->name],
        );

        return ['result' => "Proposed: drop the last remaining set of {$exercise->name} — awaiting confirmation.", 'adjustment' => $proposal];
    }

    private function removeExercise(User $user, array $input, CoachContext $ctx): array
    {
        $session = $this->targetSession($ctx, $input);
        $exercise = $this->findExercise($session, $input['exercise_name'] ?? '');
        if ($exercise === null) {
            return $this->miss($input['exercise_name'] ?? '', $input);
        }

        $proposal = $this->propose(
            $user, $ctx, 'remove_exercise', 'Cut short',
            "Drop {$exercise->name} from the session", $input['reason'] ?? null, $session,
            ['session_id' => $session->id, 'exercise_name' => $exercise->name],
        );

        return ['result' => "Proposed: drop {$exercise->name} from the session — awaiting confirmation.", 'adjustment' => $proposal];
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
        // Scope by the OPERATION, not by which chat staged it: block-wide tools
        // never pass a $session, today-tools always do. The general chat carries
        // the active block in $ctx, so a set_load there must still scope 'today'.
        $blockWide = $session === null && $ctx->block !== null;

        // The model can call the same tool repeatedly within one reply (or across
        // turns) — an identical still-pending proposal is reused, never duplicated.
        $existing = AiAdjustment::query()
            ->where('user_id', $user->id)
            ->where('status', 'proposed')
            ->where('operation', $operation)
            ->where('scope', $blockWide ? 'block' : 'today')
            ->where('text', $text)
            ->where('block_id', $blockWide ? $ctx->block->id : null)
            ->where('session_id', $blockWide ? null : $session?->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return AiAdjustment::create([
            'user_id' => $user->id,
            'session_id' => $blockWide ? null : $session?->id,
            'block_id' => $blockWide ? $ctx->block->id : null,
            'conversation_id' => $ctx->conversation?->id,
            'scope' => $blockWide ? 'block' : 'today',
            'status' => 'proposed',
            'kind' => $kind,
            'operation' => $operation,
            'target' => $blockWide ? "Block · {$ctx->block->name}" : ($session ? "Today · {$session->kind}" : 'Profile'),
            'text' => $text,
            'why' => $why,
            'payload' => $payload,
            'source' => 'coach',
        ]);
    }

    /**
     * The session a per-session tool targets: an explicit session_ref resolves
     * against the block in context (any day is editable from any chat); without
     * one, today's session — the pre-session_ref behaviour.
     */
    private function targetSession(CoachContext $ctx, array $input): ?Session
    {
        $ref = trim((string) ($input['session_ref'] ?? ''));
        if ($ref !== '' && $ctx->block !== null) {
            return $this->resolveBlockSession($ctx->block, $ref);
        }

        return $ctx->todaySession;
    }

    private function findExercise(?Session $session, string $name): ?SessionExercise
    {
        $name = mb_strtolower(trim($name));
        if ($session === null || $name === '') {
            return null;
        }

        return $session->exercises()->whereRaw('LOWER(name) LIKE ?', ['%'.$name.'%'])->first();
    }

    private function miss(string $name, array $input = []): array
    {
        $where = trim((string) ($input['session_ref'] ?? '')) !== ''
            ? "the session matching \"{$input['session_ref']}\""
            : "today's session";

        return ['result' => "No exercise matching \"{$name}\" in {$where}.", 'adjustment' => null];
    }
}
