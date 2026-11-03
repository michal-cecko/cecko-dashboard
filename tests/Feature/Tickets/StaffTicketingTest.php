<?php

namespace Tests\Feature\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Tickets\PlanBillingEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Filament\Tickets\Resources\ClientAccounts\ClientAccountResource;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\CreateClientAccount;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\EditClientAccount;
use App\Filament\Tickets\Resources\ClientAccounts\Pages\ListClientAccounts;
use App\Filament\Tickets\Resources\Projects\Pages\CreateProject;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use App\Notifications\Tickets\ClientInviteNotification;
use App\Services\Tickets\TicketingDefaults;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages\CreateTicket;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages\ViewTicket;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Support\AssigneeFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class StaffTicketingTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
    }

    public function test_staff_create_a_client_account_and_the_invite_points_to_the_client_panel(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        Livewire::test(CreateClientAccount::class)
            ->fillForm([
                'name' => 'Jana Klientová',
                'email' => 'jana@klient.sk',
                'customer_id' => $customer->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = User::query()->where('email', 'jana@klient.sk')->sole();
        $this->assertSame($customer->id, $client->customer_id);
        $this->assertFalse($client->ticket_notices_enabled);

        Notification::assertSentTo($client, ClientInviteNotification::class, function (ClientInviteNotification $notification): bool {
            return str_contains($notification->url, '/podpora/password-reset/reset')
                && str_contains($notification->url, 'signature=');
        });
    }

    public function test_the_client_account_list_shows_only_client_users(): void
    {
        $client = $this->makeClient();
        $staff = $this->makeStaff();

        $this->actingAs($staff);
        Filament::setCurrentPanel('tikety');

        Livewire::test(ListClientAccounts::class)
            ->assertCanSeeTableRecords([$client])
            ->assertCanNotSeeTableRecords([$staff]);
    }

    public function test_ticket_managers_without_manage_users_get_the_client_account_actions(): void
    {
        $client = $this->makeClient();

        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        Livewire::test(ListClientAccounts::class)
            ->assertActionVisible(CreateAction::class)
            ->assertActionVisible(TestAction::make(EditAction::class)->table($client));

        Livewire::test(EditClientAccount::class, ['record' => $client->getKey()])
            ->assertActionVisible(DeleteAction::class);
    }

    public function test_a_client_with_tickets_or_replies_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        $requester = $this->makeClient($customer);
        $colleague = $this->makeClient($customer);
        $ticket = $this->makeTicket($this->makeProject($customer), $requester);
        $ticket->replies()->create(['user_id' => $colleague->getKey(), 'content' => '<p>Doplním</p>']);

        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        foreach ([$requester, $colleague] as $client) {
            Livewire::test(EditClientAccount::class, ['record' => $client->getKey()])
                ->assertActionHidden(DeleteAction::class);
        }
    }

    public function test_staff_without_manage_tickets_cannot_open_client_accounts(): void
    {
        $this->actingAs($this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]));

        $this->get(ClientAccountResource::getUrl(panel: 'tikety'))->assertForbidden();
    }

    public function test_staff_without_manage_tickets_see_tickets_only_as_project_agents(): void
    {
        $viewer = $this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]);
        $project = $this->makeProject(Customer::factory()->create());
        $this->actingAs($viewer);

        $this->get('/tikety/klientske-ucty')->assertForbidden();
        $this->get('/tikety/projekty')->assertForbidden();
        $this->get('/tikety/tickets')->assertForbidden();

        $project->agents()->attach($viewer->id, ['role' => 'agent']);

        $this->get('/tikety/tickets')->assertOk();
    }

    public function test_a_new_project_gets_the_ticket_form(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'Friendly Fyzio',
                'slug' => 'friendly-fyzio',
                'code' => 'ff',
                'customer_id' => $customer->id,
                'plan' => SupportPlanEnum::STANDARD->value,
                'plan_billing' => PlanBillingEnum::YEARLY->value,
                'plan_started_on' => '2026-11-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'friendly-fyzio')->sole();
        $this->assertSame('FF', $project->code);
        $this->assertSame(SupportPlanEnum::STANDARD, $project->plan);
        $this->assertSame(PlanBillingEnum::YEARLY, $project->plan_billing);
        $this->assertTrue($project->forms()->whereKey(TicketingDefaults::form()->getKey())->exists());
    }

    public function test_a_project_code_is_required_unique_and_only_letters_and_digits(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->create(['customer_id' => $customer->id, 'code' => 'FF']);
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        foreach (['code' => ['', 'required'], 'taken' => ['FF', 'unique'], 'dash' => ['F-F', 'regex'], 'long' => ['ABCDEFG', 'max']] as [$code, $rule]) {
            Livewire::test(CreateProject::class)
                ->fillForm(['name' => 'Nový', 'slug' => 'novy', 'code' => $code, 'customer_id' => $customer->id])
                ->call('create')
                ->assertHasFormErrors(['code' => $rule]);
        }
    }

    public function test_staff_creating_a_ticket_go_back_to_the_ticket_list(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $client = $this->makeClient($customer);
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        Livewire::test(CreateTicket::class)
            ->fillForm(['department_id' => $project->id])
            ->fillForm([
                'user_id' => $client->id,
                'custom_fields.title' => 'Nový tiket od podpory',
                'custom_fields.description' => 'Popis',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(TicketResource::getUrl('index'));

        $this->assertSame('Nový tiket od podpory', Ticket::query()->sole()->custom_fields['title']);
    }

    public function test_assigning_a_ticket_in_the_plugin_uses_the_app_user_model(): void
    {
        $customer = Customer::factory()->create();
        $staff = $this->makeStaff();
        $colleague = User::factory()->create(['name' => 'Kolega Podpora', 'capabilities' => [UserCapabilityEnum::VIEW_TICKETS]]);
        $ticket = $this->makeTicket($this->makeProject($customer), $this->makeClient($customer));

        $this->actingAs($staff);
        Filament::setCurrentPanel('tikety');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->call('assignTicket', $colleague->id);

        $this->assertSame($colleague->id, $ticket->refresh()->assignee_id);
        $this->assertSame('Kolega Podpora', $ticket->activities()->where('description', 'Ticket was assigned')->value('new_value'));
    }

    public function test_clients_never_show_up_as_agents_or_assignees(): void
    {
        $client = $this->makeClient();
        $staff = $this->makeStaff();

        $ids = AssigneeFilter::apply(User::query())->pluck('id');

        $this->assertTrue($ids->contains($staff->id));
        $this->assertFalse($ids->contains($client->id));
    }

    public function test_the_plugin_keeps_the_apps_private_disk(): void
    {
        $this->assertTrue(config('filesystems.disks.private.serve'));
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.private.root'));
    }
}
