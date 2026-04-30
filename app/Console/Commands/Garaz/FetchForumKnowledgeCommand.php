<?php

namespace App\Console\Commands\Garaz;

use App\Enums\Garaz\KnowledgeSourceEnum;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FetchForumKnowledgeCommand extends Command
{
    protected $signature = 'garaz:fetch-forum-knowledge {--dry-run : Print would-be saves without writing}';

    protected $description = 'Pull community knowledge from configured forum sources into KnowledgeNote store';

    public function handle(): int
    {
        $userId = config('garaz.forum_ingest_user_id');

        if ($userId === null) {
            $this->warn('GARAZ_FORUM_INGEST_USER_ID is not set — skipping. Configure config/garaz.php or .env to enable.');

            return self::SUCCESS;
        }

        $sources = config('garaz.forum_sources', []);

        if (empty($sources)) {
            $this->info('No forum sources configured.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($sources as $source) {
            $this->info("Fetching: {$source['name']}");
            $items = $this->fetchSource($source);

            foreach ($items as $item) {
                if (! $this->matchesKeywords($item, $source['keywords'] ?? [])) {
                    continue;
                }

                if ($this->alreadySeen($userId, $item)) {
                    continue;
                }

                $vehicleId = $this->matchVehicle($userId, $source['vehicle_match'] ?? null);

                if ($this->option('dry-run')) {
                    $this->line("  + [{$source['name']}] {$item['title']} → vehicle_id={$vehicleId}");

                    continue;
                }

                KnowledgeNote::create([
                    'user_id' => $userId,
                    'vehicle_id' => $vehicleId,
                    'title' => $item['title'],
                    'body' => $item['body'] ?? null,
                    'source_url' => $item['url'] ?? null,
                    'source' => KnowledgeSourceEnum::FORUM,
                    'tags' => $item['tags'] ?? null,
                    'captured_at' => $item['captured_at'] ?? now(),
                ]);

                $created++;
            }

            sleep((int) ($source['rate_limit_seconds'] ?? 5));
        }

        $this->info("Created {$created} forum knowledge notes.");

        return self::SUCCESS;
    }

    /** @return array<int, array{title: string, body?: string|null, url?: string|null, tags?: array|null, captured_at?: Carbon|null}> */
    private function fetchSource(array $source): array
    {
        return match ($source['kind']) {
            'reddit_json' => $this->fetchReddit($source),
            // TODO: 'http_html' => $this->fetchForumHtml($source),
            // TODO: 'rss'       => $this->fetchRss($source),
            default => $this->warnAndReturn("Unsupported source kind: {$source['kind']}"),
        };
    }

    /** @return array<int, array> */
    private function fetchReddit(array $source): array
    {
        $response = Http::withHeaders(['User-Agent' => 'synapps-garaz/0.1 (personal use)'])
            ->timeout(15)
            ->get($source['url']);

        if (! $response->successful()) {
            $this->warn("  HTTP {$response->status()} for {$source['url']}");

            return [];
        }

        $children = data_get($response->json(), 'data.children', []);

        return collect($children)->map(function (array $child): array {
            $d = $child['data'] ?? [];

            return [
                'title' => $d['title'] ?? '(untitled)',
                'body' => $d['selftext'] ?? null,
                'url' => isset($d['permalink']) ? 'https://reddit.com'.$d['permalink'] : null,
                'tags' => array_filter([$d['subreddit'] ?? null, $d['link_flair_text'] ?? null]),
                'captured_at' => isset($d['created_utc']) ? Carbon::createFromTimestamp((int) $d['created_utc']) : null,
            ];
        })->all();
    }

    private function matchesKeywords(array $item, array $keywords): bool
    {
        if (empty($keywords)) {
            return true;
        }

        $hay = strtolower(($item['title'] ?? '').' '.($item['body'] ?? ''));

        foreach ($keywords as $kw) {
            if (str_contains($hay, strtolower($kw))) {
                return true;
            }
        }

        return false;
    }

    private function alreadySeen(int $userId, array $item): bool
    {
        if (empty($item['url'])) {
            return false;
        }

        return KnowledgeNote::query()
            ->where('user_id', $userId)
            ->where('source_url', $item['url'])
            ->exists();
    }

    private function matchVehicle(int $userId, ?array $match): ?int
    {
        if ($match === null) {
            return null;
        }

        $vehicle = Vehicle::query()
            ->where('user_id', $userId)
            ->when(isset($match['make']), fn ($q) => $q->where('make', $match['make']))
            ->when(isset($match['model']), fn ($q) => $q->whereLike('model', '%'.$match['model'].'%', caseSensitive: false))
            ->first();

        return $vehicle?->id;
    }

    private function warnAndReturn(string $message): array
    {
        $this->warn('  '.$message);

        return [];
    }
}
