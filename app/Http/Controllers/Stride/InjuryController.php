<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Injury;
use App\Models\Stride\InjuryJournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InjuryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $injuries = Injury::ownedBy($request->user())
            ->orderBy('sort')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'monitoring' THEN 1 ELSE 2 END")
            ->get();

        return response()->json(['injuries' => $injuries->map($this->summary(...))->values()]);
    }

    public function show(Request $request, Injury $injury): JsonResponse
    {
        abort_unless($injury->user_id === $request->user()->id, 404);

        $injury->load('journalEntries');

        return response()->json([
            'injury' => array_merge($this->summary($injury), [
                'note' => $injury->note,
                'avoid' => $injury->avoid ?? [],
                'safe' => $injury->safe ?? [],
                'journal' => $injury->journalEntries->map(fn (InjuryJournalEntry $e) => [
                    'id' => $e->id,
                    'date' => $e->entry_date?->toDateString(),
                    'trend' => $e->trend,
                    'text' => $e->text,
                ])->values(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body_part' => ['required', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:active,monitoring,resolved'],
            'since' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'avoid' => ['nullable', 'array'],
            'avoid.*' => ['string', 'max:64'],
            'safe' => ['nullable', 'array'],
            'safe.*' => ['string', 'max:64'],
        ]);

        $injury = Injury::create(['user_id' => $request->user()->id, ...$data]);

        return response()->json(['injury' => $this->summary($injury)], 201);
    }

    public function update(Request $request, Injury $injury): JsonResponse
    {
        abort_unless($injury->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'body_part' => ['sometimes', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:active,monitoring,resolved'],
            'since' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'avoid' => ['nullable', 'array'],
            'safe' => ['nullable', 'array'],
        ]);

        $injury->update($data);

        return response()->json(['injury' => $this->summary($injury)]);
    }

    public function addJournal(Request $request, Injury $injury): JsonResponse
    {
        abort_unless($injury->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'text' => ['required', 'string'],
            'trend' => ['nullable', 'in:better,same,worse'],
            'entry_date' => ['nullable', 'date'],
        ]);

        $entry = $injury->journalEntries()->create([
            'text' => $data['text'],
            'trend' => $data['trend'] ?? 'same',
            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'date' => $entry->entry_date?->toDateString(),
                'trend' => $entry->trend,
                'text' => $entry->text,
            ],
        ], 201);
    }

    private function summary(Injury $injury): array
    {
        return [
            'id' => $injury->id,
            'body_part' => $injury->body_part,
            'label' => $injury->label,
            'severity' => $injury->severity,
            'status' => $injury->status,
            'since' => $injury->since?->toDateString(),
        ];
    }
}
