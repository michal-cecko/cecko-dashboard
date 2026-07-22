<?php

namespace App\Console\Commands\Stride;

use App\Models\Stride\Block;
use App\Services\Stride\BlockResults;
use App\Services\Stride\PlanGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Daily heartbeat of every active training block:
 *
 *  1. Session statuses roll with the calendar — yesterday's untouched "today"
 *     session becomes "skipped", today's planned session becomes "today".
 *  2. When a block's current week has elapsed, week_of advances and the next
 *     week's sessions are generated (with progression context); when the last
 *     week elapses, the block completes and its results land in Block::$stats.
 *
 * Each block is isolated: one athlete's failure never blocks the rest.
 */
class AdvanceStrideBlocksCommand extends Command
{
    protected $signature = 'stride:advance-blocks';

    protected $description = 'Roll session statuses daily, generate the next week of active Stride blocks, complete finished ones';

    public function handle(PlanGenerationService $planner): int
    {
        $today = Carbon::today();

        foreach (Block::query()->where('status', 'active')->with('user')->get() as $block) {
            try {
                $this->advance($planner, $block, $today);
            } catch (Throwable $e) {
                report($e);
                $this->error("Block #{$block->id} ({$block->name}): {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function advance(PlanGenerationService $planner, Block $block, Carbon $today): void
    {
        // Catch-up loop: a server that missed days rolls forward one week at a
        // time until the block is current (or complete).
        while ($block->status === 'active'
            && $today->gte($block->starts_on->copy()->addWeeks($block->week_of))) {
            if ($block->week_of >= $block->weeks) {
                $block->update(['status' => 'done', 'stats' => BlockResults::compute($block)]);
                $this->info("Block #{$block->id} ({$block->name}) completed — results stored.");

                break;
            }

            $block->update(['week_of' => $block->week_of + 1]);
            $planner->generateWeek($block->user, $block->refresh());
            $this->info("Block #{$block->id} ({$block->name}) advanced to week {$block->week_of}.");
        }

        if ($block->status !== 'active') {
            return;
        }

        // Roll the day pointers: a stale "today" the athlete never opened is
        // skipped; today's planned session surfaces on Home.
        $block->sessions()->where('status', 'today')->whereDate('scheduled_date', '<', $today)->update(['status' => 'skipped']);
        $block->sessions()->where('status', 'planned')->whereDate('scheduled_date', $today)->update(['status' => 'today']);
    }
}
