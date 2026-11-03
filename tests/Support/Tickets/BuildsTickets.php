<?php

namespace Tests\Support\Tickets;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Tickets\SupportPlanEnum;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use App\Services\Tickets\TicketingDefaults;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Events\InternalNoteAdded;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use daacreators\CreatorsTicketing\Models\TicketStatus;

/**
 * Builds tickets the way the plugin and the client panel do, with the same events.
 */
trait BuildsTickets
{
    protected function installTicketing(): void
    {
        TicketingDefaults::install();
    }

    protected function makeClient(?Customer $customer = null, array $attributes = []): User
    {
        return User::factory()->create([
            'customer_id' => ($customer ?? Customer::factory()->create())->getKey(),
            'capabilities' => [],
            ...$attributes,
        ]);
    }

    protected function makeStaff(array $capabilities = [UserCapabilityEnum::VIEW_TICKETS, UserCapabilityEnum::MANAGE_TICKETS]): User
    {
        return User::factory()->create(['capabilities' => $capabilities]);
    }

    protected function makeProject(Customer $customer, SupportPlanEnum $plan = SupportPlanEnum::NONE): Project
    {
        $project = Project::factory()->plan($plan)->create(['customer_id' => $customer->getKey()]);
        TicketingDefaults::attachForm($project);

        return $project;
    }

    /**
     * Creates the ticket as the given user, so the plugin observer and our listener see that user.
     */
    protected function makeTicket(Project $project, User $requester, array $customFields = [], TicketPriority $priority = TicketPriority::MEDIUM): Ticket
    {
        $previous = auth()->user();
        auth()->setUser($requester);

        $ticket = Ticket::query()->create([
            'user_id' => $requester->getKey(),
            'department_id' => $project->getKey(),
            'form_id' => TicketingDefaults::form()->getKey(),
            'priority' => $priority,
            'custom_fields' => [
                'title' => 'Tlačidlo nefunguje',
                'description' => 'Popis od klienta',
                ...$customFields,
            ],
        ]);

        $previous ? auth()->setUser($previous) : auth()->forgetUser();

        return $ticket->refresh();
    }

    protected function staffReply(Ticket $ticket, User $staff, string $content, bool $internal = false): TicketReply
    {
        $reply = $ticket->replies()->create([
            'user_id' => $staff->getKey(),
            'content' => $content,
            'is_internal_note' => $internal,
        ]);

        event($internal ? new InternalNoteAdded($ticket, $reply) : new TicketReplyAdded($ticket, $reply));

        return $reply;
    }

    protected function changeStatus(Ticket $ticket, User $actor, string $slug): void
    {
        $previous = auth()->user();
        auth()->setUser($actor);

        $ticket->update(['ticket_status_id' => TicketStatus::query()->where('slug', $slug)->value('id')]);

        $previous ? auth()->setUser($previous) : auth()->forgetUser();
    }
}
