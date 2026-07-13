<?php

namespace App\Services\Stride\Coach;

/**
 * Tool (function-calling) definitions the coach can invoke to actually change
 * the plan. Schemas follow the Anthropic tools format. Execution lives in
 * CoachToolExecutor.
 */
class CoachTools
{
    /**
     * @param  bool  $blockScoped  In a block-scoped chat, also offer the block-wide tools.
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public static function definitions(bool $blockScoped = false): array
    {
        $tools = [
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

        // Block-wide tools (reorder/swap/scale/regenerate the WHOLE block) — only
        // offered in a block-scoped chat. Each STAGES a proposal across all sessions.
        if ($blockScoped) {
            $tools = array_merge($tools, [
                [
                    'name' => 'reorder_block',
                    'description' => 'Reorder exercises in EVERY session of the block by a rule, e.g. "always start with calisthenics first".',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'match_by' => ['type' => 'string', 'enum' => ['category', 'name'], 'description' => 'Match exercises by catalogue category (e.g. "calisthenics") or by movement name.'],
                            'match_value' => ['type' => 'string', 'description' => 'e.g. "calisthenics", "strength", or a movement like "pull-up".'],
                            'position' => ['type' => 'string', 'enum' => ['first', 'last'], 'description' => 'Move matched exercises to the start or end of each session.'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['match_by', 'match_value', 'position'],
                    ],
                ],
                [
                    'name' => 'swap_block',
                    'description' => 'Replace an exercise with another across EVERY session of the block.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'from_exercise' => ['type' => 'string', 'description' => 'Exercise to replace (name or part).'],
                            'to_exercise' => ['type' => 'string', 'description' => 'Replacement exercise name.'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['from_exercise', 'to_exercise'],
                    ],
                ],
                [
                    'name' => 'scale_block_load',
                    'description' => 'Scale working-set loads up or down by a percentage across the whole block.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'percent' => ['type' => 'integer', 'description' => 'e.g. -10 to drop all loads 10%, +5 to add 5%.'],
                            'only_category' => ['type' => 'string', 'description' => 'Optional: limit to one category, e.g. "strength".'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['percent'],
                    ],
                ],
                [
                    'name' => 'regenerate_session',
                    'description' => 'Rebuild one whole session in the block from scratch (new exercises + sets) for a given day/title.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'session_ref' => ['type' => 'string', 'description' => 'Which session — its title, kind, or scheduled date.'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['session_ref'],
                    ],
                ],
            ]);
        }

        return $tools;
    }
}
