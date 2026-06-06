<?php

namespace App\Services\Stride\Coach;

/**
 * Tool (function-calling) definitions the coach can invoke to actually change
 * the plan. Schemas follow the Anthropic tools format. Execution lives in
 * CoachToolExecutor.
 */
class CoachTools
{
    /** @return array<int, array{name: string, description: string, input_schema: array}> */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'set_load',
                'description' => "Change the working weight (and optionally reps) for an exercise in today's session. Use when the user wants to go lighter/heavier.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string', 'description' => 'Name (or part) of the exercise in today\'s session.'],
                        'kg' => ['type' => 'number', 'description' => 'New working weight in kilograms.'],
                        'reps' => ['type' => 'integer', 'description' => 'Optional new working reps.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['exercise_name', 'kg'],
                ],
            ],
            [
                'name' => 'swap_exercise',
                'description' => "Replace one exercise in today's session with another movement.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from_exercise' => ['type' => 'string', 'description' => 'Exercise to replace (name or part).'],
                        'to_exercise' => ['type' => 'string', 'description' => 'Replacement exercise name.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale.'],
                    ],
                    'required' => ['from_exercise', 'to_exercise'],
                ],
            ],
            [
                'name' => 'add_set',
                'description' => "Add a set to an exercise in today's session.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string'],
                        'kind' => ['type' => 'string', 'enum' => ['Warm-up', 'Working', 'AMRAP', 'Drop']],
                        'reps' => ['type' => 'integer'],
                        'kg' => ['type' => 'number'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['exercise_name'],
                ],
            ],
            [
                'name' => 'log_injury',
                'description' => 'Record an injury/niggle so the coach programs around it going forward.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body_part' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                        'severity' => ['type' => 'string', 'enum' => ['Mild', 'Moderate', 'Severe']],
                        'avoid' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Movements to avoid.'],
                    ],
                    'required' => ['body_part', 'note'],
                ],
            ],
            [
                'name' => 'remember_fact',
                'description' => 'Persist a durable fact about the user (preference, constraint) to recall in future conversations.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'fact' => ['type' => 'string', 'description' => 'A concise, lasting fact, e.g. "trains fasted in the AM".'],
                    ],
                    'required' => ['fact'],
                ],
            ],
        ];
    }
}
