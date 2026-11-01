<?php

namespace Tests\Feature\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Jobs\Support\SendPendingNotificationGroup;
use App\Models\Support\PendingNotification;
use App\Services\Support\SupportNotificationService;
use App\Services\Support\SupportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class SupportNotificationTest extends SupportTestCase
{
    public function test_each_plan_uses_its_1700_slots_and_holiday_shift(): void
    {
        $after = CarbonImmutable::parse('2026-11-02 17:01', 'Europe/Bratislava')->utc();
        $schedule = app(SupportSchedule::class);
        $this->assertSame('2026-11-09 17:00', $schedule->nextSlot(SupportPlanEnum::None, $after)->setTimezone('Europe/Bratislava')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 17:00', $schedule->nextSlot(SupportPlanEnum::Start, $after)->setTimezone('Europe/Bratislava')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-03 17:00', $schedule->nextSlot(SupportPlanEnum::Standard, $after)->setTimezone('Europe/Bratislava')->format('Y-m-d H:i'));
        config(['support.holidays' => ['2026-11-09']]);
        $this->assertSame('2026-11-10', $schedule->nextSlot(SupportPlanEnum::None, $after)->setTimezone('Europe/Bratislava')->toDateString());
    }

    public function test_empty_flush_dispatches_nothing_and_stale_claim_is_recovered(): void
    {
        app(SupportNotificationService::class)->flush();
        Queue::assertNothingPushed();
        $row = PendingNotification::factory()->create(['project_id' => $this->project->id, 'claimed_by' => 'old', 'claimed_at' => now()->subHour()]);
        app(SupportNotificationService::class)->flush();
        Queue::assertPushed(SendPendingNotificationGroup::class);
        $this->assertNotSame('old', $row->fresh()->claimed_by);
    }

    public function test_delivery_marks_sent_only_after_success_and_old_token_cannot_send(): void
    {
        $row = PendingNotification::factory()->create(['project_id' => $this->project->id, 'claimed_by' => 'new-token', 'claimed_at' => now()]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        (new SendPendingNotificationGroup([$row->id], $row->batch_key, 'old-token'))->handle();
        Http::assertNothingSent();
        (new SendPendingNotificationGroup([$row->id], $row->batch_key, 'new-token'))->handle();
        $this->assertNotNull($row->fresh()->sent_at);
        (new SendPendingNotificationGroup([$row->id], $row->batch_key, 'new-token'))->handle();
        Http::assertSentCount(1);
    }

    public function test_failed_provider_send_does_not_mark_sent(): void
    {
        $row = PendingNotification::factory()->create(['project_id' => $this->project->id, 'claimed_by' => 'token', 'claimed_at' => now()]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        try {
            (new SendPendingNotificationGroup([$row->id], $row->batch_key, 'token'))->handle();
        } catch (\RuntimeException $exception) {
            $this->assertSame('Telegram delivery failed.', $exception->getMessage());
        }
        $this->assertNull($row->fresh()->sent_at);
    }

    public function test_long_telegram_batch_is_split_without_losing_rows(): void
    {
        $rows = PendingNotification::factory()->count(5)->create(['project_id' => $this->project->id, 'url' => 'https://example.test/'.str_repeat('a', 1900), 'claimed_by' => 'token', 'claimed_at' => now()]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        (new SendPendingNotificationGroup($rows->modelKeys(), 'telegram:test-chat', 'token'))->handle();
        $this->assertSame(5, PendingNotification::query()->whereNotNull('sent_at')->count());
        Http::assertSent(fn ($request): bool => mb_strlen($request['text']) <= 4096);
        $this->assertGreaterThan(1, count(Http::recorded()));
    }
}
