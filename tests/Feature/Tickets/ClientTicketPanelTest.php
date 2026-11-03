<?php

namespace Tests\Feature\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Client\Components\ClientTicketChat;
use App\Filament\Client\Pages\Projects;
use App\Filament\Client\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Client\Resources\Tickets\Pages\ListTickets;
use App\Filament\Client\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Client\Resources\Tickets\TicketResource;
use App\Models\Invoices\Customer;
use App\Services\Tickets\TicketAttachments;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class ClientTicketPanelTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
    }

    public function test_panel_access_follows_customer_and_capabilities(): void
    {
        $client = $this->makeClient();
        $viewer = $this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]);
        $nobody = $this->makeStaff([]);

        $this->assertTrue($client->canAccessPanel(Filament::getPanel('podpora')));
        $this->assertFalse($client->canAccessPanel(Filament::getPanel('tikety')));
        $this->assertTrue($viewer->canAccessPanel(Filament::getPanel('tikety')));
        $this->assertFalse($viewer->canAccessPanel(Filament::getPanel('podpora')));
        $this->assertFalse($nobody->canAccessPanel(Filament::getPanel('tikety')));

        $this->actingAs($client)->get('/tikety/tickets')->assertForbidden();
        $this->actingAs($viewer)->get('/podpora/tikety')->assertForbidden();
    }

    public function test_every_user_of_a_customer_sees_its_tickets_and_nothing_else(): void
    {
        $customer = Customer::factory()->create();
        $author = $this->makeClient($customer);
        $colleague = $this->makeClient($customer);
        $ownTicket = $this->makeTicket($this->makeProject($customer), $author);

        $otherCustomer = Customer::factory()->create();
        $foreignTicket = $this->makeTicket($this->makeProject($otherCustomer), $this->makeClient($otherCustomer));

        $this->actingAs($colleague);
        Filament::setCurrentPanel('podpora');

        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$ownTicket])
            ->assertCanNotSeeTableRecords([$foreignTicket]);

        $this->get(TicketResource::getUrl('view', ['record' => $ownTicket]))->assertOk();
        $this->get(TicketResource::getUrl('view', ['record' => $foreignTicket]))->assertNotFound();
    }

    public function test_search_finds_a_title_with_accents(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer);
        $invoiceTicket = $this->makeTicket($project, $client, ['title' => 'Chýba účet v exporte']);
        $otherTicket = $this->makeTicket($project, $client, ['title' => 'Tlačidlo nefunguje']);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(ListTickets::class)
            ->searchTable('účet')
            ->assertCanSeeTableRecords([$invoiceTicket])
            ->assertCanNotSeeTableRecords([$otherTicket]);
    }

    public function test_ticket_page_shows_public_replies_but_never_internal_notes(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);

        $this->staffReply($ticket, $staff, '<p>Verejná odpoveď pre klienta</p>');
        $this->staffReply($ticket, $staff, '<p>INTERNÁ POZNÁMKA odhad 6 hodín</p><img src="'.url("/private/ticket-attachments/{$ticket->id}/internal.png").'">', internal: true);

        $this->actingAs($client)
            ->get(TicketResource::getUrl('view', ['record' => $ticket], panel: 'podpora'))
            ->assertOk()
            ->assertSee('Verejná odpoveď pre klienta')
            ->assertSee('Popis od klienta')
            ->assertDontSee('INTERNÁ POZNÁMKA')
            ->assertDontSee('odhad 6 hodín')
            ->assertDontSee('internal.png');
    }

    public function test_client_creates_a_ticket_in_own_project_only(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer);
        $foreignProject = $this->makeProject(Customer::factory()->create());

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(CreateTicket::class)
            ->fillForm([
                'project_id' => $foreignProject->id,
                'title' => 'Cudzí projekt',
                'description' => 'Nemalo by prejsť',
            ])
            ->call('create')
            ->assertHasFormErrors(['project_id']);

        Livewire::test(CreateTicket::class)
            ->fillForm([
                'project_id' => $project->id,
                'title' => 'Export nefunguje',
                'description' => '<p>Pri exporte CSV príde chyba 500.</p>',
                'priority' => TicketPriority::URGENT->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertSame($project->id, $ticket->department_id);
        $this->assertSame($client->id, $ticket->user_id);
        $this->assertSame('Export nefunguje', $ticket->custom_fields['title']);
        $this->assertSame(TicketPriority::URGENT, $ticket->priority);
        $this->assertSame('novy', $ticket->status->slug);
        $this->assertFalse(TicketResource::canCreate() && $project->customer_id !== $client->customer_id);
    }

    public function test_create_another_keeps_the_project_and_create_goes_back_to_the_list(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $this->makeProject($customer);
        $project = $this->makeProject($customer);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(CreateTicket::class)
            ->assertSee('Vytvoriť & vytvoriť ďalší')
            ->fillForm([
                'project_id' => $project->id,
                'title' => 'Prvý tiket',
                'description' => 'Prvý popis',
            ])
            ->call('createAnother')
            ->assertHasNoFormErrors()
            ->assertNoRedirect()
            ->assertSchemaStateSet([
                'project_id' => $project->id,
                'title' => null,
            ])
            ->fillForm([
                'title' => 'Druhý tiket',
                'description' => 'Druhý popis',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(TicketResource::getUrl('index'));

        $this->assertSame(
            ['Prvý tiket', 'Druhý tiket'],
            Ticket::query()->orderBy('id')->get()->map(fn (Ticket $ticket): string => $ticket->custom_fields['title'])->all(),
        );
        $this->assertSame([$project->id], Ticket::query()->distinct()->pluck('department_id')->all());
    }

    public function test_the_ticket_form_refuses_a_file_path_the_client_typed(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(CreateTicket::class)
            ->fillForm([
                'project_id' => $project->id,
                'title' => 'Príloha',
                'description' => 'Cudzí súbor',
                'files' => ['ticket-attachments/temp/../../ticket-attachments/1/invoice.pdf'],
            ])
            ->call('create')
            ->assertHasFormErrors(['files']);

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_client_reply_on_a_resolved_ticket_reopens_it(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);
        $this->changeStatus($ticket, $staff, 'vyrieseny');

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->fillForm(['content' => '<p>Stále to nejde.</p><script>alert(1)</script><img src="https://evil.test/x.png">'], 'replyForm')
            ->call('sendReply')
            ->assertHasNoFormErrors([], 'replyForm');

        $ticket->refresh();
        $reply = $ticket->replies()->sole();

        $this->assertSame('novy', $ticket->status->slug);
        $this->assertFalse($reply->is_internal_note);
        $this->assertSame($client->id, $reply->user_id);
        $this->assertStringNotContainsString('<script>', $reply->content);
        $this->assertStringNotContainsString('evil.test', $reply->content);
        $this->assertStringContainsString('Stále to nejde.', $reply->content);
    }

    public function test_projects_page_lists_only_the_customers_active_projects(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer);
        $inactive = $this->makeProject($customer);
        $inactive->update(['is_active' => false]);
        $foreign = $this->makeProject(Customer::factory()->create());

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(Projects::class)
            ->assertCanSeeTableRecords([$project])
            ->assertCanNotSeeTableRecords([$inactive, $foreign]);
    }

    public function test_the_chat_shows_public_replies_with_signed_links_and_no_internal_notes(): void
    {
        Storage::fake('private');
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $staff = $this->makeStaff();
        $ticket = $this->makeTicket($this->makeProject($customer), $client);
        $this->staffReply($ticket, $staff, '<p>Verejná odpoveď</p><a href="'.TicketAttachments::staffUrl($ticket, 'report.pdf').'">report</a>');
        $this->staffReply($ticket, $staff, '<p>INTERNÁ POZNÁMKA</p>', internal: true);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(ClientTicketChat::class, ['ticket' => $ticket])
            ->assertSee('Verejná odpoveď')
            ->assertSee('/podpora/tikety/'.$ticket->id.'/prilohy/report.pdf', false)
            ->assertDontSee('/private/ticket-attachments/', false)
            ->assertDontSee('INTERNÁ POZNÁMKA')
            ->call('loadReplies')
            ->assertDontSee('INTERNÁ POZNÁMKA')
            ->assertSet('canViewInternalNotes', false);

        $this->assertFalse($ticket->refresh()->is_seen);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function chatHooks(): array
    {
        return [
            'reply html' => ['presentContent'],
            'reply side' => ['isRequesterSide'],
            'seen mark' => ['shouldMarkSeen'],
        ];
    }

    #[DataProvider('chatHooks')]
    public function test_the_browser_cannot_call_the_chat_view_hooks(string $hook): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $ticket = $this->makeTicket($this->makeProject($customer), $client);
        $otherCustomer = Customer::factory()->create();
        $otherTicket = $this->makeTicket($this->makeProject($otherCustomer), $this->makeClient($otherCustomer));
        $note = $this->staffReply($otherTicket, $this->makeStaff(), '<p>INTERNÁ POZNÁMKA</p>', internal: true);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        $this->expectException(MethodNotFoundException::class);

        Livewire::test(ClientTicketChat::class, ['ticket' => $ticket])->call($hook, $note->getKey());
    }

    public function test_the_chat_refuses_a_ticket_of_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $ticket = $this->makeTicket($this->makeProject($customer), $this->makeClient($customer));

        $this->actingAs($this->makeClient());
        Filament::setCurrentPanel('podpora');

        Livewire::test(ClientTicketChat::class, ['ticket' => $ticket])->assertForbidden();
    }

    public function test_an_urgent_ticket_shows_the_fee_of_the_projects_plan(): void
    {
        $customer = Customer::factory()->create();
        $client = $this->makeClient($customer);
        $project = $this->makeProject($customer, SupportPlanEnum::START);

        $this->actingAs($client);
        Filament::setCurrentPanel('podpora');

        Livewire::test(CreateTicket::class)
            ->fillForm(['project_id' => $project->id, 'priority' => TicketPriority::MEDIUM->value])
            ->assertDontSee('kritickú poruchu')
            ->fillForm(['priority' => TicketPriority::URGENT->value])
            ->assertSee('Balík Štart: kritickú poruchu v pracovnej dobe (po–pi 9:00–17:00) začnem nasledujúci pracovný deň. Mimo nej (6:00–23:00) 100 € / hod.', false);
    }

    public function test_status_list_has_the_installed_defaults(): void
    {
        $this->assertSame(
            ['novy', 'riesi-sa', 'caka-na-klienta', 'vyrieseny'],
            TicketStatus::query()->orderBy('order_column')->pluck('slug')->all(),
        );
    }
}
