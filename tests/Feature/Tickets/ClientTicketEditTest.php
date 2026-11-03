<?php

namespace Tests\Feature\Tickets;

use App\Filament\Client\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Services\Tickets\ClientTicketActions;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class ClientTicketEditTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private User $client;

    private User $staff;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();

        $customer = Customer::factory()->create();
        $this->client = $this->makeClient($customer);
        $this->staff = $this->makeStaff();
        $this->ticket = $this->makeTicket($this->makeProject($customer), $this->client, [
            'title' => 'Pôvodný názov',
            'description' => 'Pôvodný popis',
        ]);
        DatabaseNotification::query()->delete();

        $this->actingAs($this->client);
        Filament::setCurrentPanel('podpora');
    }

    public function test_a_client_edits_an_open_ticket_and_staff_see_the_change_in_the_log(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['title' => 'Nový názov', 'description' => '<p>Nový popis</p><script>alert(1)</script>'])
            ->assertHasNoActionErrors();

        $this->ticket->refresh();
        $this->assertSame('Nový názov', $this->ticket->custom_fields['title']);
        $this->assertSame('<p>Nový popis</p>', $this->ticket->custom_fields['description']);

        $changes = $this->ticket->activities()->where('description', 'Field was changed')->get();
        $this->assertCount(2, $changes);
        $this->assertTrue($changes->contains('old_value', 'Názov: Pôvodný názov'));
        $this->assertTrue($changes->every('user_id', $this->client->id));
    }

    public function test_the_client_page_never_shows_the_change_log(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['title' => 'Nový názov', 'description' => '<p>Nový popis</p>']);

        $this->get(TicketResource::getUrl('view', ['record' => $this->ticket]))
            ->assertOk()
            ->assertSee('Nový názov')
            ->assertDontSee('Pôvodný názov')
            ->assertDontSee('Pôvodný popis')
            ->assertDontSee('Pole bolo zmenené')
            ->assertDontSee('Field was changed');
    }

    public function test_staff_get_an_in_app_notice_without_ticket_text(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['title' => 'Nový názov', 'description' => '<p>Nový popis</p>']);

        $notice = DatabaseNotification::query()->where('notifiable_id', $this->staff->id)->sole();
        $data = json_encode($notice->data, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Klient upravil tiket', $data);
        $this->assertStringContainsString($this->ticket->ticket_uid, $data);
        $this->assertStringNotContainsString('názov', $data);
        $this->assertStringNotContainsString('popis', $data);
        $this->assertSame(0, DatabaseNotification::query()->where('notifiable_id', $this->client->id)->count());
    }

    public function test_saving_without_a_change_logs_nothing(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['title' => 'Pôvodný názov', 'description' => 'Pôvodný popis']);

        $this->assertSame(0, $this->ticket->activities()->where('description', 'Field was changed')->count());
        $this->assertSame(0, DatabaseNotification::query()->count());
    }

    public function test_a_resolved_ticket_cannot_be_edited(): void
    {
        $this->changeStatus($this->ticket, $this->staff, 'vyrieseny');

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->assertActionHidden('edit');
    }

    public function test_a_client_cannot_edit_or_open_a_ticket_of_another_customer(): void
    {
        $this->actingAs($this->makeClient());

        $this->get(TicketResource::getUrl('view', ['record' => $this->ticket]))->assertNotFound();

        $this->expectException(HttpException::class);
        ClientTicketActions::edit(auth()->user(), $this->ticket, 'Cudzí', 'Cudzí', TicketPriority::LOW);
    }

    public function test_the_service_refuses_to_edit_a_resolved_ticket(): void
    {
        $this->changeStatus($this->ticket, $this->staff, 'vyrieseny');

        try {
            ClientTicketActions::edit($this->client, $this->ticket->refresh(), 'Nový', 'Nový', TicketPriority::MEDIUM);
            $this->fail('A resolved ticket was edited.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('Pôvodný názov', $this->ticket->refresh()->custom_fields['title']);
    }

    public function test_a_client_has_no_delete_action(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_a_client_changes_the_priority_and_only_staff_see_the_log(): void
    {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['priority' => TicketPriority::HIGH->value])
            ->assertHasNoActionErrors();

        $this->assertSame(TicketPriority::HIGH, $this->ticket->refresh()->priority);
        $this->assertSame(1, $this->ticket->activities()->where('description', 'Priority was changed')->count());
        $this->assertSame(0, $this->ticket->activities()->where('description', 'Field was changed')->count());
        $this->assertSame(1, DatabaseNotification::query()->where('notifiable_id', $this->staff->id)->count());

        $this->get(TicketResource::getUrl('view', ['record' => $this->ticket]))
            ->assertOk()
            ->assertDontSee('Priority was changed')
            ->assertDontSee('Priorita bola zmenená');
    }

    public function test_raising_the_priority_to_urgent_alerts_staff_on_telegram_at_once(): void
    {
        config([
            'services.telegram.ticket_bot_token' => 'test-token',
            'services.telegram.ticket_chat_id' => '12345',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['priority' => TicketPriority::URGENT->value]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'klient zvýšil prioritu')
            && str_contains($request['text'], 'URGENTNÉ')
            && ! str_contains($request['text'], 'Pôvodný'));
    }

    public function test_saving_an_urgent_ticket_again_sends_no_new_alert(): void
    {
        $this->ticket->update(['priority' => TicketPriority::URGENT]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->getKey()])
            ->callAction('edit', ['title' => 'Nový názov']);

        Http::assertNothingSent();
    }
}
