<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\Goal;
use App\Models\Stride\Injury;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;

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
    /** Persona personalities, mirroring the prototype's STRIDE_AI_PERSONAS. */
    private const PERSONAS = [
        'coach' => 'You are "Coach": direct, no-nonsense, motivating. Short, punchy sentences. No fluff.',
        'calm' => 'You are "Trainer": steady, supportive, evidence-based. Warm but precise.',
        'nerd' => 'You are "Analyst": stats-first. Lead with volume, RPE and tonnage. Quantify everything.',
        'buddy' => 'You are "Riley": hype-friend energy, upbeat, casual. Emoji are welcome, sparingly.',
    ];

    /** Stable system instructions — safe to cache for the whole conversation. */
    public function systemGuide(string $personaKey): string
    {
        $persona = self::PERSONAS[$personaKey] ?? self::PERSONAS['calm'];

        return <<<TEXT
        You are Stride, a personal strength & conditioning coach inside a training app.
        {$persona}

        Rules:
        - Always program around flagged injuries; never prescribe a movement listed under AVOID.
        - When the user asks to change today's training (lighter, heavier, swap, add, an injury),
          USE THE TOOLS to apply the change — do not just describe it. Then confirm in one or two sentences.
        - Never say a change was applied unless you actually called a tool for it in this turn,
          even if earlier messages in the conversation phrased it differently.
        - Be concise. Explain the "why" briefly when you change the plan.
        - Use kilograms and the user's metric/imperial preference. Never invent data you weren't given.
        TEXT;
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

    private function todaySection(User $user): string
    {
        $today = Session::ownedBy($user)->where('status', 'today')->with('exercises.sets')->first();

        if ($today === null) {
            return 'TODAY: no active session.';
        }

        $lines = ["TODAY: {$today->title} ({$today->kind}), target {$today->duration_min} min."];

        foreach ($today->exercises as $exercise) {
            $working = $exercise->sets->where('kind', 'Working');
            $topSet = $working->first();
            $detail = $topSet
                ? sprintf('%d×%g kg × %d sets', $topSet->reps, $topSet->kg, $working->count())
                : 'see plan';
            $lines[] = "  - {$exercise->name}: {$detail}";
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
