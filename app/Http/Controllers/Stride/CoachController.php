<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\Block;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\CoachMessage;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Coach\CoachQuotaException;
use App\Services\Stride\Coach\CoachService;
use App\Services\Stride\Coach\ProposalApplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI coach: conversations scoped to the user, each carrying full context +
 * history. The heavy lifting lives in CoachService.
 */
class CoachController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = CoachConversation::ownedBy($request->user())
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map($this->conversationSummary(...));

        return response()->json(['conversations' => $conversations->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'persona_key' => ['nullable', 'in:coach,calm,nerd,buddy'],
        ]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $conversation = CoachConversation::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? null,
            'persona_key' => $data['persona_key'] ?? $profile->persona_key,
        ]);

        return response()->json(['conversation' => $this->conversationSummary($conversation)], 201);
    }

    public function show(Request $request, CoachConversation $conversation): JsonResponse
    {
        $this->authorize($request, $conversation);

        $conversation->load('messages');

        return response()->json([
            'conversation' => array_merge($this->conversationSummary($conversation), [
                'messages' => $conversation->messages->map($this->messagePayload(...))->values(),
            ]),
        ]);
    }

    public function message(Request $request, CoachConversation $conversation, CoachService $coach): JsonResponse
    {
        $this->authorize($request, $conversation);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $assistant = $coach->reply($conversation, $data['message']);
        } catch (CoachQuotaException $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        }

        return response()->json([
            'message' => $this->messagePayload($assistant),
            'conversation_id' => $conversation->id,
        ]);
    }

    public function setPersona(Request $request, CoachConversation $conversation): JsonResponse
    {
        $this->authorize($request, $conversation);

        $data = $request->validate(['persona_key' => ['required', 'in:coach,calm,nerd,buddy']]);
        $conversation->update(['persona_key' => $data['persona_key']]);

        return response()->json(['conversation' => $this->conversationSummary($conversation)]);
    }

    /** The coach's recent plan changes — the "why" feed for Home/Plan. */
    public function adjustments(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => ['nullable', 'in:today,week,block']]);

        $adjustments = AiAdjustment::ownedBy($request->user())
            ->when($data['scope'] ?? null, fn ($q, $scope) => $q->where('scope', $scope))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (AiAdjustment $a) => [
                'id' => $a->id,
                'scope' => $a->scope,
                'kind' => $a->kind,
                'target' => $a->target,
                'text' => $a->text,
                'why' => $a->why,
                'when' => $a->created_at?->diffForHumans(),
            ]);

        return response()->json(['adjustments' => $adjustments->values()]);
    }

    /** The coach chat scoped to one block — edits the whole block. Created on first open. */
    public function blockConversation(Request $request, Block $block): JsonResponse
    {
        abort_unless($block->user_id === $request->user()->id, 404);

        $profile = StrideProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $conversation = CoachConversation::firstOrCreate(
            ['user_id' => $request->user()->id, 'block_id' => $block->id],
            ['persona_key' => $profile->persona_key, 'title' => "{$block->name} — coach", 'last_message_at' => now()],
        );
        $conversation->load('messages');

        return response()->json([
            'conversation' => array_merge($this->conversationSummary($conversation), [
                'messages' => $conversation->messages->map($this->messagePayload(...))->values(),
            ]),
        ]);
    }

    /** Pending coach changes awaiting the user's Apply/Dismiss (the propose→confirm queue). */
    public function proposals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:today,week,block'],
            'block_id' => ['nullable', 'integer'],
        ]);

        $proposals = AiAdjustment::ownedBy($request->user())->proposed()
            ->when($data['scope'] ?? null, fn ($q, $scope) => $q->where('scope', $scope))
            ->when($data['block_id'] ?? null, fn ($q, $id) => $q->where('block_id', $id))
            ->orderByDesc('id')
            ->get()
            ->map($this->proposalPayload(...));

        return response()->json(['proposals' => $proposals->values()]);
    }

    public function applyProposal(Request $request, AiAdjustment $adjustment, ProposalApplyService $applier): JsonResponse
    {
        abort_unless($adjustment->user_id === $request->user()->id, 404);

        $outcome = $applier->apply($request->user(), $adjustment);

        return response()->json([
            'adjustment' => $this->proposalPayload($adjustment->fresh()),
            'session_ids' => $outcome['session_ids'],
            'scope' => $adjustment->scope,
            'block_id' => $adjustment->block_id,
            'result' => $outcome['result'],
        ]);
    }

    public function dismissProposal(Request $request, AiAdjustment $adjustment): JsonResponse
    {
        abort_unless($adjustment->user_id === $request->user()->id, 404);
        abort_unless($adjustment->status === 'proposed', 409, 'This change is no longer pending.');

        $adjustment->update(['status' => 'dismissed']);

        return response()->json(['ok' => true]);
    }

    private function proposalPayload(AiAdjustment $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'operation' => $a->operation,
            'scope' => $a->scope,
            'kind' => $a->kind,
            'target' => $a->target,
            'text' => $a->text,
            'why' => $a->why,
            'when' => $a->created_at?->diffForHumans(),
        ];
    }

    private function authorize(Request $request, CoachConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }

    private function conversationSummary(CoachConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'persona_key' => $conversation->persona_key,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    private function messagePayload(CoachMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'cards' => $message->cards,
            // The stored adjustments are a stage-time snapshot; refresh each one's
            // status from the live row so an already-applied/dismissed proposal
            // doesn't reappear with an Apply button after a reload.
            'adjustments' => $this->withLiveStatus($message->adjustments ?? []),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function withLiveStatus(array $adjustments): array
    {
        $ids = collect($adjustments)->pluck('id')->filter()->all();
        if ($ids === []) {
            return $adjustments;
        }
        $live = AiAdjustment::whereIn('id', $ids)->pluck('status', 'id');

        return collect($adjustments)->map(function (array $a) use ($live) {
            if (isset($a['id']) && $live->has($a['id'])) {
                $a['status'] = $live[$a['id']];
            }

            return $a;
        })->values()->all();
    }
}
