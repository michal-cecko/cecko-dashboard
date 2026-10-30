<?php

namespace Tests\Feature\Stride;

use App\Models\Common\User;
use App\Models\Common\UserApiToken;
use App\Models\Stride\Block;
use App\Models\Stride\PushDelivery;
use App\Models\Stride\PushSubscription;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Models\Stride\WeightEntry;
use App\Services\Stride\Coach\PokeService;
use App\Services\Stride\Push\PushScheduler;
use App\Services\Stride\Push\PushSendResult;
use App\Services\Stride\Push\PushTransport;
use App\Services\Stride\Push\WebPushTransport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\VAPID;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\Support\Stride\FakePushTransport;
use Tests\TestCase;

class StridePushTest extends TestCase
{
    use RefreshDatabase;

    private FakePushTransport $transport;

    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->transport);
        $this->mock(PokeService::class)->shouldReceive('pokes')->zeroOrMoreTimes()->andReturn([]);
        config([
            'stride-push.public_key' => 'public-key',
            'stride-push.private_key' => 'private-key',
            'stride-push.subject' => 'mailto:test@example.test',
        ]);
    }

    public function test_push_config_requires_auth_and_returns_safe_defaults(): void
    {
        $this->getJson('/api/stride/push/config')->assertUnauthorized();
        $user = $this->user();

        $this->getJson('/api/stride/push/config', $this->auth($user))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('public_key', 'public-key')
            ->assertJsonPath('subscribed', false)
            ->assertJsonPath('preferences.workout_reminder', false)
            ->assertJsonPath('preferences.workout_reminder_time', '18:00')
            ->assertJsonPath('preferences.weigh_reminder', false)
            ->assertJsonPath('preferences.weigh_reminder_time', '06:00')
            ->assertJsonPath('preferences.coach_pokes', false)
            ->assertJsonPath('preferences.notification_timezone', 'Europe/Prague');

        config(['stride-push.private_key' => null]);
        $this->getJson('/api/stride/push/config', $this->auth($user))
            ->assertJsonPath('configured', false)
            ->assertJsonPath('public_key', null);
    }

    public function test_preferences_are_partial_validated_and_keep_existing_profile_values(): void
    {
        $user = $this->user();
        StrideProfile::create(['user_id' => $user->id, 'preferences' => ['language' => 'sk', 'coach_pokes' => true]]);
        $auth = $this->auth($user);

        $this->patchJson('/api/stride/push/preferences', [
            'weigh_reminder' => true,
            'weigh_reminder_time' => '07:15',
            'notification_timezone' => 'Europe/Prague',
        ], $auth)
            ->assertOk()
            ->assertJsonPath('preferences.weigh_reminder', true)
            ->assertJsonPath('preferences.weigh_reminder_time', '07:15')
            ->assertJsonPath('preferences.coach_pokes', true);

        $this->assertSame('sk', StrideProfile::where('user_id', $user->id)->first()->preferences['language']);
        $this->patchJson('/api/stride/push/preferences', ['notification_timezone' => 'Mars/Olympus'], $auth)->assertUnprocessable();
        $this->patchJson('/api/stride/push/preferences', ['workout_reminder_time' => '25:99'], $auth)->assertUnprocessable();
    }

    public function test_subscription_is_token_scoped_and_rejects_other_owner_and_private_endpoints(): void
    {
        $first = $this->user();
        $second = $this->user();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-subscription';
        $body = ['subscription' => [
            'endpoint' => $endpoint,
            'expirationTime' => null,
            'keys' => ['p256dh' => $this->publicKey(), 'auth' => $this->authKey()],
        ]];

        $this->postJson('/api/stride/push/subscriptions', $body, $this->auth($first))->assertCreated();
        $this->getJson('/api/stride/push/config?endpoint='.urlencode($endpoint), $this->auth($first))
            ->assertOk()
            ->assertJsonPath('subscribed', true);
        $this->getJson('/api/stride/push/config?endpoint='.urlencode($endpoint), $this->auth($second))
            ->assertOk()
            ->assertJsonPath('subscribed', false);
        $otherRaw = fake()->uuid();
        UserApiToken::create([
            'user_id' => $first->id,
            'name' => 'Other device',
            'token' => hash('sha256', $otherRaw),
            'abilities' => ['stride'],
        ]);
        $this->getJson('/api/stride/push/config?endpoint='.urlencode($endpoint), [
            'Authorization' => 'Bearer '.$otherRaw,
        ])->assertOk()->assertJsonPath('subscribed', false);
        $this->postJson('/api/stride/push/subscriptions', $body, $this->auth($second))->assertUnprocessable();
        $this->postJson('/api/stride/push/subscriptions', [
            'subscription' => [...$body['subscription'], 'endpoint' => 'https://127.0.0.1/private'],
        ], $this->auth($second))->assertUnprocessable();
        $this->postJson('/api/stride/push/subscriptions', [
            'subscription' => [...$body['subscription'], 'endpoint' => 'https://attacker.fcm.googleapis.com/send/test'],
        ], $this->auth($second))->assertUnprocessable();

        $this->postJson('/api/stride/push/test', ['endpoint' => $endpoint], $this->auth($second))->assertNotFound();
        $this->postJson('/api/stride/push/test', ['endpoint' => $endpoint], $this->auth($first))->assertOk();
        $this->postJson('/api/stride/push/test', ['endpoint' => $endpoint], $this->auth($first))->assertStatus(429);
        $this->assertCount(1, $this->transport->sent);

        $this->deleteJson('/api/stride/push/subscriptions', ['endpoint' => $endpoint], $this->auth($second))->assertOk();
        $this->assertDatabaseCount('stride_push_subscriptions', 1);
        $this->deleteJson('/api/stride/push/subscriptions', ['endpoint' => $endpoint], $this->auth($first))->assertOk();
        $this->assertDatabaseCount('stride_push_subscriptions', 0);
    }

    public function test_subscription_rejects_invalid_browser_keys(): void
    {
        $user = $this->user();
        $this->postJson('/api/stride/push/subscriptions', ['subscription' => [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            'keys' => ['p256dh' => 'bad', 'auth' => 'bad'],
        ]], $this->auth($user))->assertUnprocessable();
    }

    public function test_push_test_reports_when_delivery_is_not_configured(): void
    {
        $user = $this->user();
        $token = $this->token($user);
        $subscription = $this->subscription($user, $token);
        config(['stride-push.private_key' => null]);

        $rawToken = fake()->uuid();
        $token->update(['token' => hash('sha256', $rawToken)]);
        $this->postJson('/api/stride/push/test', ['endpoint' => $subscription->endpoint], [
            'Authorization' => 'Bearer '.$rawToken,
        ])->assertStatus(503)->assertJsonPath('message', 'Push notifications are not configured.');
    }

    public function test_due_weight_reminder_uses_local_day_dedupes_and_retries_temporary_failure(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 03:59:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'weigh_reminder' => true,
            'weigh_reminder_time' => '06:00',
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $subscription = $this->subscription($user, $token);
        $subscription->forceFill(['created_at' => CarbonImmutable::parse('2026-10-17 03:59:00 UTC')])->save();
        CarbonImmutable::setTestNow('2026-10-17 04:03:00 UTC');
        $this->transport->results = [PushSendResult::temporaryFailure(), PushSendResult::sent()];

        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
        $this->assertSame(1, app(PushScheduler::class)->sendDue());
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertDatabaseCount('stride_push_deliveries', 1);
        $this->assertSame('/?screen=weight', $this->transport->sent[1]['url']);
    }

    public function test_workout_reminder_skips_rest_done_and_skipped_sessions(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 16:01:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'workout_reminder' => true,
            'workout_reminder_time' => '18:00',
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $subscription = $this->subscription($user, $token);
        $block = Block::create(['user_id' => $user->id, 'name' => 'Block', 'status' => 'active']);
        Session::create(['user_id' => $user->id, 'block_id' => $block->id, 'kind' => 'Rest', 'title' => 'Rest', 'status' => 'planned', 'scheduled_date' => '2026-10-17']);

        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertCount(0, $this->transport->sent);

        Session::query()->delete();
        Session::create(['user_id' => $user->id, 'block_id' => $block->id, 'kind' => 'Pull', 'title' => 'Pull day', 'status' => 'done', 'scheduled_date' => '2026-10-17']);
        $this->assertSame(0, app(PushScheduler::class)->sendDue());

        Session::query()->delete();
        Session::create(['user_id' => $user->id, 'block_id' => $block->id, 'kind' => 'Pull', 'title' => 'Pull day', 'status' => 'skipped', 'scheduled_date' => '2026-10-17']);
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertDatabaseMissing('stride_push_deliveries', ['subscription_id' => $subscription->id]);
    }

    public function test_due_workout_reminder_is_sent_once_for_an_active_training_session(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 16:02:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'workout_reminder' => true,
            'workout_reminder_time' => '18:00',
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $subscription = $this->subscription($user, $token);
        $subscription->forceFill(['created_at' => CarbonImmutable::parse('2026-10-17 15:59:00 UTC')])->save();
        $block = Block::create(['user_id' => $user->id, 'name' => 'Block', 'status' => 'active']);
        Session::create([
            'user_id' => $user->id,
            'block_id' => $block->id,
            'kind' => 'Pull',
            'title' => 'Pull day',
            'status' => 'planned',
            'scheduled_date' => '2026-10-17',
        ]);

        $this->assertSame(1, app(PushScheduler::class)->sendDue());
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertSame('Training reminder', $this->transport->sent[0]['title']);
        $this->assertSame('Your Pull day session is ready when you are.', $this->transport->sent[0]['body']);
        $this->assertSame('/?screen=home', $this->transport->sent[0]['url']);
        $this->assertDatabaseHas('stride_push_deliveries', [
            'type' => 'workout',
        ]);
        $this->assertSame('2026-10-17', PushDelivery::query()->where('type', 'workout')->firstOrFail()->local_date->toDateString());
    }

    public function test_workout_reminder_text_uses_slovak_profile_language(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 16:02:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'workout_reminder' => true,
            'workout_reminder_time' => '18:00',
            'notification_timezone' => 'Europe/Prague',
            'language' => 'sk',
        ]]);
        $subscription = $this->subscription($user, $token);
        $subscription->forceFill(['created_at' => CarbonImmutable::parse('2026-10-17 15:59:00 UTC')])->save();
        $block = Block::create(['user_id' => $user->id, 'name' => 'Block', 'status' => 'active']);
        Session::create([
            'user_id' => $user->id,
            'block_id' => $block->id,
            'kind' => 'Pull',
            'title' => 'Pull day',
            'status' => 'planned',
            'scheduled_date' => '2026-10-17',
        ]);

        $this->assertSame(1, app(PushScheduler::class)->sendDue());
        $this->assertSame('Pripomienka tréningu', $this->transport->sent[0]['title']);
        $this->assertSame('Čaká ťa tréning: Pull day.', $this->transport->sent[0]['body']);
    }

    public function test_weigh_reminder_is_skipped_after_a_local_day_entry(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 04:01:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'weigh_reminder' => true,
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $this->subscription($user, $token);
        WeightEntry::create([
            'user_id' => $user->id,
            'recorded_on' => '2026-10-17',
            'kg' => 80,
        ]);

        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertCount(0, $this->transport->sent);
    }

    public function test_expired_subscriptions_are_removed_and_temporary_errors_are_not_recorded(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 03:59:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'weigh_reminder' => true,
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $subscription = $this->subscription($user, $token);
        CarbonImmutable::setTestNow('2026-10-17 04:01:00 UTC');
        $this->transport->results = [PushSendResult::expired()];

        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertDatabaseMissing('stride_push_subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseCount('stride_push_deliveries', 0);
    }

    public function test_scheduler_ignores_revoked_tokens_and_subscriptions_added_after_due_time(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 04:01:00 UTC');
        $user = $this->user();
        $token = $this->token($user);
        StrideProfile::create(['user_id' => $user->id, 'preferences' => [
            'weigh_reminder' => true,
            'notification_timezone' => 'Europe/Prague',
        ]]);
        $subscription = $this->subscription($user, $token);
        $subscription->forceFill(['created_at' => CarbonImmutable::now()->addMinute()])->save();

        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $subscription->forceFill(['created_at' => CarbonImmutable::now()->subDay()])->save();
        $token->forceFill(['revoked_at' => now()])->save();
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $token->forceFill(['revoked_at' => null, 'abilities' => ['read']])->save();
        $this->assertSame(0, app(PushScheduler::class)->sendDue());
        $this->assertCount(0, $this->transport->sent);
    }

    public function test_web_push_transport_uses_minishlink_with_a_mocked_http_response(): void
    {
        $keys = VAPID::createVapidKeys();
        config([
            'stride-push.public_key' => $keys['publicKey'],
            'stride-push.private_key' => $keys['privateKey'],
            'stride-push.subject' => 'mailto:test@example.test',
        ]);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($key);
        $p256dh = "\x04".$details['ec']['x'].$details['ec']['y'];
        $subscription = new PushSubscription([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            'public_key' => rtrim(strtr(base64_encode($p256dh), '+/', '-_'), '='),
            'auth_token' => $this->authKey(),
        ]);
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): ResponseInterface {
            $requests[] = [$method, $url, $options];

            return new MockResponse('', ['http_code' => 201]);
        });

        $result = (new WebPushTransport($client))->send($subscription, ['title' => 'Test']);

        $this->assertSame('sent', $result->status);
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0][0]);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/test', $requests[0][1]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->strideUser()->create();
    }

    private function auth(User $user): array
    {
        if (! isset($this->tokens[$user->id])) {
            $raw = fake()->uuid();
            $this->tokens[$user->id] = $raw;
            UserApiToken::create([
                'user_id' => $user->id,
                'name' => 'Test device',
                'token' => hash('sha256', $raw),
                'abilities' => ['stride'],
            ]);
        }

        return ['Authorization' => 'Bearer '.$this->tokens[$user->id]];
    }

    private function token(User $user): UserApiToken
    {
        return UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'Test device',
            'token' => hash('sha256', fake()->uuid()),
            'abilities' => ['stride'],
        ]);
    }

    private function subscription(User $user, UserApiToken $token): PushSubscription
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$user->id.'-device';

        return PushSubscription::create([
            'user_id' => $user->id,
            'token_id' => $token->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => $this->publicKey(),
            'auth_token' => $this->authKey(),
        ]);
    }

    private function publicKey(): string
    {
        return rtrim(strtr(base64_encode("\x04".str_repeat("\x00", 64)), '+/', '-_'), '=');
    }

    private function authKey(): string
    {
        return rtrim(strtr(base64_encode(str_repeat("\x01", 16)), '+/', '-_'), '=');
    }
}
