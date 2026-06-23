<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\Block;
use App\Models\Stride\CoachMemory;
use App\Models\Stride\PersonalRecord;
use App\Services\Stride\PlanGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan tab: periodised blocks (foundations → … → test week) and a single
 * block's detail with its session timeline. Also drives onboarding plan
 * generation (recommend a few options → generate the chosen one).
 */
class PlanController extends Controller
{
    /** Onboarding step 1: a few AI-recommended plan options to pick from. */
    public function recommend(Request $request, PlanGenerationService $planner): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:300'],
            'base' => ['nullable', 'array'],
            'base.name' => ['nullable', 'string', 'max:120'],
            'base.split' => ['nullable', 'string', 'max:60'],
            'base.phase' => ['nullable', 'string', 'max:60'],
            'base.summary' => ['nullable', 'string', 'max:500'],
            'base.weeks' => ['nullable', 'integer', 'between:1,16'],
            'base.days_per_week' => ['nullable', 'integer', 'between:1,7'],
        ]);

        return response()->json([
            'options' => $planner->recommend($request->user(), $data['note'] ?? null, $data['base'] ?? null),
        ]);
    }

    /**
     * Optional onboarding sub-step: a few clarifying questions the coach wants
     * answered before generating (missing PRs the goals imply + general context).
     */
    public function questions(Request $request, PlanGenerationService $planner): JsonResponse
    {
        $data = $request->validate([
            'option' => ['required', 'array'],
            'option.name' => ['required', 'string', 'max:120'],
            'option.split' => ['nullable', 'string', 'max:60'],
            'option.phase' => ['nullable', 'string', 'max:60'],
            'option.summary' => ['nullable', 'string', 'max:500'],
            'option.weeks' => ['nullable', 'integer', 'between:1,16'],
            'option.days_per_week' => ['nullable', 'integer', 'between:1,7'],
        ]);

        return response()->json([
            'questions' => $planner->questions($request->user(), $data['option']),
        ]);
    }

    /**
     * Persist answers to the clarifying questions: `pr` answers become
     * PersonalRecords (source `ai-question`), `text` answers become CoachMemory
     * facts (source `onboarding`) so the coach remembers them going forward.
     */
    public function answers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'max:12'],
            'answers.*.type' => ['required', 'string', 'in:pr,text'],
            'answers.*.label' => ['required', 'string', 'max:120'],
            'answers.*.exercise_id' => ['nullable', 'integer', 'exists:stride_exercises,id'],
            'answers.*.metric_type' => ['nullable', 'string', 'in:load,reps,hold,run,sprint,machine'],
            'answers.*.metrics' => ['nullable', 'array'],
            'answers.*.metrics.weight' => ['nullable', 'numeric', 'between:0,1000'],
            'answers.*.metrics.reps' => ['nullable', 'integer', 'between:0,10000'],
            'answers.*.metrics.seconds' => ['nullable', 'integer', 'between:0,86400'],
            'answers.*.metrics.distance_m' => ['nullable', 'numeric', 'between:0,1000000'],
            'answers.*.metrics.calories' => ['nullable', 'integer', 'between:0,100000'],
            'answers.*.metrics.watts' => ['nullable', 'integer', 'between:0,5000'],
            'answers.*.achieved_on' => ['nullable', 'date', 'before_or_equal:today'],
            'answers.*.form_quality' => ['nullable', 'integer', 'between:1,5'],
            'answers.*.text' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $records = 0;
        $facts = 0;

        foreach ($data['answers'] as $answer) {
            if ($answer['type'] === 'pr' && ! empty(array_filter($answer['metrics'] ?? [], fn ($v) => $v !== null))) {
                PersonalRecord::create([
                    'user_id' => $user->id,
                    'exercise_id' => $answer['exercise_id'] ?? null,
                    'label' => $answer['label'],
                    'metric_type' => $answer['metric_type'] ?? 'load',
                    'metrics' => array_filter($answer['metrics'], fn ($v) => $v !== null),
                    'achieved_on' => $answer['achieved_on'] ?? null,
                    'form_quality' => $answer['form_quality'] ?? null,
                    'source' => 'ai-question',
                ]);
                $records++;
            } elseif ($answer['type'] === 'text' && filled($answer['text'] ?? null)) {
                CoachMemory::create([
                    'user_id' => $user->id,
                    'fact' => $answer['label'].': '.$answer['text'],
                    'source' => 'onboarding',
                    'last_used_at' => now(),
                ]);
                $facts++;
            }
        }

        return response()->json(['saved_records' => $records, 'saved_facts' => $facts], 201);
    }

    /** Onboarding step 2: generate + persist the chosen plan. */
    public function generate(Request $request, PlanGenerationService $planner): JsonResponse
    {
        $data = $request->validate([
            'option' => ['required', 'array'],
            'option.name' => ['required', 'string', 'max:120'],
            'option.split' => ['required', 'string', 'max:60'],
            'option.phase' => ['nullable', 'string', 'max:60'],
            'option.summary' => ['nullable', 'string', 'max:500'],
            'option.weeks' => ['nullable', 'integer', 'between:1,16'],
            'option.days_per_week' => ['nullable', 'integer', 'between:1,7'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $block = $planner->generate($request->user(), $data['option'], $data['start_date'] ?? null);

        return response()->json(['block_id' => $block->id, 'name' => $block->name], 201);
    }

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
