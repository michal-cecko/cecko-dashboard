<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference, 'title' => $this->title, 'description' => $this->description,
            'url' => $this->url, 'type' => $this->type, 'status' => $this->status, 'is_critical' => $this->is_critical,
            'checklist' => $this->checklist ?? [], 'created_at' => $this->created_at->toIso8601String(),
            'events' => $this->whenLoaded('events', fn (): array => $this->events->where('body', '!=', '')->map(fn ($event): array => ['id' => $event->id, 'kind' => $event->kind, 'body' => $event->body, 'at' => $event->occurred_at->toIso8601String()])->values()->all()),
        ];
    }
}
