<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\CoachMessage;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Coach\CoachQuotaException;
use App\Services\Stride\Coach\CoachService;
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
            'adjustments' => $message->adjustments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
