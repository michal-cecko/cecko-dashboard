<?php

namespace App\Services\Stride\Coach;

use App\Models\Common\User;
use App\Models\Stride\AiAdjustment;
use App\Models\Stride\AiUsage;
use App\Models\Stride\Block;
use App\Models\Stride\CoachConversation;
use App\Models\Stride\CoachMessage;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Services\Common\Ai\AiCost;
use App\Services\Common\Ai\AiProviderResolver;
use App\Services\Common\Ai\AiTokenUsage;
use App\Services\Common\Ai\AiUsageBucket;
use App\Services\Common\Ai\ResolvedAi;

/**
 * Orchestrates one coach turn: assemble context → run the tool-use loop →
 * persist the exchange → log usage → maybe compact older history.
 *
 * The provider is resolved per user (BYOK or the free tier) via
 * AiProviderResolver, so this class is identical whether it talks to the user's
 * Anthropic/Gemini/OpenAI account, the app default, or a fake in tests.
 */
class CoachService
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly TrainingMemoryBuilder $memory,
        private readonly CoachToolExecutor $executor,
    ) {}

    /**
     * Produce the coach's reply to a new user message and persist both sides.
     *
     * @throws CoachQuotaException
     */
    public function reply(CoachConversation $conversation, string $userText): CoachMessage
    {
        $user = $conversation->user;
        $ai = $this->resolver->for($user, $conversation->model);
        $this->assertWithinQuota($user, $ai);

        $conversation->messages()->create(['role' => 'user', 'content' => $userText]);

        $language = $this->resolveLanguage($user);
        $system = $this->systemBlocks($conversation, $user, $language);
        $messages = $this->history($conversation);

        // The general chat can edit the block too: without a scoped block, fall
        // back to the athlete's active one so block tools work from either chat.
        $context = new CoachContext(
            conversation: $conversation,
            todaySession: Session::ownedBy($user)->where('status', 'today')
                ->whereHas('block', fn ($q) => $q->where('status', 'active'))->first(),
            block: $conversation->block ?? Block::ownedBy($user)->active()->first(),
        );

        [$finalText, $adjustments, $usages] = $this->runToolLoop($ai, $user, $context, $system, $messages, $language);

        if ($finalText === '') {
            $finalText = $adjustments !== []
                ? ($language === 'sk' ? 'Pripravil som zmeny — potvrď ich, prosím, nižšie.' : 'I have staged the changes — please confirm them below.')
                : ($language === 'sk' ? 'Prepáč — odpoveď sa nepodarilo dokončiť. Skús to, prosím, ešte raz.' : 'Sorry — my reply got cut off. Please try again.');
        }

        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $finalText,
            'adjustments' => $this->adjustmentCards($adjustments),
            'input_tokens' => array_sum(array_map(fn ($u) => $u['usage']->inputTokens, $usages)),
            'output_tokens' => array_sum(array_map(fn ($u) => $u['usage']->outputTokens, $usages)),
        ]);

        $conversation->update(['last_message_at' => now()]);

        foreach ($usages as $u) {
            $this->logUsage($ai, $conversation, $u['usage'], $u['latency'], 'chat');
        }

        $this->maybeSummarize($ai, $conversation);

        return $assistant;
    }

    /** @return array{0: string, 1: array, 2: array} [finalText, adjustments, usages] */
    private function runToolLoop(ResolvedAi $ai, User $user, CoachContext $ctx, array $system, array $messages, string $language = 'en'): array
    {
        $tools = CoachTools::definitions($ctx->block !== null);
        $maxIterations = (int) config('stride.coach.max_tool_iterations');
        $adjustments = [];
        $usages = [];
        $finalText = '';

        for ($iteration = 0; ; $iteration++) {
            // On the final allowed iteration, drop tools so the model must close with text.
            $turn = new CoachTurn(
                model: $ai->model('chat'),
                systemBlocks: $system,
                messages: $messages,
                tools: $iteration < $maxIterations ? $tools : [],
                maxTokens: (int) config('stride.coach.max_tokens'),
                language: $language,
            );

            $start = hrtime(true);
            $reply = $ai->provider->chat($turn);
            $usages[] = ['usage' => $reply->usage, 'latency' => (int) ((hrtime(true) - $start) / 1e6)];

            if (! $reply->wantsTools()) {
                // A reply cut off at max_tokens is the model's unfinished planning
                // ("…Let's swap `Overhead Press` with `") — never surface it; the
                // caller substitutes a clean language-appropriate close instead.
                $finalText = $reply->stopReason === 'max_tokens' ? '' : ($reply->text ?? '');
                if ($reply->stopReason === 'max_tokens') {
                    logger()->warning('Stride coach reply truncated at max_tokens — suppressed fragment.', [
                        'conversation_id' => $ctx->conversation?->id,
                        'snippet' => mb_substr((string) $reply->text, 0, 120),
                    ]);
                }
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $reply->assistantContent()];

            $results = [];
            foreach ($reply->toolUses as $tool) {
                $outcome = $this->executor->execute($user, $tool['name'], $tool['input'], $ctx);
                if ($outcome['adjustment'] !== null
                    && ! in_array($outcome['adjustment']->id, array_map(fn ($a) => $a->id, $adjustments), true)) {
                    $adjustments[] = $outcome['adjustment'];
                }
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $tool['id'],
                    'content' => $outcome['result'],
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        return [$finalText, $adjustments, $usages];
    }

    /** Cached system prompt: stable guide + per-request training memory + rolling summary. */
    private function systemBlocks(CoachConversation $conversation, User $user, string $language = 'en'): array
    {
        $block = $conversation->block ?? Block::ownedBy($user)->active()->first();

        $blocks = [
            ['text' => $this->memory->systemGuide($conversation->persona_key, $language, $block !== null), 'cache' => true],
            ['text' => $this->memory->memory($user), 'cache' => true],
        ];

        if ($block !== null) {
            $blocks[] = ['text' => $this->memory->blockMemory($block), 'cache' => true];
        }

        if ($conversation->summary) {
            $blocks[] = ['text' => "EARLIER CONVERSATION SUMMARY:\n".$conversation->summary, 'cache' => false];
        }

        return $blocks;
    }

    /** The user's chosen coach/UI language ('en'|'sk'), from their Stride profile preferences. */
    private function resolveLanguage(User $user): string
    {
        $language = StrideProfile::firstOrCreate(['user_id' => $user->id])->preferences['language'] ?? 'en';

        return in_array($language, ['en', 'sk'], true) ? $language : 'en';
    }

    /** Recent raw turns after the summarised window, capped to recent_turns. */
    private function history(CoachConversation $conversation): array
    {
        $recentTurns = (int) config('stride.coach.recent_turns');

        return $conversation->messages()
            ->when($conversation->summarized_through_id, fn ($q) => $q->where('id', '>', $conversation->summarized_through_id))
            ->get()
            ->slice(-$recentTurns)
            ->map(fn (CoachMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    /** Fold older turns into the conversation summary once history grows. */
    private function maybeSummarize(ResolvedAi $ai, CoachConversation $conversation): void
    {
        $recentTurns = (int) config('stride.coach.recent_turns');
        $threshold = (int) config('stride.coach.summary_threshold');

        $unsummarised = $conversation->messages()
            ->when($conversation->summarized_through_id, fn ($q) => $q->where('id', '>', $conversation->summarized_through_id))
            ->get();

        if ($unsummarised->count() <= $threshold) {
            return;
        }

        $toSummarise = $unsummarised->slice(0, $unsummarised->count() - $recentTurns);
        if ($toSummarise->isEmpty()) {
            return;
        }

        $transcript = $toSummarise->map(fn (CoachMessage $m) => "{$m->role}: {$m->content}")->implode("\n");

        $turn = new CoachTurn(
            model: $ai->model('summary'),
            systemBlocks: [['text' => 'You compress coaching conversations. Keep durable facts, decisions, and open threads. Be terse.', 'cache' => false]],
            messages: [['role' => 'user', 'content' => "Summarise the conversation so far in under 200 words:\n\n{$transcript}"]],
            maxTokens: 400,
            purpose: 'summary',
        );

        $start = hrtime(true);
        $reply = $ai->provider->chat($turn);
        $this->logUsage($ai, $conversation, $reply->usage, (int) ((hrtime(true) - $start) / 1e6), 'summary');

        $merged = trim(($conversation->summary ? $conversation->summary."\n\n" : '').($reply->text ?? ''));

        $conversation->update([
            'summary' => $merged,
            'summarized_through_id' => $toSummarise->last()->id,
        ]);
    }

    private function assertWithinQuota(User $user, ResolvedAi $ai): void
    {
        // BYOK users run on their own key/cost — 0 means unlimited. The free tier
        // gets a tighter cap.
        $limit = $ai->isByok
            ? (int) config('stride.ai.byok_daily_quota')
            : (int) config('stride.ai.free.daily_quota', config('stride.coach.daily_message_quota'));

        if ($limit <= 0) {
            return;
        }

        $todayCount = CoachMessage::query()
            ->where('role', 'user')
            ->whereDate('created_at', today())
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        if ($todayCount >= $limit) {
            throw new CoachQuotaException($limit);
        }
    }

    private function logUsage(ResolvedAi $ai, CoachConversation $conversation, AiTokenUsage $usage, int $latencyMs, string $purpose): void
    {
        // Ollama serves every purpose with its single configured local model.
        $model = $ai->driverName() === 'ollama'
            ? (string) config('ai.ollama.model')
            : $ai->model($purpose);

        AiUsageBucket::record(AiUsage::class, [
            'user_id' => $conversation->user_id,
            'conversation_id' => $conversation->id,
            'provider' => $ai->driverName(),
            'model' => $model,
            'purpose' => $purpose,
        ], [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cache_creation_tokens' => $usage->cacheCreationTokens,
            'cache_read_tokens' => $usage->cacheReadTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => $this->cost($ai, $model, $usage),
        ]);
    }

    private function cost(ResolvedAi $ai, string $model, AiTokenUsage $usage): float
    {
        // Local inference (local stub, ollama) is free whatever the token counts.
        if (in_array($ai->driverName(), ['local', 'ollama'], true)) {
            return 0.0;
        }

        return AiCost::usd($model, $usage);
    }

    /** @param array<int, AiAdjustment> $adjustments */
    private function adjustmentCards(array $adjustments): ?array
    {
        if ($adjustments === []) {
            return null;
        }

        return array_map(fn ($a) => [
            'id' => $a->id,
            'status' => $a->status,
            'operation' => $a->operation,
            'kind' => $a->kind,
            'target' => $a->target,
            'text' => $a->text,
            'why' => $a->why,
        ], $adjustments);
    }
}
