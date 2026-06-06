<?php

namespace Tests\Feature\Stride;

use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\OllamaCoachProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class StrideOllamaProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stride.coach.ollama.url', 'http://localhost:11434');
        config()->set('stride.coach.ollama.model', 'qwen3:8b');
        config()->set('stride.coach.ollama.think', false);
    }

    public function test_translates_turn_to_ollama_format_and_parses_tool_calls(): void
    {
        Http::fake([
            'localhost:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [
                        ['function' => ['name' => 'set_load', 'arguments' => ['exercise' => 'Bench Press', 'load_kg' => 60]]],
                    ],
                ],
                'done_reason' => 'stop',
                'prompt_eval_count' => 120,
                'eval_count' => 30,
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'claude-haiku-4-5', // gateway model id is ignored by the ollama driver
            systemBlocks: [
                ['text' => 'You are a coach.', 'cache' => true],
                ['text' => 'TRAINING MEMORY: squats on Monday.', 'cache' => true],
            ],
            messages: [
                ['role' => 'user', 'content' => 'Bench felt too heavy today.'],
                ['role' => 'assistant', 'content' => [
                    ['type' => 'text', 'text' => 'Let me check.'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'set_load', 'input' => (object) ['exercise' => 'Bench Press']],
                ]],
                ['role' => 'user', 'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => 'tu_1', 'content' => 'Load updated to 62.5kg'],
                ]],
            ],
            tools: [
                ['name' => 'set_load', 'description' => 'Change working load', 'input_schema' => ['type' => 'object', 'properties' => []]],
            ],
            maxTokens: 512,
        );

        $reply = (new OllamaCoachProvider)->chat($turn);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'http://localhost:11434/api/chat'
                && $body['model'] === 'qwen3:8b'
                && $body['stream'] === false
                && $body['think'] === false
                && $body['options'] === ['num_predict' => 512]
                // system blocks fold into one leading system message
                && $body['messages'][0] === ['role' => 'system', 'content' => "You are a coach.\n\nTRAINING MEMORY: squats on Monday."]
                && $body['messages'][1] === ['role' => 'user', 'content' => 'Bench felt too heavy today.']
                // anthropic tool_use block → ollama tool_calls
                && $body['messages'][2]['role'] === 'assistant'
                && $body['messages'][2]['content'] === 'Let me check.'
                && $body['messages'][2]['tool_calls'][0]['function']['name'] === 'set_load'
                // anthropic tool_result block → ollama role=tool message
                && $body['messages'][3] === ['role' => 'tool', 'content' => 'Load updated to 62.5kg']
                // anthropic tool defs → ollama function format
                && $body['tools'][0]['type'] === 'function'
                && $body['tools'][0]['function']['name'] === 'set_load'
                && $body['tools'][0]['function']['parameters'] === ['type' => 'object', 'properties' => []];
        });

        $this->assertTrue($reply->wantsTools());
        $this->assertSame('set_load', $reply->toolUses[0]['name']);
        $this->assertSame(['exercise' => 'Bench Press', 'load_kg' => 60], $reply->toolUses[0]['input']);
        $this->assertSame('tool_use', $reply->stopReason);
        $this->assertSame(120, $reply->usage->inputTokens);
        $this->assertSame(30, $reply->usage->outputTokens);
    }

    public function test_parses_plain_text_reply_and_strips_thinking(): void
    {
        Http::fake([
            'localhost:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => "<think>user is tired, deload</think>Let's pull back 10% today."],
                'done_reason' => 'stop',
                'prompt_eval_count' => 80,
                'eval_count' => 20,
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'claude-haiku-4-5',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Feeling drained.']],
        );

        $reply = (new OllamaCoachProvider)->chat($turn);

        $this->assertFalse($reply->wantsTools());
        $this->assertSame("Let's pull back 10% today.", $reply->text);
        $this->assertSame('stop', $reply->stopReason);
    }

    public function test_strips_reasoning_with_dangling_close_tag(): void
    {
        // qwen3 thinking-2507 models emit reasoning with only a closing tag.
        Http::fake([
            'localhost:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => "The user wants a short answer, so...\n</think>\n\nDeload 10% today."],
                'done_reason' => 'stop',
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'claude-haiku-4-5',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Feeling drained.']],
        );

        $reply = (new OllamaCoachProvider)->chat($turn);

        $this->assertSame('Deload 10% today.', $reply->text);
    }

    public function test_unreachable_ollama_raises_actionable_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $turn = new CoachTurn(
            model: 'claude-haiku-4-5',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Hello?']],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is it running?');

        (new OllamaCoachProvider)->chat($turn);
    }
}
