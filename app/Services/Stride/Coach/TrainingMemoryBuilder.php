<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\ExerciseCategory;

/**
 * Builds the coach's context, scoped to one user.
 *
 * The "training memory" block is a COMPACT, DETERMINISTIC snapshot serialised
 * from normalised tables on every request — not raw chat. Because it's small
 * and stable within a conversation it sits behind a prompt-cache breakpoint,
 * so repeated turns are cheap.
 */
class TrainingMemoryBuilder
{
    /**
     * Persona personalities, mirroring the prototype's STRIDE_AI_PERSONAS.
     * The selectable coaches are real people; "Coach" stays the generic product term.
     * Keys are stable ids ('calm' is the default); 'buddy' was merged into 'coach'.
     */
    private const PERSONAS = [
        'calm' => 'You are "Jožo": a steady, supportive, evidence-based coach. Warm but precise.',
        'nerd' => 'You are "Peter": stats-first. Lead with volume, RPE and tonnage. Quantify everything.',
        'coach' => 'You are "Jano": hype-friend energy fused with no-excuses drive — upbeat, motivating, and direct. Short, punchy sentences; an emoji now and then. Push hard, never reckless.',
    ];

    /** Stable system instructions — safe to cache for the whole conversation. */
    public function systemGuide(string $personaKey, string $language = 'en', bool $blockScoped = false): string
    {
        $persona = self::PERSONAS[$personaKey] ?? self::PERSONAS['calm'];

        $guide = <<<TEXT
        You are Stride, a personal strength & conditioning coach inside a training app.
        {$persona}

        Rules:
        - Always program around flagged injuries; never prescribe a movement listed under AVOID.
        - When the user asks to change their training (lighter, heavier, swap, add, reorder, an injury),
          USE THE TOOLS to STAGE the change — do not just describe it. Every change is a PROPOSAL the
          athlete must confirm; never claim a change is already applied. Confirm in one or two sentences.
        - Be concise. Explain the "why" briefly when you change the plan.
        - Use kilograms and the user's metric/imperial preference. Never invent data you weren't given.
        TEXT;

        if ($blockScoped) {
            $guide .= "\n\n".<<<'TEXT'
            BLOCK TOOLS AVAILABLE: you can edit the athlete's current training block (shown in BLOCK BEING
            EDITED below) — block-wide changes apply across ALL its sessions. Use the block tools (reorder_block,
            swap_block, scale_block_load, regenerate_session, change_session_kind) to stage them. Examples:
            "always start with calisthenics first" → reorder_block(match_by="category", match_value="calisthenics",
            position="first"); "start today with Pull instead of Push" → change_session_kind(session_ref=today's
            date, new_kind="Pull") — and one call per other session whose kind shifts (regenerate_session alone
            can NOT change what a day trains). ALWAYS call a tool for a plan change; the athlete confirms each
            proposal before it is applied.
            TEXT;
        }

        if ($language === 'sk') {
            $guide .= "\n\n".<<<'TEXT'
            IMPORTANT — LANGUAGE: Reply to the user ONLY in Slovak (po slovensky), using the informal "ty" (tykanie).
            Keep exercise names, RPE and units (kg) as they are. Tool names and tool ARGUMENTS must stay in English /
            identifiers exactly as defined — never translate them; only your natural-language reply is in Slovak.
            TEXT;
        }

        return $guide;
    }

    /** The per-user, per-request training snapshot. */
    public function memory(User $user): string
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);
        $lines = ['USER TRAINING MEMORY', '===================='];

        $lines[] = sprintf(
            'Profile: %s%s, units=%s, streak=%d days.',
            $profile->weight_kg ? "bodyweight {$profile->weight_kg}kg" : 'bodyweight n/a',
            $profile->goal_weight_kg ? " (goal {$profile->goal_weight_kg}kg)" : '',
            $profile->units,
            $profile->streak_days,
        );

        $block = Block::ownedBy($user)->active()->first();
        if ($block) {
            $lines[] = "Current block: {$block->name} ({$block->phase}), week {$block->week_of} of {$block->weeks}.";
        }

        $lines[] = '';
        $lines[] = $this->todaySection($user);
        $lines[] = '';
        $lines[] = $this->injuriesSection($user);
        $lines[] = '';
        $lines[] = $this->goalsSection($user);
        $lines[] = '';
        $lines[] = $this->prSection($user);

        $facts = CoachMemory::ownedBy($user)->latest('id')->limit(20)->pluck('fact');
        if ($facts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'LEARNED FACTS:';
            foreach ($facts as $fact) {
                $lines[] = "- {$fact}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Per-block snapshot for a block-scoped chat: every session and its exercises
     * (position, resolved category, top working set) so the model can reason about
     * ordering ("calisthenics first"), swaps and loads across the whole block.
     */
    public function blockMemory(Block $block): string
    {
        $block->loadMissing('sessions.exercises.sets');

        $lines = [
            "BLOCK BEING EDITED: {$block->name} ({$block->phase}), {$block->weeks} weeks, {$block->sessions->count()} sessions.",
            'Every change you stage here applies to ALL the sessions below.',
            '',
        ];

        foreach ($block->sessions as $session) {
            $date = $session->scheduled_date?->toDateString() ?? '—';
            $lines[] = "SESSION #{$session->id} — {$session->title} ({$session->kind}, {$session->status}, {$date}):";
            foreach ($session->exercises->sortBy('position') as $exercise) {
                $category = ExerciseCategory::of($exercise) ?? 'uncategorised';
                $top = $exercise->sets->where('kind', 'Working')->first();
                $detail = $top ? sprintf('%d×%g kg', $top->reps, $top->kg) : '—';
                $lines[] = "  {$exercise->position}. {$exercise->name} [{$category}] {$detail}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function todaySection(User $user): string
    {
        $today = Session::ownedBy($user)->where('status', 'today')->with('exercises.sets')->first();

        if ($today === null) {
            return 'TODAY: no active session.';
        }

        $inProgress = $today->started_at !== null;
        $lines = ["TODAY: {$today->title} ({$today->kind}), target {$today->duration_min} min.".($inProgress ? ' IN PROGRESS — the athlete is mid-workout; suggest small live edits (set_load/add_set/remove_set/add_exercise/remove_exercise/swap_exercise), never a rebuild.' : '')];

        foreach ($today->exercises as $exercise) {
            $working = $exercise->sets->where('kind', 'Working');
            $topSet = $working->first();
            $detail = $topSet
                ? sprintf('%d×%g kg × %d sets', $topSet->reps, $topSet->kg, $working->count())
                : 'see plan';
            // Mid-workout: anchor suggestions to what has actually happened.
            $progress = '';
            if ($inProgress) {
                $done = $exercise->sets->where('is_done', true);
                $progress = sprintf(' — done %d/%d sets', $done->count(), $exercise->sets->count());
                if (($last = $done->last()) !== null && ($last->actual_reps !== null || $last->actual_kg !== null)) {
                    $progress .= sprintf(' (last: %s%s)', $last->actual_reps !== null ? $last->actual_reps : '?', $last->actual_kg !== null ? sprintf(' @ %g kg', $last->actual_kg) : '');
                }
            }
            $lines[] = "  - {$exercise->name}: {$detail}{$progress}";
        }

        return implode("\n", $lines);
    }

    private function injuriesSection(User $user): string
    {
        $injuries = Injury::ownedBy($user)->flagged()->get();

        if ($injuries->isEmpty()) {
            return 'INJURIES: none flagged.';
        }

        $lines = ['INJURIES (program around these):'];

        foreach ($injuries as $injury) {
            $avoid = $injury->avoid ? ' AVOID: '.implode(', ', $injury->avoid).'.' : '';
            $safe = $injury->safe ? ' SAFE: '.implode(', ', $injury->safe).'.' : '';
            $lines[] = "- {$injury->body_part} — {$injury->label} ({$injury->severity}, {$injury->status}).{$avoid}{$safe}";
        }

        return implode("\n", $lines);
    }

    private function prSection(User $user): string
    {
        $prs = PersonalRecord::ownedBy($user)->orderByDesc('achieved_on')->orderByDesc('id')->limit(15)->get();

        if ($prs->isEmpty()) {
            return 'PERSONAL RECORDS: none logged.';
        }

        $lines = ['PERSONAL RECORDS (favour recent; old ones may be stale):'];
        foreach ($prs as $pr) {
            $when = $pr->achieved_on ? ' — '.$pr->achieved_on->format('Y-m') : '';
            $form = $pr->formNote() ? ' ('.$pr->formNote().')' : '';
            $lines[] = "- {$pr->label}: {$pr->display()}{$form}{$when}";
        }

        return implode("\n", $lines);
    }

    private function goalsSection(User $user): string
    {
        $goals = Goal::ownedBy($user)->where('is_achieved', false)->orderByDesc('progress')->get();

        if ($goals->isEmpty()) {
            return 'GOALS: none set.';
        }

        $lines = ['GOALS:'];

        foreach ($goals as $goal) {
            $pct = round($goal->progress * 100);
            $lines[] = "- {$goal->title} — {$pct}% ({$goal->current_label} → {$goal->target_label}).";
        }

        return implode("\n", $lines);
    }
}
