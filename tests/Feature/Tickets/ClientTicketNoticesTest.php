<?php

namespace Tests\Feature\Tickets;

use App\Models\Invoices\Customer;
use App\Notifications\Tickets\TicketUpdatedNotification;
use App\Services\Tickets\ClientTickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 1);

        $this->travel(1)->minutes();
        ClientTickets::markRead($ticket, $client);

        $this->travel(1)->minutes();
        $this->staffReply($ticket, $staff, '<p>Druhá</p>');
        Notification::assertSentToTimes($client, TicketUpdatedNotification::class, 2);
    }

    public function test_the_clients_own_reply_or_reopen_sends_them_no_notice(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer, ['ticket_notices_enabled' => true]);
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->changeStatus($ticket, $client, 'vyrieseny');
        $this->staffReply($ticket, $client, '<p>Ešte otázka</p>');

        Notification::assertNotSentTo($client, TicketUpdatedNotification::class);
    }
}
