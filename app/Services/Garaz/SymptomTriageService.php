<?php

namespace App\Services\Garaz;

use App\Enums\Garaz\KnowledgeSourceEnum;
use App\Models\Garaz\AiUsage;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\Vehicle;
use App\Services\Common\Ai\AiCost;
use App\Services\Common\Ai\AnthropicClient;
use RuntimeException;

/**
 * Embedded symptom-triage chat over the app-wide AnthropicClient
 * (App\Services\Common\Ai) — the same connector the Stride coach uses.
 *
 * Sends the vehicle profile + recent service history + relevant knowledge notes
 * as a cached system prompt; the user message is the symptom description.
 * Every call is recorded to garaz_ai_usage (tokens, latency, cost).
 *
 * Returns Slovak output by system instruction.
 *
 * Hook points:
 *  - System prompt is split into cacheable blocks (vehicle profile + history)
 *    so subsequent turns reuse the cache
 *  - Knowledge notes are filtered by tag/title relevance — naive substring match
 *    for now; swap to embeddings/RAG if/when the note volume grows
 */
class SymptomTriageService
{
    public function __construct(private readonly AnthropicClient $client = new AnthropicClient) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function ask(Vehicle $vehicle, string $symptom, ?string $previousReply = null): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY nie je nakonfigurovaný — nastav v .env a vyčisti config cache.');
        }

        $model = $this->client->defaultModel();

        $systemBlocks = [
            ['text' => $this->slovakStyleGuide(), 'cache' => true],
            ['text' => $this->vehicleProfile($vehicle), 'cache' => true],
            ['text' => $this->serviceHistorySnapshot($vehicle), 'cache' => true],
            ['text' => $this->relevantKnowledge($vehicle, $symptom)],
        ];

        $messages = [];

        if ($previousReply !== null) {
            $messages[] = ['role' => 'assistant', 'content' => $previousReply];
        }

        $messages[] = ['role' => 'user', 'content' => $symptom];

        $start = hrtime(true);
        $reply = $this->client->messages($model, 1024, $systemBlocks, $messages);
        $latencyMs = (int) ((hrtime(true) - $start) / 1e6);

        AiUsage::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'provider' => 'anthropic',
            'model' => $model,
            'purpose' => 'symptom_triage',
            'input_tokens' => $reply->usage->inputTokens,
            'output_tokens' => $reply->usage->outputTokens,
            'cache_creation_tokens' => $reply->usage->cacheCreationTokens,
            'cache_read_tokens' => $reply->usage->cacheReadTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => AiCost::usd($model, $reply->usage),
        ]);

        return $reply->text ?? '';
    }

    private function slovakStyleGuide(): string
    {
        return <<<'TEXT'
Si AI asistent pre majiteľa vozidla. Odpovedaj výlučne v slovenčine. Tykáš používateľovi.
Technické čísla dielov a značky ponechaj v origináli (napr. MANN W712/95, AdBlue, DPF, OEM kódy).
Odpovedaj štruktúrovane: Najpravdepodobnejšia príčina, Závažnosť (akútna/sledovať/info), Čo spraviť teraz (DIY), Čo žiadať v servise (ak treba), Cenový odhad. Nikdy nepotvrdzuj náhradné diely bez označenia "verifikuj OEM kompatibilitu".
TEXT;
    }

    private function vehicleProfile(Vehicle $vehicle): string
    {
        $spec = $vehicle->spec();
        $type = $vehicle->type?->translation() ?? '?';
        $lines = ["VOZIDLO: {$vehicle->nickname} ({$type})"];

        if ($vehicle->make || $vehicle->model) {
            $lines[] = 'Značka/Model: '.trim(($vehicle->make ?? '').' '.($vehicle->model ?? ''));
        }

        if ($vehicle->year_of_manufacture) {
            $lines[] = 'Rok výroby: '.$vehicle->year_of_manufacture;
        }

        if ($vehicle->current_odometer_km) {
            $lines[] = 'Aktuálny stav km: '.number_format($vehicle->current_odometer_km, 0, ',', ' ');
        }

        if ($spec !== null) {
            $lines[] = "\nŠpecifikácia:";

            foreach ($spec->getAttributes() as $key => $value) {
                if (in_array($key, ['id', 'vehicle_id', 'created_at', 'updated_at'], true) || $value === null || $value === '') {
                    continue;
                }
                $lines[] = "  - {$key}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    private function serviceHistorySnapshot(Vehicle $vehicle): string
    {
        $records = $vehicle->serviceRecords()->limit(20)->get();

        if ($records->isEmpty()) {
            return 'HISTÓRIA SERVISU: žiadne záznamy.';
        }

        $lines = ['HISTÓRIA SERVISU (max 20 najnovších):'];

        foreach ($records as $r) {
            $line = '- '.$r->performed_at->format('Y-m-d');

            if ($r->mileage_km) {
                $line .= ' @ '.number_format($r->mileage_km, 0, ',', ' ').' km';
            }

            $line .= ': '.($r->category?->translation() ?? '—');

            if ($r->source) {
                $line .= ' ['.$r->source->translation().']';
            }

            if ($r->shop_name) {
                $line .= ' — '.$r->shop_name;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function relevantKnowledge(Vehicle $vehicle, string $query): string
    {
        $haystack = strtolower($query);

        $notes = KnowledgeNote::query()
            ->where('user_id', $vehicle->user_id)
            ->where(function ($q) use ($vehicle): void {
                $q->whereNull('vehicle_id')->orWhere('vehicle_id', $vehicle->id);
            })
            ->limit(50)
            ->get()
            ->filter(function (KnowledgeNote $n) use ($haystack): bool {
                $hay = strtolower($n->title.' '.($n->body ?? '').' '.implode(' ', (array) $n->tags));

                foreach (explode(' ', $haystack) as $word) {
                    if (mb_strlen($word) >= 4 && str_contains($hay, $word)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(8);

        if ($notes->isEmpty()) {
            return 'POZNÁMKY POUŽÍVATEĽA: žiadne relevantné záznamy ku symptómu.';
        }

        $lines = ['POZNÁMKY POUŽÍVATEĽA (komunita / forum):'];

        foreach ($notes as $n) {
            $source = match ($n->source) {
                KnowledgeSourceEnum::FORUM => 'forum',
                KnowledgeSourceEnum::BOOKMARKLET => 'bookmark',
                KnowledgeSourceEnum::EMAIL => 'email',
                KnowledgeSourceEnum::AI => 'ai',
                default => 'manuál',
            };
            $lines[] = "- [{$source}] {$n->title}".($n->body ? ': '.mb_substr($n->body, 0, 240) : '');
        }

        return implode("\n", $lines);
    }
}
