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
                'description' => 'Change the working weight (and optionally reps) for an exercise in a session — today\'s by default, or any block session via session_ref. Use when the user wants to go lighter/heavier.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string', 'description' => 'Name (or part) of the exercise in the session.'],
                        'kg' => ['type' => 'number', 'description' => 'New working weight in kilograms.'],
                        'reps' => ['type' => 'integer', 'description' => 'Optional new working reps.'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['exercise_name', 'kg'],
                ],
            ],
            [
                'name' => 'swap_exercise',
                'description' => 'Replace one exercise with another movement in a session — today\'s by default, or any block session via session_ref.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from_exercise' => ['type' => 'string', 'description' => 'Exercise to replace (name or part).'],
                        'to_exercise' => ['type' => 'string', 'description' => 'Replacement exercise name.'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale.'],
                    ],
                    'required' => ['from_exercise', 'to_exercise'],
                ],
            ],
            [
                'name' => 'add_set',
                'description' => 'Add a set to an exercise in a session — today\'s by default, or any block session via session_ref.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string'],
                        'kind' => ['type' => 'string', 'enum' => ['Warm-up', 'Working', 'AMRAP', 'Drop']],
                        'reps' => ['type' => 'integer'],
                        'kg' => ['type' => 'number'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['exercise_name'],
                ],
            ],
            [
                'name' => 'add_exercise',
                'description' => 'Add a brand-new exercise (with default working sets) to a session — today\'s by default, or any block session via session_ref. You can add a movement on its own; you never need to remove one to make room.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Exercise name to add.'],
                        'tag' => ['type' => 'string', 'enum' => ['Compound', 'Isolation'], 'description' => 'Movement type. Defaults to Compound.'],
                        'sets' => ['type' => 'integer', 'description' => 'Number of working sets (default 3).'],
                        'reps' => ['type' => 'integer', 'description' => 'Target reps per set (default 8).'],
                        'kg' => ['type' => 'number', 'description' => 'Optional working weight in kilograms.'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'remove_set',
                'description' => 'Remove the last not-yet-done set from an exercise in a session — today\'s by default. Use when the user wants less volume on one movement.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string', 'description' => 'Name (or part) of the exercise in the session.'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['exercise_name'],
                ],
            ],
            [
                'name' => 'remove_exercise',
                'description' => 'Drop a whole exercise from a session — today\'s by default. To cut a session short, call this once per exercise to drop (never removes already-done work).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'exercise_name' => ['type' => 'string', 'description' => 'Name (or part) of the exercise to drop.'],
                        'session_ref' => ['type' => 'string', 'description' => 'Optional: which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today\'s session.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['exercise_name'],
                ],
            ],
            [
                'name' => 'move_session',
                'description' => "Reschedule ONE upcoming session to a different FUTURE day (its exercises come along unchanged). Use whenever the athlete wants a workout on another date — \"do legs tomorrow instead\", \"push Friday's session to Sunday\". This is the ONLY way to change a session's date; changing what a day trains is change_session_kind and REPLACES the workout, so never use that to move something. If the athlete ALREADY trained the session on a past day, use log_past_session instead — this tool only reschedules, it never marks a session done.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'session_ref' => ['type' => 'string', 'description' => "Which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today's session."],
                        'date' => ['type' => 'string', 'description' => 'The new date, YYYY-MM-DD. Must not be in the past.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['date'],
                ],
            ],
            [
                'name' => 'shift_plan',
                'description' => 'Move the whole remaining plan earlier or later by a number of days, keeping the order and spacing. Use for "shift everything a day closer", "push the rest of the week back two days". Only upcoming, unstarted sessions move; finished and in-progress ones stay where they are.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Negative = earlier (closer), positive = later. e.g. -1 shifts everything one day closer.'],
                        'from' => ['type' => 'string', 'description' => 'Optional: only shift sessions on/after this date (YYYY-MM-DD). Defaults to today.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['days'],
                ],
            ],
            [
                'name' => 'log_past_session',
                'description' => "Mark a scheduled session as ALREADY DONE on a past (or today's) date — use when the athlete says they already trained it on a different or earlier day (\"I did Monday's legs yesterday\", \"already did today's push on Saturday\"). It records the session as completed on that day so it lands in history/recent on the right date. This is the ONLY way to log a workout that already happened; move_session only reschedules future sessions and never marks them done. If training early frees up a day and they want to close the gap (fewer rest days), also call shift_plan.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'session_ref' => ['type' => 'string', 'description' => "Which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today's session."],
                        'date' => ['type' => 'string', 'description' => 'The day it was actually trained, YYYY-MM-DD. Today or in the past — never the future.'],
                        'rpe' => ['type' => 'integer', 'description' => 'Optional perceived effort 1-10, only if the athlete mentions how hard it was.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['date'],
                ],
            ],
            [
                'name' => 'remove_session',
                'description' => "Delete a whole session (training day) from the plan — use when the athlete wants a day removed ENTIRELY, not moved or rebuilt (\"drop Monday's legs, I don't want it\", \"remove that extra day\"). This is the only way to delete a session. It refuses a session that already has logged sets or is done — that is recorded history (skip or move it instead). Removing leaves a gap that day; if the athlete wants the rest of the plan pulled up to close it, also call shift_plan.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'session_ref' => ['type' => 'string', 'description' => "Which session — its title, kind, or scheduled date (YYYY-MM-DD). Defaults to today's session."],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'set_warmup_style',
                'description' => "Change HOW warm-ups are structured across the plan: 'grouped' = one warm-up block before the whole session, 'per_exercise' = a warm-up set on each working exercise. Use when the user asks for the warm-up as a block up front (or back on each exercise). Applies to today's and every upcoming unstarted session, and to future generated ones.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'style' => ['type' => 'string', 'enum' => ['grouped', 'per_exercise'], 'description' => 'grouped = one block before the session; per_exercise = warm-up set on each exercise.'],
                        'reason' => ['type' => 'string', 'description' => 'Short rationale shown to the user.'],
                    ],
                    'required' => ['style'],
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
                    'description' => 'Rebuild one whole session in the block from scratch (new exercises + sets) for a given day/title. Keeps what the session trains (its kind) — to change that, use change_session_kind. Never use on a session the athlete has already started — use set_load/add_set/remove_set/remove_exercise/swap_exercise instead.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'session_ref' => ['type' => 'string', 'description' => 'Which session — its title, kind, or scheduled date.'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['session_ref'],
                    ],
                ],
                [
                    'name' => 'change_session_kind',
                    'description' => 'Change WHAT a session in the block trains (its kind, e.g. Push → Pull) and rebuild its exercises to match. Use when the user wants a different day order, e.g. "start today with Pull instead of Push" — call it once per session to change.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'session_ref' => ['type' => 'string', 'description' => 'Which session — its title, kind, or scheduled date (YYYY-MM-DD).'],
                            'new_kind' => ['type' => 'string', 'enum' => ['Push', 'Pull', 'Legs', 'Upper', 'Lower', 'Full body'], 'description' => 'What the session should train instead.'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['session_ref', 'new_kind'],
                    ],
                ],
            ]);
        }

        return $tools;
    }
}
