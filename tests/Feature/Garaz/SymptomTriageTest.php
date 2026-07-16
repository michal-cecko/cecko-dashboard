<?php

namespace Tests\Feature\Garaz;

use App\Models\Common\User;
use App\Models\Garaz\AiUsage;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\SymptomTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SymptomTriageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_returns_reply_text_and_logs_usage(): void
    {
        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.default_model', 'claude-sonnet-4-6');

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Najpravdepodobnejšia príčina: napinák rozvodovej reťaze.']],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 1200,
                    'output_tokens' => 300,
                    'cache_creation_input_tokens' => 800,
                    'cache_read_input_tokens' => 0,
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();

        $reply = app(SymptomTriageService::class)->ask($vehicle, 'Rachot pri studenom štarte');

        $this->assertSame('Najpravdepodobnejšia príčina: napinák rozvodovej reťaze.', $reply);

        $this->assertDatabaseHas('garaz_ai_usage', [
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'purpose' => 'symptom_triage',
            'input_tokens' => 1200,
            'output_tokens' => 300,
            'cache_creation_tokens' => 800,
            'cache_read_tokens' => 0,
        ]);

        $usage = AiUsage::sole();
        $this->assertEqualsWithDelta(0.0111, $usage->cost_usd, 0.0001);
        $this->assertNotNull($usage->latency_ms);
    }

    public function test_ask_without_api_key_throws_and_logs_nothing(): void
    {
        config()->set('services.anthropic.api_key', null);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->expectException(RuntimeException::class);

        try {
            app(SymptomTriageService::class)->ask($vehicle, 'Rachot pri studenom štarte');
        } finally {
            $this->assertDatabaseCount('garaz_ai_usage', 0);
        }
    }
}
