<?php

namespace Tests\Feature\Tickets;

use App\Models\Invoices\Customer;
use App\Models\Tickets\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tickets\BuildsTickets;
use Tests\TestCase;

class TicketNumberTest extends TestCase
{
    use BuildsTickets;
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTicketing();
        $this->customer = Customer::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00', 'Europe/Bratislava'));
    }

    public function test_numbers_count_up_per_project_and_day(): void
    {
        $friendlyFyzio = $this->project('FF');
        $client = $this->makeClient($this->customer);

        $this->assertSame('FF-261103-01', $this->makeTicket($friendlyFyzio, $client)->ticket_uid);
        $this->assertSame('FF-261103-02', $this->makeTicket($friendlyFyzio, $client)->ticket_uid);
        $this->assertSame('WEB-261103-01', $this->makeTicket($this->project('WEB'), $client)->ticket_uid);

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00', 'Europe/Bratislava'));
        $this->assertSame('FF-261104-01', $this->makeTicket($friendlyFyzio, $client)->ticket_uid);
    }

    public function test_a_deleted_tickets_number_is_not_reused(): void
    {
        $project = $this->project('FF');
        $client = $this->makeClient($this->customer);

        $this->makeTicket($project, $client)->delete();

        $this->assertSame('FF-261103-02', $this->makeTicket($project, $client)->ticket_uid);
    }

    public function test_the_sequence_goes_past_99(): void
    {
        $project = $this->project('FF');
        $client = $this->makeClient($this->customer);
        $this->makeTicket($project, $client)->update(['ticket_uid' => 'FF-261103-99']);

        $this->assertSame('FF-261103-100', $this->makeTicket($project, $client)->ticket_uid);
        $this->assertSame('FF-261103-101', $this->makeTicket($project, $client)->ticket_uid);
    }

    public function test_the_date_is_the_bratislava_day_just_after_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 23:30', 'UTC'));

        $ticket = $this->makeTicket($this->project('FF'), $this->makeClient($this->customer));

        $this->assertSame('FF-261104-01', $ticket->ticket_uid);
    }

    public function test_a_project_without_a_code_uses_tkt(): void
    {
        $ticket = $this->makeTicket($this->project(null), $this->makeClient($this->customer));

        $this->assertSame('TKT-261103-01', $ticket->ticket_uid);
    }

    public function test_a_longer_code_starting_with_the_same_letters_has_its_own_sequence(): void
    {
        $client = $this->makeClient($this->customer);
        $this->makeTicket($this->project('FFX'), $client);

        $this->assertSame('FF-261103-01', $this->makeTicket($this->project('FF'), $client)->ticket_uid);
    }

    private function project(?string $code): Project
    {
        $project = $this->makeProject($this->customer);
        $project->update(['code' => $code]);

        return $project;
    }
}
