<?php

namespace Tests\Feature\Tickets;

use App\Models\Invoices\Customer;
use App\Notifications\Tickets\TicketUpdatedNotification;
use App\Services\Tickets\ClientTickets;
use App\Services\Tickets\TicketNotices;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages\ViewTicket;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Livewire\Livewire;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class ClientTicketNoticesTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        Notification::fake();
    }

    public function test_a_public_staff_reply_marks_the_ticket_unread_and_an_internal_note_does_not(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->assertFalse(ClientTickets::isUnread($ticket->refresh(), $client));

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Interná vec</p>', internal: true);
        $this->assertFalse(ClientTickets::isUnread($ticket->refresh(), $client));

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Pozreli sme sa na to</p>');
        $this->assertTrue(ClientTickets::isUnread($ticket->refresh(), $client));

        $this->travel(1)->minutes();
        ClientTickets::markRead($ticket, $client);
        $this->assertFalse(ClientTickets::isUnread($ticket->refresh(), $client));
    }

    public function test_notices_go_only_to_users_who_turned_them_on_and_carry_no_ticket_text(): void
    {
        $customer = Customer::factory()->create();
        $author = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $quiet = $this->makeClient($customer, ['ticket_notices_enabled' => false]);
        $outsider = $this->makeClient(attributes: ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $author, ['description' => 'Tajný popis pacienta']);

        $this->staffReply($ticket, $staff, '<p>Odpoveď s detailom diagnózy</p>');
        $this->sendNotices();

        Notification::assertSentTo($author, TicketUpdatedNotification::class, function (TicketUpdatedNotification $notification) use ($author, $ticket): bool {
            $mail = $notification->toMail($author);
            $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);

            return str_contains($text, $ticket->ticket_uid)
                && ! str_contains($text, 'Tajný popis')
                && ! str_contains($text, 'diagnózy')
                && ! str_contains($text, 'Tlačidlo nefunguje')
                && str_contains($mail->actionUrl, '/podpora/tikety/'.$ticket->id);
        });
        Notification::assertNotSentTo($quiet, TicketUpdatedNotification::class);
        Notification::assertNotSentTo($outsider, TicketUpdatedNotification::class);
    }

    public function test_an_internal_note_sends_no_notice(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->staffReply($ticket, $this->makeStaff(), '<p>Interná poznámka</p>', internal: true);
        $this->sendNotices();

        Notification::assertNothingSent();
    }

    public function test_one_notice_until_the_client_opens_the_ticket_again(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Prvá</p>');
        $this->changeStatus($ticket, $staff, 'riesi-sa');
        $this->sendNotices();
        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 1);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Ešte jedna</p>');
        $this->sendNotices();
        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 1);

        $this->travel(1)->minutes();
        ClientTickets::markRead($ticket, $client);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Druhá</p>');
        $this->sendNotices();
        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 2);
    }

    public function test_the_clients_own_reply_or_reopen_sends_them_no_notice(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->changeStatus($ticket, $client, 'vyrieseny');
        $this->staffReply($ticket, $client, '<p>Ešte otázka</p>');
        $this->sendNotices();

        Notification::assertNotSentTo($client, TicketUpdatedNotification::class);
    }

    public function test_changes_on_many_tickets_wait_for_the_hourly_run_and_go_out_as_one_email(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $project = $this->makeProject($customer);
        $tickets = collect(range(1, 10))->map(fn (): Ticket => $this->makeTicket($project, $client));

        $this->travel(1)->minutes();
        $tickets->each(fn (Ticket $ticket) => $this->changeStatus($ticket, $staff, 'riesi-sa'));

        Notification::assertNotSentTo($client, TicketUpdatedNotification::class);

        $this->sendNotices();
        $this->sendNotices();

        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 1);
        Notification::assertSentTo($client, TicketUpdatedNotification::class, function (TicketUpdatedNotification $notification) use ($client, $tickets): bool {
            $mail = $notification->toMail($client);

            return $mail->subject === 'Novinky v 10 tiketoch'
                && collect($notification->tickets)->pluck('uid')->sort()->values()->all() === $tickets->pluck('ticket_uid')->sort()->values()->all();
        });
        $this->assertTrue(Schedule::events() !== [] && collect(Schedule::events())->contains(
            fn ($event): bool => str_contains($event->command, 'tickets:send-client-notices') && $event->expression === '0 * * * *',
        ));
    }

    public function test_a_ticket_the_client_opened_before_the_run_is_left_out(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $project = $this->makeProject($customer);
        $opened = $this->makeTicket($project, $client);
        $waiting = $this->makeTicket($project, $client);

        $this->travel(1)->minutes();
        $this->staffReply($opened, $staff, '<p>Hotovo</p>');
        $this->staffReply($waiting, $staff, '<p>Pozeráme</p>');

        $this->travel(1)->minutes();
        ClientTickets::markRead($opened, $client);
        $this->sendNotices();

        Notification::assertSentTo($client, TicketUpdatedNotification::class, fn (TicketUpdatedNotification $notification): bool => array_column($notification->tickets, 'uid') === [$waiting->ticket_uid]);

        $this->travel(1)->minutes();
        $this->staffReply($opened, $staff, '<p>Ešte doplnenie</p>');
        $this->sendNotices();

        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 2);
    }

    public function test_a_change_after_the_client_opened_the_ticket_still_goes_out(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Pozeráme</p>');

        $this->travel(15)->minutes();
        ClientTickets::markRead($ticket, $client);

        $this->travel(20)->minutes();
        $this->staffReply($ticket, $staff, '<p>Hotovo</p>');
        $this->sendNotices();

        Notification::assertSentTo($client, TicketUpdatedNotification::class, fn (TicketUpdatedNotification $notification): bool => array_column($notification->tickets, 'uid') === [$ticket->ticket_uid]);
    }

    public function test_a_user_who_turns_notices_off_before_the_run_gets_nothing(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->staffReply($ticket, $this->makeStaff(), '<p>Odpoveď</p>');
        $client->update(['ticket_notices_enabled' => false]);
        $this->sendNotices();

        Notification::assertNotSentTo($client, TicketUpdatedNotification::class);
    }

    public function test_send_now_skips_the_wait_and_the_run_does_not_send_it_again(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $quiet = $this->makeClient($customer, ['ticket_notices_enabled' => false]);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Prvá</p>');
        $this->sendNotices();

        $this->travel(1)->minutes();
        $this->changeStatus($ticket, $staff, 'vyrieseny');
        $this->assertSame(1, TicketNotices::sendNow($ticket->refresh(), $staff));
        $this->sendNotices();

        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 2);
        Notification::assertSentTo($client, TicketUpdatedNotification::class, fn (TicketUpdatedNotification $notification): bool => $notification->toMail($client)->subject === "Novinka v tikete {$ticket->ticket_uid}");
        Notification::assertNotSentTo($quiet, TicketUpdatedNotification::class);
    }

    public function test_staff_send_the_email_now_from_the_ticket_page(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->actingAs($staff);
        Filament::setCurrentPanel('tikety');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction('notifyClientNow')
            ->assertNotified('E-mail odoslaný');

        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 1);
    }

    private function sendNotices(): void
    {
        $this->artisan('tickets:send-client-notices')->assertSuccessful();
    }
}
