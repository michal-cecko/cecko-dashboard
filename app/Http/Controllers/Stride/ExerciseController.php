<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\ExercisePresenter;
use App\Models\Stride\Exercise;
use App\Models\Stride\Routine;
use App\Services\Stride\RoutineSteps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The user's private exercises. They show up next to the shared catalogue in
 * the library and can be used in routines; nobody else ever sees them.
 */
class ExerciseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $user = $request->user();

        $exercise = Exercise::create([
            ...$data,
            'user_id' => $user->id,
            'slug' => "u{$user->id}-".Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'category' => $data['category'] ?? 'custom',
            'cues' => $data['cues'] ?? [],
        ]);

        return response()->json(['exercise' => ExercisePresenter::payload($exercise)], 201);
    }

    public function update(Request $request, Exercise $exercise): JsonResponse
    {
        abort_unless($exercise->user_id === $request->user()->id, 404);

        $data = $this->validateData($request, partial: true);
        if (array_key_exists('category', $data)) {
            $data['category'] ??= 'custom';
        }
        if (array_key_exists('cues', $data)) {
            $data['cues'] ??= [];
        }

        $exercise->update($data);

        return response()->json(['exercise' => ExercisePresenter::payload($exercise)]);
    }

    public function destroy(Request $request, Exercise $exercise): Response
    {
        abort_unless($exercise->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($request, $exercise): void {
            $exercise->delete();

            foreach (Routine::ownedBy($request->user())->lockForUpdate()->get() as $routine) {
                $steps = RoutineSteps::withoutExercise($routine->steps, $exercise->id);
                if ($steps !== $routine->steps) {
                    $routine->update(['steps' => $steps]);
                }
            }
        });

        return response()->noContent();
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $url = ['nullable', 'string', 'url:https', 'max:255'];

        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'name_sk' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'group' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cues' => ['nullable', 'array', 'max:10'],
            'cues.*' => ['string', 'max:200'],
            'video_url' => $url,
            'thumbnail_url' => $url,
            'image_url' => $url,
        ]);
    }
}
