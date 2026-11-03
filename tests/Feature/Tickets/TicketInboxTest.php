<?php

namespace Tests\Feature\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use App\Services\Tickets\ClientTicketActions;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class TicketInboxTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private Customer $customer;

    private User $client;

    private User $colleague;

    private User $manager;

    private User $agent;

    private User $outsider;

    private User $otherClient;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();

        $this->customer = Customer::factory()->create();
        $this->client = $this->makeClient($this->customer);
        $this->colleague = $this->makeClient($this->customer);
        $this->otherClient = $this->makeClient();
        $this->manager = $this->makeStaff();
        $this->agent = $this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]);
        $this->outsider = $this->makeStaff([UserCapabilityEnum::VIEW_TICKETS]);
        $this->project = $this->makeProject($this->customer);
        $this->project->agents()->attach($this->agent->id, ['role' => 'agent']);
    }

    public function test_a_new_client_ticket_goes_to_managers_and_project_agents(): void
    {
        $ticket = $this->makeTicket($this->project, $this->client, ['title' => 'Tajný názov', 'description' => 'Tajný popis']);

        $this->assertNoticed($this->manager, 'Nový tiket od klienta', $ticket);
        $this->assertNoticed($this->agent, 'Nový tiket od klienta', $ticket);
        $this->assertNotNoticed($this->outsider, $this->client, $this->colleague, $this->otherClient);
        $this->assertStringContainsString('/tikety/', $this->notice($this->manager)['actions'][0]['url']);
    }

    public function test_a_client_reply_goes_to_staff_but_not_to_the_author(): void
    {
        $ticket = $this->makeTicket($this->project, $this->client);
        DatabaseNotification::query()->delete();

        ClientTicketActions::reply($this->colleague, $ticket, '<p>Tajná odpoveď klienta</p>');

        $this->assertNoticed($this->manager, 'Nová odpoveď klienta', $ticket);
        $this->assertNotNoticed($this->colleague, $this->client, $this->outsider);
    }

    public function test_a_staff_reply_goes_to_the_customers_users_without_its_text(): void
    {
        $ticket = $this->makeTicket($this->project, $this->client);
        DatabaseNotification::query()->delete();

        $this->staffReply($ticket, $this->manager, '<p>Tajná odpoveď podpory</p>');

        $this->assertNoticed($this->client, 'Nová odpoveď na tikete', $ticket);
        $this->assertNoticed($this->colleague, 'Nová odpoveď na tikete', $ticket);
        $this->assertNotNoticed($this->otherClient, $this->manager);
        $this->assertStringContainsString('/podpora/', $this->notice($this->client)['actions'][0]['url']);
    }

    public function test_an_internal_note_sends_no_in_app_notice(): void
    {
        $ticket = $this->makeTicket($this->project, $this->client);
        DatabaseNotification::query()->delete();

        $this->staffReply($ticket, $this->manager, '<p>Interný odhad 6 hodín</p>', internal: true);

        $this->assertSame(0, DatabaseNotification::query()->count());
    }

    public function test_a_status_change_by_staff_goes_to_the_client(): void
    {
        $ticket = $this->makeTicket($this->project, $this->client);
        DatabaseNotification::query()->delete();

        $this->changeStatus($ticket, $this->manager, 'riesi-sa');

        $this->assertNoticed($this->client, 'Zmenil sa stav tiketu', $ticket);
        $this->assertNotNoticed($this->manager);
    }

    /**
     * @return array<string, mixed>
     */
    private function notice(User $user): array
    {
        return DatabaseNotification::query()->where('notifiable_id', $user->id)->sole()->data;
    }

    private function assertNoticed(User $user, string $title, Ticket $ticket): void
    {
        $data = $this->notice($user);

        $this->assertSame($title, $data['title']);
        $this->assertSame($ticket->ticket_uid.' · '.$this->project->name, $data['body']);
        $this->assertStringNotContainsString('Tajn', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function assertNotNoticed(User ...$users): void
    {
        foreach ($users as $user) {
            $this->assertSame(0, DatabaseNotification::query()->where('notifiable_id', $user->id)->count(), "User {$user->id} got a notice.");
        }
    }
}
