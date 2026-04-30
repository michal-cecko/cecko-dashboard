<?php

namespace App\Mcp\Tools;

use App\Models\Garaz\KnowledgeNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Searches the user knowledge notes (FB groups, forums, articles) by keyword. Optionally scope by vehicle_id.')]
class SearchKnowledgeTool extends Tool
{
    public function handle(Request $request): Response
    {
        $userId = auth()->id();

        if ($userId === null) {
            return Response::text('Authorization required.');
        }

        $query = trim((string) $request->get('query', ''));
        $vehicleId = $request->get('vehicle_id');

        $builder = KnowledgeNote::query()->where('user_id', $userId);

        if (! empty($vehicleId)) {
            $builder->where('vehicle_id', $vehicleId);
        }

        if ($query !== '') {
            $builder->where(function ($q) use ($query): void {
                $q->whereLike('title', "%{$query}%", caseSensitive: false)
                    ->orWhereLike('body', "%{$query}%", caseSensitive: false);
            });
        }

        $notes = $builder->orderByDesc('captured_at')->limit(20)->get();

        if ($notes->isEmpty()) {
            return Response::text('No knowledge notes match.');
        }

        $lines = [];

        foreach ($notes as $n) {
            $lines[] = "## {$n->title}";

            if ($n->source_url) {
                $lines[] = "Source: {$n->source_url}";
            }

            if ($n->tags) {
                $lines[] = 'Tags: '.implode(', ', (array) $n->tags);
            }

            if ($n->body) {
                $lines[] = '';
                $lines[] = mb_substr($n->body, 0, 800);
            }

            $lines[] = '';
        }

        return Response::text(implode("\n", $lines));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search keyword(s)')->required(),
            'vehicle_id' => $schema->integer()->description('Optionally scope to a specific vehicle id'),
        ];
    }
}
