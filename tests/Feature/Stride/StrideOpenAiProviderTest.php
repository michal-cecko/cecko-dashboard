<?php

namespace Tests\Feature\Stride;

use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\OpenAiCoachProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class StrideOpenAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'sk-test-key');
        config()->set('ai.openai.url', 'https://api.openai.com/v1');
    }

    public function test_translates_turn_to_openai_format_and_parses_tool_calls(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'set_load', 'arguments' => '{"exercise":"Bench Press","load_kg":60}'],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30, 'prompt_tokens_details' => ['cached_tokens' => 40]],
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'gpt-5-mini',
            systemBlocks: [
                ['text' => 'You are a coach.', 'cache' => true],
                ['text' => 'TRAINING MEMORY: squats on Monday.', 'cache' => true],
            ],
            messages: [
                ['role' => 'user', 'content' => 'Bench felt too heavy today.'],
                ['role' => 'assistant', 'content' => [
                    ['type' => 'text', 'text' => 'Let me check.'],
                    ['type' => 'tool_use', 'id' => 'call_prev', 'name' => 'set_load', 'input' => ['exercise' => 'Bench Press']],
                ]],
                ['role' => 'user', 'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => 'call_prev', 'content' => 'Load updated to 62.5kg'],
                ]],
            ],
            tools: [
                ['name' => 'set_load', 'description' => 'Change working load', 'input_schema' => ['type' => 'object', 'properties' => []]],
            ],
            maxTokens: 512,
        );

        $reply = (new OpenAiCoachProvider)->chat($turn);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer sk-test-key')
                && $body['model'] === 'gpt-5-mini'
                && $body['max_completion_tokens'] === 512
                // system blocks → one leading system message
                && $body['messages'][0] === ['role' => 'system', 'content' => "You are a coach.\n\nTRAINING MEMORY: squats on Monday."]
                // user string message
                && $body['messages'][1] === ['role' => 'user', 'content' => 'Bench felt too heavy today.']
                // assistant text + tool_use → content + tool_calls
                && $body['messages'][2]['role'] === 'assistant'
                && $body['messages'][2]['content'] === 'Let me check.'
                && $body['messages'][2]['tool_calls'][0]['id'] === 'call_prev'
                && $body['messages'][2]['tool_calls'][0]['function']['name'] === 'set_load'
                // tool_result → role "tool" keyed by tool_call_id
                && $body['messages'][3] === ['role' => 'tool', 'tool_call_id' => 'call_prev', 'content' => 'Load updated to 62.5kg']
                // anthropic tool defs → openai function tools
                && $body['tools'][0]['type'] === 'function'
                && $body['tools'][0]['function']['name'] === 'set_load'
                && $body['tool_choice'] === 'auto';
        });

        $this->assertTrue($reply->wantsTools());
        $this->assertSame('set_load', $reply->toolUses[0]['name']);
        $this->assertSame(['exercise' => 'Bench Press', 'load_kg' => 60], $reply->toolUses[0]['input']);
        $this->assertSame('tool_use', $reply->stopReason);
        $this->assertSame(120, $reply->usage->inputTokens);
        $this->assertSame(30, $reply->usage->outputTokens);
        $this->assertSame(40, $reply->usage->cacheReadTokens);
    }

    public function test_parses_plain_text_reply(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => "Let's pull back 10% today."],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 20],
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'gpt-5-mini',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Feeling drained.']],
        );

        $reply = (new OpenAiCoachProvider)->chat($turn);

        $this->assertFalse($reply->wantsTools());
        $this->assertSame("Let's pull back 10% today.", $reply->text);
        $this->assertSame('end_turn', $reply->stopReason);
    }

    public function test_rejects_a_non_openai_model_id(): void
    {
        $turn = new CoachTurn(
            model: 'gemini-2.5-flash',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Hi.']],
        );

        $this->expectException(RuntimeException::class);

        (new OpenAiCoachProvider)->chat($turn);
    }

    public function test_uses_the_injected_credentials_over_config(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'gpt-5-mini',
            systemBlocks: [['text' => 'x']],
            messages: [['role' => 'user', 'content' => 'hi']],
        );

        (new OpenAiCoachProvider(new \App\Services\Common\Ai\AiCredentials(apiKey: 'sk-user-byok')))->chat($turn);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-user-byok'));
    }
}
