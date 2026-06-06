<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\Block;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan tab: periodised blocks (foundations → … → test week) and a single
 * block's detail with its session timeline.
 */
class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = Block::ownedBy($request->user())
            ->orderBy('sort')
            ->orderBy('starts_on')
            ->withCount('sessions')
            ->get();

        return response()->json([
            'blocks' => $blocks->map($this->blockSummary(...))->values(),
        ]);
    }

    public function show(Request $request, Block $block): JsonResponse
    {
        abort_unless($block->user_id === $request->user()->id, 404);

        $block->load('sessions');

        return response()->json([
            'block' => array_merge($this->blockSummary($block), [
                'summary' => $block->summary,
                'sessions' => $block->sessions->map(SessionPresenter::summary(...))->values(),
            ]),
        ]);
    }

    private function blockSummary(Block $block): array
    {
        return [
            'id' => $block->id,
            'name' => $block->name,
            'phase' => $block->phase,
            'status' => $block->status,
            'weeks' => $block->weeks,
            'week_of' => $block->week_of,
            'starts_on' => $block->starts_on?->toDateString(),
            'ends_on' => $block->ends_on?->toDateString(),
            'accent' => $block->accent,
            'stats' => $block->stats,
            'sessions_count' => $block->sessions_count ?? $block->sessions()->count(),
        ];
    }
}
