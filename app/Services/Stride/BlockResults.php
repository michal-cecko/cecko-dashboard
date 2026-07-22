<?php

namespace App\Services\Stride;

use App\Models\Stride\Block;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\WeightEntry;

/**
 * End-of-block results, stored on Block::$stats when a block completes (same
 * [{label, value}] shape the app's BigStat grid already renders): adherence,
 * completed sessions, lifted volume, trained hours, PRs set and bodyweight
 * change over the block's window.
 */
class BlockResults
{
    /** @return array<int, array{label: string, value: string}> */
    public static function compute(Block $block): array
    {
        $sessions = $block->sessions()->get();
        $trainable = $sessions->where('kind', '!=', 'Rest');
        $done = $trainable->where('status', 'done');

        $adherence = $trainable->count() > 0
            ? (int) round($done->count() / $trainable->count() * 100)
            : 0;
        $volumeTons = round((float) $done->sum('volume_kg') / 1000, 1);
        $hours = round((int) $done->sum('duration_min') / 60, 1);

        $stats = [
            ['label' => 'Adherence', 'value' => $adherence.'%'],
            ['label' => 'Sessions', 'value' => $done->count().'/'.$trainable->count()],
            ['label' => 'Volume', 'value' => $volumeTons.' t'],
            ['label' => 'Hours', 'value' => (string) $hours],
        ];

        $prs = PersonalRecord::ownedBy($block->user)
            ->whereBetween('achieved_on', [$block->starts_on, $block->ends_on])
            ->count();
        if ($prs > 0) {
            $stats[] = ['label' => 'PRs set', 'value' => (string) $prs];
        }

        $weights = WeightEntry::query()
            ->where('user_id', $block->user_id)
            ->whereBetween('recorded_on', [$block->starts_on, $block->ends_on])
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'kg']);
        if ($weights->count() >= 2) {
            $delta = round($weights->last()->kg - $weights->first()->kg, 1);
            $stats[] = ['label' => 'Bodyweight', 'value' => ($delta > 0 ? '+' : '').$delta.' kg'];
        }

        return $stats;
    }
}
