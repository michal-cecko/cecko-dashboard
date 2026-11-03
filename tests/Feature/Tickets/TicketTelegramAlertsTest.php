<?php

namespace Tests\Feature\Tickets;

use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Invoices\Customer;
use App\Models\Tickets\TicketAlert;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class TicketTelegramAlertsTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        config([
            'services.telegram.ticket_bot_token' => 'test-token',
            'services.telegram.ticket_chat_id' => '12345',
        ]);
    }

    /**
     * 2026-11-02 is a Monday.
     *
     * @return array<string, array{SupportPlanEnum, string, bool}>
     */
    public static function planDays(): array
    {
        return [
            'none on Monday' => [SupportPlanEnum::NONE, '2026-11-02', true],
            'none on Tuesday' => [SupportPlanEnum::NONE, '2026-11-03', false],
            'start on Thursday' => [SupportPlanEnum::START, '2026-11-05', true],
            'start on Wednesday' => [SupportPlanEnum::START, '2026-11-04', false],
            'standard on Tuesday' => [SupportPlanEnum::STANDARD, '2026-11-03', true],
            'pro on Friday' => [SupportPlanEnum::PRO, '2026-11-06', true],
            'pro on Saturday' => [SupportPlanEnum::PRO, '2026-11-07', false],
        ];
    }

    #[DataProvider('planDays')]
    public function test_the_batch_sends_on_the_plans_days(SupportPlanEnum $plan, string $date, bool $sends): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $customer = Customer::factory()->create();
        $this->makeTicket($this->makeProject($customer, $plan), $this->makeClient($customer));

        Carbon::setTestNow(Carbon::parse($date.' 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertSuccessful();

        $sends ? Http::assertSentCount(1) : Http::assertNothingSent();
        $this->assertSame($sends ? 0 : 1, TicketAlert::query()->unsent()->count());
    }

    public function test_an_urgent_ticket_is_sent_at_once(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $customer = Customer::factory()->create();

        $this->makeTicket($this->makeProject($customer), $this->makeClient($customer), priority: TicketPriority::URGENT);

        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'URGENTNÉ'));
        $this->assertSame(0, TicketAlert::query()->unsent()->count());
    }

    public function test_a_failed_send_keeps_the_alerts_for_the_next_run(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        $customer = Customer::factory()->create();
        $this->makeTicket($this->makeProject($customer, SupportPlanEnum::PRO), $this->makeClient($customer));

        Carbon::setTestNow(Carbon::parse('2026-11-03 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertFailed();

        $this->assertSame(1, TicketAlert::query()->unsent()->count());
    }

    public function test_a_long_batch_is_split_and_a_failed_part_keeps_only_its_alerts(): void
    {
        Http::fakeSequence('api.telegram.org/*')
            ->push(['ok' => true])
            ->push(['ok' => false], 500);
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer, SupportPlanEnum::PRO);
        $project->update(['name' => str_repeat('Dlhý názov projektu ', 10)]);

        foreach (range(1, 25) as $i) {
            $this->makeTicket($project, $client);
        }

        Carbon::setTestNow(Carbon::parse('2026-11-03 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertFailed();

        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertLessThanOrEqual(4096, mb_strlen($request['text']));
        }
        $sent = TicketAlert::query()->whereNotNull('sent_at')->count();
        $this->assertGreaterThan(0, $sent);
        $this->assertSame(25 - $sent, TicketAlert::query()->unsent()->count());
    }

    public function test_the_message_has_no_ticket_text_and_staff_activity_makes_no_alert(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer, SupportPlanEnum::PRO), $client, ['title' => 'Tajný názov', 'description' => 'Tajný popis']);

        $this->staffReply($ticket, $staff, '<p>Interná poznámka</p>', internal: true);
        $this->staffReply($ticket, $staff, '<p>Odpoveď podpory</p>');
        $this->staffReply($ticket, $client, '<p>Tajná odpoveď klienta</p>');

        $this->assertSame(
            [TicketAlert::EVENT_CREATED, TicketAlert::EVENT_REPLIED],
            TicketAlert::query()->orderBy('id')->pluck('event')->all(),
        );

        Carbon::setTestNow(Carbon::parse('2026-11-03 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertSuccessful();

        Http::assertSent(function (Request $request) use ($ticket): bool {
            $text = $request['text'];

            return str_contains($text, $ticket->ticket_uid)
                && str_contains($text, 'nový tiket, odpovede klienta: 1')
                && str_contains($text, '/tikety/tickets/'.$ticket->id)
                && ! str_contains($text, 'Tajn')
                && ! str_contains($text, 'Interná')
                && ! str_contains($text, 'Odpoveď podpory');
        });
    }

    public function test_the_batch_is_scheduled_at_16_in_bratislava(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'tickets:send-telegram-digest'));

        $this->assertNotNull($event);
        $this->assertSame('0 16 * * *', $event->expression);
        $this->assertSame('Europe/Bratislava', $event->timezone);
    }
}
