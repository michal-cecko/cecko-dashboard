<?php

namespace Tests\Feature\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Resources\Tickets\Pages\ListTickets as ClientListTickets;
use App\Filament\Client\Resources\Tickets\TicketResource as ClientTicketResource;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use App\Models\Tickets\TicketAlert;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages\ListTickets;
use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class TicketSoftDeleteTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private User $client;

    private User $staff;

    private Project $project;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        Storage::fake('private');
        config([
            'services.telegram.ticket_bot_token' => 'test-token',
            'services.telegram.ticket_chat_id' => '12345',
        ]);

        $customer = Customer::factory()->create();
        $this->client = $this->makeClient($customer);
        $this->staff = $this->makeStaff();
        $this->project = $this->makeProject($customer, SupportPlanEnum::PRO);
        $this->ticket = $this->makeTicket($this->project, $this->client);
        $this->staffReply($this->ticket, $this->staff, '<p>Pozrieme sa na to.</p>');
        Storage::disk('private')->put("ticket-attachments/{$this->ticket->id}/screen.png", 'png');
    }

    public function test_staff_soft_delete_a_ticket_and_keep_its_replies_and_files(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel('tikety');

        Livewire::test(ListTickets::class)
            ->selectTableRecords([$this->ticket])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertSoftDeleted($this->ticket);
        $this->assertSame(1, $this->ticket->replies()->count());
        Storage::disk('private')->assertExists("ticket-attachments/{$this->ticket->id}/screen.png");
        $this->assertTrue($this->ticket->activities()->where('description', 'Ticket was deleted')->where('user_id', $this->staff->id)->exists());
    }

    public function test_staff_restore_a_deleted_ticket(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel('tikety');
        $this->ticket->delete();

        Livewire::test(ListTickets::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$this->ticket])
            ->selectTableRecords([$this->ticket])
            ->callAction(TestAction::make(RestoreBulkAction::class)->table()->bulk());

        $this->assertNotSoftDeleted($this->ticket);
        $this->assertTrue($this->ticket->activities()->where('description', 'Ticket was restored')->exists());
    }

    public function test_an_agent_without_the_delete_grant_cannot_delete(): void
    {
        $agent = $this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]);
        $this->project->agents()->attach($agent->id, ['role' => 'agent']);
        $this->actingAs($agent);
        Filament::setCurrentPanel('tikety');

        Livewire::test(ListTickets::class)
            ->assertActionHidden(TestAction::make(DeleteBulkAction::class)->table()->bulk());
    }

    public function test_the_client_no_longer_sees_a_deleted_ticket(): void
    {
        $this->ticket->delete();
        $this->actingAs($this->client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(ClientListTickets::class)->assertCanNotSeeTableRecords([$this->ticket]);
        $this->get(ClientTicketResource::getUrl('view', ['record' => $this->ticket]))->assertNotFound();
    }

    public function test_a_force_delete_removes_the_files(): void
    {
        $this->ticket->forceDelete();

        Storage::disk('private')->assertMissing("ticket-attachments/{$this->ticket->id}/screen.png");
    }

    public function test_alerts_of_a_deleted_ticket_are_closed_without_a_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->assertSame(1, TicketAlert::query()->unsent()->count());

        $this->ticket->delete();
        Carbon::setTestNow(Carbon::parse('2026-11-03 16:00', 'Europe/Bratislava'));
        $this->artisan('tickets:send-telegram-digest')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, TicketAlert::query()->unsent()->count());
    }
}
