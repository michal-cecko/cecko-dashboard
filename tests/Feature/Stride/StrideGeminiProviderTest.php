<?php

namespace Tests\Feature\Stride;

use App\Services\Stride\Coach\CoachTurn;
use App\Services\Stride\Coach\GeminiCoachProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class StrideGeminiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.api_key', 'test-key');
        config()->set('ai.gemini.url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    public function test_translates_turn_to_gemini_format_and_parses_function_calls(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'role' => 'model',
                        'parts' => [
                            [
                                'functionCall' => ['name' => 'set_load', 'args' => ['exercise' => 'Bench Press', 'load_kg' => 60]],
                                'thoughtSignature' => 'sig-abc',
                            ],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 30, 'cachedContentTokenCount' => 40],
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'gemini-2.5-flash',
            systemBlocks: [
                ['text' => 'You are a coach.', 'cache' => true],
                ['text' => 'TRAINING MEMORY: squats on Monday.', 'cache' => true],
            ],
            messages: [
                ['role' => 'user', 'content' => 'Bench felt too heavy today.'],
                ['role' => 'assistant', 'content' => [
                    ['type' => 'text', 'text' => 'Let me check.'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'set_load', 'input' => (object) ['exercise' => 'Bench Press'], 'signature' => 'sig-prev'],
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

        $reply = (new GeminiCoachProvider)->chat($turn);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return str_contains($request->url(), '/models/gemini-2.5-flash:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $body['generationConfig'] === ['maxOutputTokens' => 512]
                // system blocks → systemInstruction parts (kept separate)
                && $body['systemInstruction']['parts'] === [
                    ['text' => 'You are a coach.'],
                    ['text' => 'TRAINING MEMORY: squats on Monday.'],
                ]
                // user string message → contents[0]
                && $body['contents'][0] === ['role' => 'user', 'parts' => [['text' => 'Bench felt too heavy today.']]]
                // assistant text + tool_use → role "model" with text + functionCall parts
                && $body['contents'][1]['role'] === 'model'
                && $body['contents'][1]['parts'][0] === ['text' => 'Let me check.']
                && $body['contents'][1]['parts'][1]['functionCall']['name'] === 'set_load'
                // args is serialised as a JSON object (stdClass here), so {} not [] when empty
                && (array) $body['contents'][1]['parts'][1]['functionCall']['args'] === ['exercise' => 'Bench Press']
                // tool_result → role "user" with a functionResponse whose name is
                // resolved from the earlier tool_use of the same id
                && $body['contents'][2]['role'] === 'user'
                && $body['contents'][2]['parts'][0]['functionResponse'] === [
                    'name' => 'set_load',
                    'response' => ['result' => 'Load updated to 62.5kg'],
                ]
                // anthropic tool defs → gemini functionDeclarations
                && $body['tools'][0]['functionDeclarations'][0]['name'] === 'set_load'
                && $body['tools'][0]['functionDeclarations'][0]['parameters'] === ['type' => 'object', 'properties' => []]
                && $body['toolConfig']['functionCallingConfig']['mode'] === 'AUTO';
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
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['role' => 'model', 'parts' => [['text' => "Let's pull back 10% today."]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 20],
            ]),
        ]);

        $turn = new CoachTurn(
            model: 'gemini-2.5-flash',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Feeling drained.']],
        );

        $reply = (new GeminiCoachProvider)->chat($turn);

        $this->assertFalse($reply->wantsTools());
        $this->assertSame("Let's pull back 10% today.", $reply->text);
        $this->assertSame('end_turn', $reply->stopReason);
    }

    public function test_rejects_a_non_gemini_model_id(): void
    {
        $turn = new CoachTurn(
            model: 'claude-haiku-4-5',
            systemBlocks: [['text' => 'You are a coach.']],
            messages: [['role' => 'user', 'content' => 'Hi.']],
        );

        $this->expectException(RuntimeException::class);

        (new GeminiCoachProvider)->chat($turn);
    }
}
