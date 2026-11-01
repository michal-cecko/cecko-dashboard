<?php

namespace Tests\Feature\Support;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Support\SupportPlanEnum;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Models\Support\SupportContact;
use App\Models\Support\SupportProject;
use App\Services\Support\SupportBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class SupportTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected SupportContact $contact;

    protected SupportProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        config(['support.telegram.chat_id' => 'test-chat', 'support.telegram.bot_token' => 'test-token']);
        $company = Company::factory()->create(['is_vat_payer' => false]);
        $this->staff = $company->user;
        $this->staff->update(['capabilities' => [UserCapabilityEnum::MANAGE_SUPPORT, UserCapabilityEnum::VIEW_INVOICES, UserCapabilityEnum::MANAGE_INVOICES], 'active_company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        InvoiceNumberSequence::factory()->create(['company_id' => $company->id]);
        $this->project = SupportProject::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $this->contact = SupportContact::factory()->create();
        $this->project->contacts()->attach($this->contact, ['can_approve' => true]);
    }

    protected function loginClient(): void
    {
        Auth::guard('support')->login($this->contact);
        session()->put('support.email_hash', hash('sha256', $this->contact->email));
    }

    protected function start(SupportPlanEnum $plan = SupportPlanEnum::Start, string $interval = 'monthly'): void
    {
        app(SupportBillingService::class)->start($this->project, $plan, $interval);
        $this->project->unsetRelation('subscription');
    }
}
