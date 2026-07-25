<?php

namespace Tests\Feature\Stride;

use App\Models\Common\AiConnection;
use App\Models\Common\User;
use App\Models\Stride\CoachConversation;
use App\Services\Common\Ai\AiProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StrideAiConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'sk-app-key');

        $this->user = User::factory()->strideUser()->create(['email' => 'byok@example.test', 'password' => 'secret-pass']);
        $token = $this->postJson('/api/stride/auth/login', ['email' => 'byok@example.test', 'password' => 'secret-pass'])->json('token');
        $this->auth = ['Authorization' => "Bearer {$token}"];
    }

    public function test_show_returns_free_default_and_catalog(): void
    {
        $response = $this->getJson('/api/stride/ai/connection', $this->auth)->assertOk();

        $response->assertJsonPath('ai.connection.provider', 'free');
        $this->assertNotEmpty($response->json('ai.catalog.providers'));
        // Catalog includes the built-in providers + the free tier.
        $keys = collect($response->json('ai.catalog.providers'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['anthropic', 'gemini', 'openai', 'free'], $keys);
    }

    public function test_store_validates_and_saves_a_working_api_key(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'OK'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $this->postJson('/api/stride/ai/connection', [
            'provider' => 'openai',
            'auth_type' => 'api_key',
            'api_key' => 'sk-user-byok',
            'model' => 'gpt-5',
        ], $this->auth)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai.connection.provider', 'openai')
            ->assertJsonPath('ai.connection.model', 'gpt-5')
            ->assertJsonPath('ai.connection.status', 'active');

        // The probe fired against the chosen model with the user's key.
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer sk-user-byok') && $r->data()['model'] === 'gpt-5');

        $connection = AiConnection::firstWhere('user_id', $this->user->id);
        $this->assertSame('active', $connection->status);
        // Stored encrypted; the public payload never exposes it.
        $this->assertSame('sk-user-byok', $connection->credentials['api_key']);
    }

    public function test_store_marks_an_invalid_key_as_invalid(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('unauthorized', 401)]);

        $this->postJson('/api/stride/ai/connection', [
            'provider' => 'openai',
            'auth_type' => 'api_key',
            'api_key' => 'sk-bad',
            'model' => 'gpt-5',
        ], $this->auth)->assertStatus(422)->assertJsonPath('ok', false);

        $this->assertSame('invalid', AiConnection::firstWhere('user_id', $this->user->id)->status);
    }

    public function test_store_rejects_an_unknown_model(): void
    {
        $this->postJson('/api/stride/ai/connection', [
            'provider' => 'openai',
            'auth_type' => 'api_key',
            'api_key' => 'sk-user-byok',
            'model' => 'gpt-does-not-exist',
        ], $this->auth)->assertStatus(422);
    }

    public function test_free_and_disconnect(): void
    {
        AiConnection::create(['user_id' => $this->user->id, 'provider' => 'openai', 'auth_type' => 'api_key', 'credentials' => ['api_key' => 'sk-x'], 'model' => 'gpt-5', 'status' => 'active']);

        $this->postJson('/api/stride/ai/connection', ['provider' => 'free', 'auth_type' => 'none'], $this->auth)
            ->assertOk()
            ->assertJsonPath('ai.connection.provider', 'free');

        $this->deleteJson('/api/stride/ai/connection', [], $this->auth)->assertOk();
        $this->assertNull(AiConnection::firstWhere('user_id', $this->user->id));
    }

    public function test_resolver_picks_the_byok_provider_and_model(): void
    {
        AiConnection::create([
            'user_id' => $this->user->id, 'provider' => 'openai', 'auth_type' => 'api_key',
            'credentials' => ['api_key' => 'sk-user-byok'], 'model' => 'gpt-5', 'status' => 'active',
        ]);

        $resolved = app(AiProviderResolver::class)->for($this->user->fresh());

        $this->assertTrue($resolved->isByok);
        $this->assertSame('openai', $resolved->providerKey);
        $this->assertSame('openai', $resolved->driverName());
        $this->assertSame('gpt-5', $resolved->model('chat'));
    }

    public function test_resolver_falls_back_to_free_when_status_invalid(): void
    {
        AiConnection::create([
            'user_id' => $this->user->id, 'provider' => 'openai', 'auth_type' => 'api_key',
            'credentials' => ['api_key' => 'sk-bad'], 'model' => 'gpt-5', 'status' => 'invalid',
        ]);

        $resolved = app(AiProviderResolver::class)->for($this->user->fresh());

        $this->assertFalse($resolved->isByok);
        $this->assertSame('free', $resolved->providerKey);
    }

    public function test_in_chat_model_switch_rejects_a_cross_provider_model(): void
    {
        AiConnection::create([
            'user_id' => $this->user->id, 'provider' => 'openai', 'auth_type' => 'api_key',
            'credentials' => ['api_key' => 'sk-user-byok'], 'model' => 'gpt-5', 'status' => 'active',
        ]);

        $conversation = CoachConversation::create(['user_id' => $this->user->id, 'persona_key' => 'calm']);

        // A valid openai model sticks.
        $this->patchJson("/api/stride/coach/conversations/{$conversation->id}/model", ['model' => 'gpt-5-mini'], $this->auth)
            ->assertOk()
            ->assertJsonPath('conversation.model', 'gpt-5-mini');

        // A Gemini model on an OpenAI connection is rejected.
        $this->patchJson("/api/stride/coach/conversations/{$conversation->id}/model", ['model' => 'gemini-2.5-pro'], $this->auth)
            ->assertStatus(422);
    }
}
