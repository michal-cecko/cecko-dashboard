<?php

namespace Tests\Feature\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Invoices\Resources\Customers\Pages\EditCustomer;
use App\Filament\Tickets\Actions\SendClientInviteAction;
use App\Filament\Tickets\Resources\ClientAccounts\RelationManagers\ClientAccountsRelationManager;
use App\Filament\Tickets\Resources\Projects\Pages\EditProject;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Notifications\Tickets\ClientInviteNotification;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class ClientAccountsRelationManagerTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        Notification::fake();
    }

    public function test_the_project_page_lists_only_the_clients_of_its_customer(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $client = $this->makeClient($customer);
        $otherClient = $this->makeClient();
        $staff = $this->makeStaff();
        $this->actingAs($staff);
        Filament::setCurrentPanel('tikety');

        $this->relationManager(EditProject::class, $project)
            ->assertCanSeeTableRecords([$client])
            ->assertCanNotSeeTableRecords([$otherClient, $staff]);
    }

    public function test_staff_add_a_client_on_the_project_page_and_the_client_gets_an_invite(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        $this->relationManager(EditProject::class, $project)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'name' => 'Jana Klientová',
                'email' => 'jana@klient.sk',
                'ticket_notices_enabled' => true,
            ])
            ->assertHasNoFormErrors();

        $client = User::query()->where('email', 'jana@klient.sk')->sole();
        $this->assertSame($customer->id, $client->customer_id);
        $this->assertSame([], $client->capabilities);
        $this->assertTrue($client->ticket_notices_enabled);
        Notification::assertSentTo($client, ClientInviteNotification::class);
    }

    public function test_staff_add_a_client_on_the_customer_page_in_the_invoices_panel(): void
    {
        $staff = $this->makeStaff([UserCapabilityEnum::VIEW_INVOICES, UserCapabilityEnum::MANAGE_TICKETS]);
        $customer = $this->customerInActiveCompany($staff);
        $this->actingAs($staff);
        Filament::setCurrentPanel('invoices');

        $this->relationManager(EditCustomer::class, $customer)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'name' => 'Peter Klient',
                'email' => 'peter@klient.sk',
            ])
            ->assertHasNoFormErrors();

        $client = User::query()->where('email', 'peter@klient.sk')->sole();
        $this->assertSame($customer->id, $client->customer_id);
        Notification::assertSentTo($client, ClientInviteNotification::class);
    }

    public function test_the_client_accounts_stay_hidden_without_manage_tickets(): void
    {
        $staff = $this->makeStaff([UserCapabilityEnum::VIEW_INVOICES, UserCapabilityEnum::VIEW_TICKETS]);
        $customer = $this->customerInActiveCompany($staff);
        $project = $this->makeProject($customer);
        $this->actingAs($staff);

        $this->assertFalse(ClientAccountsRelationManager::canViewForRecord($customer, EditCustomer::class));
        $this->assertFalse(ClientAccountsRelationManager::canViewForRecord($project, EditProject::class));

        $staff->update(['capabilities' => [UserCapabilityEnum::MANAGE_TICKETS]]);

        $this->assertTrue(ClientAccountsRelationManager::canViewForRecord($customer, EditCustomer::class));
    }

    public function test_the_client_accounts_stay_hidden_on_a_project_without_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $this->actingAs($this->makeStaff());

        $this->assertTrue(ClientAccountsRelationManager::canViewForRecord($project, EditProject::class));

        $customer->delete();

        $this->assertFalse(ClientAccountsRelationManager::canViewForRecord($project->fresh(), EditProject::class));
    }

    public function test_staff_resend_an_invite_from_the_project_page(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $client = $this->makeClient($customer);
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        $this->relationManager(EditProject::class, $project)
            ->callAction(TestAction::make(SendClientInviteAction::class)->table($client));

        Notification::assertSentTo($client, ClientInviteNotification::class);
    }

    public function test_a_client_with_tickets_cannot_be_deleted_from_the_project_page(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->makeProject($customer);
        $requester = $this->makeClient($customer);
        $newcomer = $this->makeClient($customer);
        $this->makeTicket($project, $requester);
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        $this->relationManager(EditProject::class, $project)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($requester))
            ->callAction(TestAction::make(DeleteAction::class)->table($newcomer));

        $this->assertModelExists($requester);
        $this->assertModelMissing($newcomer);
    }

    public function test_a_new_client_keeps_the_random_password_until_the_invite_is_used(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->makeStaff());
        Filament::setCurrentPanel('tikety');

        $this->relationManager(EditProject::class, $this->makeProject($customer))
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'name' => 'Jana',
                'email' => 'jana@klient.sk',
                'password' => 'password',
            ]);

        $this->assertFalse(Hash::check('password', User::query()->where('email', 'jana@klient.sk')->sole()->password));
    }

    private function relationManager(string $pageClass, Model $ownerRecord): Testable
    {
        return Livewire::test(ClientAccountsRelationManager::class, [
            'ownerRecord' => $ownerRecord,
            'pageClass' => $pageClass,
        ]);
    }

    private function customerInActiveCompany(User $staff): Customer
    {
        $company = Company::factory()->create(['user_id' => $staff->id]);
        $staff->update(['active_company_id' => $company->id]);

        return Customer::factory()->create(['company_id' => $company->id]);
    }
}
