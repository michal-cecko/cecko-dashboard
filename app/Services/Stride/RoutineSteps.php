<?php

namespace App\Services\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Validation\ValidationException;

/**
 * The step tree of a routine: work and rest steps, and loops that repeat their
 * child steps. Loops nest at most 2 levels deep, like the app's editor.
 *
 * Validation is hand-written instead of Laravel rules so the messages stay
 * short, English and name the step ("Step 2.1: …"): the app shows the first one
 * to the user, and the built-in messages follow APP_LOCALE.
 */
class RoutineSteps
{
    public const MAX_LOOP_DEPTH = 2;

    public const MAX_STEPS_PER_LEVEL = 100;

    public const MAX_STEPS = 300;

    public const MAX_TOTAL_SECONDS = 4 * 3600;

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, true> */
    private array $ids = [];

    /** @var array<int, array{int, callable}> exercise id + the step's error callback */
    private array $exerciseRefs = [];

    private int $nodes = 0;

    private function __construct(private readonly int $userId) {}

    /**
     * Validate the tree and return it with only the known keys per step type.
     *
     * @throws ValidationException
     */
    public static function validate(mixed $steps, int $userId): array
    {
        $validator = new self($userId);
        $clean = $validator->level($steps, 'steps', '', 0);
        $validator->checkExercises();
        $validator->checkTotal($clean);

        if ($validator->errors !== []) {
            throw ValidationException::withMessages($validator->errors);
        }

        return $clean;
    }

    /** Work steps count twice when done on both sides; a loop adds its rest between rounds, not after the last. */
    public static function totalSeconds(array $steps): int
    {
        return array_sum(array_map(fn (array $step) => match ($step['type']) {
            'work' => $step['seconds'] * ($step['both_sides'] ? 2 : 1),
            'rest' => $step['seconds'],
            'loop' => $step['times'] * self::totalSeconds($step['steps']) + ($step['times'] - 1) * $step['rest_between'],
        }, $steps));
    }

    /** Every node counts, loops included (matches the app's routineCountSteps). */
    public static function count(array $steps): int
    {
        return array_sum(array_map(
            fn (array $step) => 1 + ($step['type'] === 'loop' ? self::count($step['steps']) : 0),
            $steps,
        ));
    }

    /** @return array<int, int> */
    public static function exerciseIds(array $steps): array
    {
        $ids = [];
        foreach ($steps as $step) {
            if ($step['type'] === 'loop') {
                $ids = [...$ids, ...self::exerciseIds($step['steps'])];
            } elseif ($step['type'] === 'work' && $step['exercise_id'] !== null) {
                $ids[] = $step['exercise_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    public static function withoutExercise(array $steps, int $exerciseId): array
    {
        return array_map(function (array $step) use ($exerciseId) {
            if ($step['type'] === 'loop') {
                $step['steps'] = self::withoutExercise($step['steps'], $exerciseId);
            } elseif ($step['type'] === 'work' && $step['exercise_id'] === $exerciseId) {
                $step['exercise_id'] = null;
            }

            return $step;
        }, $steps);
    }

    private function level(mixed $steps, string $key, string $number, int $depth): array
    {
        $where = $number === '' ? 'A routine' : "Step {$number}: a loop";
        if (! is_array($steps) || ! array_is_list($steps) || $steps === []) {
            $this->errors[$key] = "{$where} needs at least one step.";

            return [];
        }
        if (count($steps) > self::MAX_STEPS_PER_LEVEL) {
            $this->errors[$key] = "{$where} can have at most ".self::MAX_STEPS_PER_LEVEL.' steps at one level.';

            return [];
        }

        $clean = [];
        foreach ($steps as $i => $step) {
            if ($this->nodes >= self::MAX_STEPS) {
                $this->errors['steps'] = 'A routine can have at most '.self::MAX_STEPS.' steps.';

                return [];
            }
            $stepNumber = ltrim($number.'.'.($i + 1), '.');
            $node = $this->step($step, "{$key}.{$i}", $stepNumber, $depth);
            if ($node !== null) {
                $clean[] = $node;
            }
        }

        return $clean;
    }

    private function step(mixed $step, string $key, string $number, int $depth): ?array
    {
        $this->nodes++;
        $error = fn (string $field, string $message) => $this->errors["{$key}.{$field}"] = "Step {$number}: {$message}";

        if (! is_array($step)) {
            $this->errors[$key] = "Step {$number} is not valid.";

            return null;
        }

        $id = $step['id'] ?? null;
        if (! is_string($id) || $id === '' || mb_strlen($id) > 64) {
            $error('id', 'the step id is missing or too long.');
        } elseif (isset($this->ids[$id])) {
            $error('id', 'another step has the same id.');
        } else {
            $this->ids[$id] = true;
        }

        $type = $step['type'] ?? null;
        if (! in_array($type, ['work', 'rest', 'loop'], true)) {
            $error('type', 'the step type must be work, rest or loop.');

            return null;
        }
        if ($type === 'loop' && $depth >= self::MAX_LOOP_DEPTH) {
            $error('type', 'loops can be nested at most '.self::MAX_LOOP_DEPTH.' levels deep.');

            return null;
        }

        return match ($type) {
            'work' => [
                'id' => $id,
                'type' => 'work',
                'seconds' => $this->seconds($step, $error),
                'label' => $this->text($step, 'label', 80, $error),
                'exercise_id' => $this->exercise($step, $error),
                'note' => $this->text($step, 'note', 500, $error),
                'both_sides' => $this->flag($step, 'both_sides', $error),
            ],
            'rest' => [
                'id' => $id,
                'type' => 'rest',
                'seconds' => $this->seconds($step, $error),
                'label' => $this->text($step, 'label', 80, $error),
            ],
            'loop' => [
                'id' => $id,
                'type' => 'loop',
                'times' => $this->integer($step, 'times', 1, 50, null, 'rounds must be between 1 and 50.', $error),
                'rest_between' => $this->integer($step, 'rest_between', 0, 3600, 0, 'rest between rounds must be between 0 seconds and 60 minutes.', $error),
                'steps' => $this->level($step['steps'] ?? null, "{$key}.steps", $number, $depth + 1),
            ],
        };
    }

    private function seconds(array $step, callable $error): int
    {
        return $this->integer($step, 'seconds', 1, 3600, null, 'the time must be between 1 second and 60 minutes.', $error);
    }

    private function integer(array $step, string $field, int $min, int $max, ?int $default, string $message, callable $error): int
    {
        $value = $step[$field] ?? $default;
        if (! is_int($value) || $value < $min || $value > $max) {
            $error($field, $message);

            return $min;
        }

        return $value;
    }

    private function text(array $step, string $field, int $max, callable $error): string
    {
        $value = $step[$field] ?? '';
        if (! is_string($value) || mb_strlen($value) > $max) {
            $error($field, "the {$field} can be at most {$max} characters.");

            return '';
        }

        return $value;
    }

    private function flag(array $step, string $field, callable $error): bool
    {
        $value = $step[$field] ?? false;
        if (! is_bool($value)) {
            $error($field, "{$field} must be true or false.");

            return false;
        }

        return $value;
    }

    private function exercise(array $step, callable $error): ?int
    {
        $value = $step['exercise_id'] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_int($value)) {
            $error('exercise_id', 'that exercise does not exist.');

            return null;
        }

        $this->exerciseRefs[] = [$value, $error];

        return $value;
    }

    private function checkExercises(): void
    {
        if ($this->exerciseRefs === []) {
            return;
        }

        $visible = Exercise::query()->visibleTo($this->userId)
            ->whereKey(array_unique(array_column($this->exerciseRefs, 0)))
            ->pluck('id')
            ->flip();

        foreach ($this->exerciseRefs as [$exerciseId, $error]) {
            if (! isset($visible[$exerciseId])) {
                $error('exercise_id', 'that exercise does not exist.');
            }
        }
    }

    private function checkTotal(array $clean): void
    {
        if ($this->errors === [] && self::totalSeconds($clean) > self::MAX_TOTAL_SECONDS) {
            $this->errors['steps'] = 'A routine can be at most 4 hours long.';
        }
    }
}
