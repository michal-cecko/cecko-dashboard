<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Checkin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Daily check-ins (energy + note). Upserted per day so re-checking in simply
 * corrects today's entry, and the history stays for the coach to reason about.
 */
class CheckinController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $checkins = Checkin::ownedBy($request->user())
            ->orderByDesc('checked_on')
            ->limit(60)
            ->get()
            ->map(fn (Checkin $c) => [
                'date' => $c->checked_on->toDateString(),
                'energy' => $c->energy,
                'note' => $c->note,
            ]);

        return response()->json(['checkins' => $checkins->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Match on the DATE, not the cast value: `checked_on` is stored as a
        // datetime, so a string comparison misses and the insert would trip the
        // per-day unique index.
        $date = Carbon::createFromFormat('Y-m-d', $data['date'] ?? today()->toDateString())->startOfDay();

        $checkin = Checkin::ownedBy($request->user())->whereDate('checked_on', $date)->first()
            ?? new Checkin(['user_id' => $request->user()->id, 'checked_on' => $date]);

        // Patch-like: sending only an energy must not wipe today's note.
        if (array_key_exists('energy', $data)) {
            $checkin->energy = $data['energy'];
        }
        if (array_key_exists('note', $data)) {
            $checkin->note = $data['note'];
        }
        $checkin->save();

        return response()->json(['checkin' => [
            'date' => $checkin->checked_on->toDateString(),
            'energy' => $checkin->energy,
            'note' => $checkin->note,
        ]], 201);
    }
}
