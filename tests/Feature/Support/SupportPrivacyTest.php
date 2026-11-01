<?php

namespace Tests\Feature\Support;

use App\Enums\Support\SupportPlanEnum;
use App\Http\Resources\Support\SupportRequestResource;
use App\Jobs\Support\SendPendingNotificationGroup;
use App\Livewire\Support\Portal;
use App\Mail\Support\SupportMessage;
use App\Models\Support\PendingNotification;
use App\Models\Support\SupportInternalNote;
use App\Models\Support\SupportProject;
use App\Models\Support\SupportRequest;
use App\Services\Invoices\InvoicePdfService;
use App\Services\Support\SupportInvoiceService;
use App\Services\Support\SupportWorkflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

class SupportPrivacyTest extends SupportTestCase
{
    private const NOTE = 'INTERNAL-ONLY-SECRET-7d6b';

    private const ESTIMATE = 987654;

    public function test_pages_livewire_api_and_serialization_hide_notes_and_estimate(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'internal_estimate_minutes' => self::ESTIMATE]);
        SupportInternalNote::factory()->create(['request_id' => $record->id, 'body' => self::NOTE]);
        $this->loginClient();
        $urls = [route('support.home', $this->project->slug), route('support.request', ['project' => $this->project->slug, 'reference' => $record->reference]), route('support.api.requests', $this->project->slug), route('support.api.request', ['project' => $this->project->slug, 'reference' => $record->reference])];
        foreach ($urls as $url) {
            $response = str_contains($url, '/api/') ? $this->getJson($url) : $this->get($url);
            $response->assertOk()->assertDontSee(self::NOTE)->assertDontSee((string) self::ESTIMATE)->assertDontSee('internal_estimate_minutes');
        }
        $component = Livewire::test(Portal::class, ['project' => $this->project->slug, 'section' => 'detail', 'reference' => $record->reference]);
        $component->assertDontSee(self::NOTE)->set('reply', 'Public reply')->call('sendReply')->assertHasNoErrors()->assertDontSee((string) self::ESTIMATE);
        $serialized = json_encode($record->load('internalNotes')->toArray());
        $this->assertStringNotContainsString(self::NOTE, $serialized);
        $this->assertStringNotContainsString((string) self::ESTIMATE, $serialized);
        $resource = json_encode((new SupportRequestResource($record))->resolve());
        $this->assertStringNotContainsString(self::NOTE, $resource);
    }

    public function test_another_project_and_unassigned_contact_cannot_read_api(): void
    {
        $other = SupportProject::factory()->create();
        $record = SupportRequest::factory()->create(['project_id' => $other->id, 'description' => self::NOTE]);
        $this->loginClient();
        $this->getJson(route('support.api.request', ['project' => $other->slug, 'reference' => $record->reference]))->assertForbidden()->assertDontSee(self::NOTE);
        $this->getJson(route('support.api.request', ['project' => $this->project->slug, 'reference' => $record->reference]))->assertNotFound()->assertDontSee(self::NOTE);
    }

    public function test_rendered_mail_and_telegram_contain_only_public_information(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'internal_estimate_minutes' => self::ESTIMATE]);
        SupportInternalNote::factory()->create(['request_id' => $record->id, 'body' => self::NOTE]);
        app(SupportWorkflow::class)->event($record, 'public_reply', 'Public progress');
        PendingNotification::query()->update(['send_after' => now(), 'claimed_by' => 'test-token', 'claimed_at' => now()]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        foreach (PendingNotification::all()->groupBy('batch_key') as $key => $rows) {
            (new SendPendingNotificationGroup($rows->modelKeys(), $key, 'test-token'))->handle();
        }
        $html = Mail::sent(SupportMessage::class)->first()->render();
        $this->assertStringContainsString('Public progress', $html);
        $this->assertStringNotContainsString(self::NOTE, $html);
        $this->assertStringNotContainsString((string) self::ESTIMATE, $html);
        Http::assertSent(fn ($request): bool => ! str_contains(json_encode($request->data()), self::NOTE) && ! str_contains(json_encode($request->data()), (string) self::ESTIMATE));
    }

    public function test_client_contact_without_approval_rights_cannot_accept_work(): void
    {
        $record = SupportRequest::factory()->create(['project_id' => $this->project->id, 'status' => 'ready_for_acceptance']);
        $this->project->contacts()->updateExistingPivot($this->contact, ['can_approve' => false]);
        $this->loginClient();
        Livewire::test(Portal::class, ['project' => $this->project->slug, 'section' => 'detail', 'reference' => $record->reference])->call('decide', 'accept')->assertForbidden();
        $this->assertNull($record->fresh()->accepted_at);
    }

    public function test_invoice_pdf_html_never_contains_private_request_time_or_notes(): void
    {
        $this->start(SupportPlanEnum::Pro);
        $request = SupportRequest::factory()->create(['project_id' => $this->project->id, 'internal_estimate_minutes' => self::ESTIMATE]);
        $request->internalNotes()->create(['body' => self::NOTE]);
        $this->actingAs($this->staff);
        $order = app(SupportWorkflow::class)->propose($request, 'quote', 'Agreed public feature', ['amount' => 40000]);
        $confirmation = app(SupportWorkflow::class)->confirmation($this->project, $this->contact, 'order', $request, $order);
        app(SupportWorkflow::class)->consume($confirmation, '127.0.0.1', 'Test');
        $invoice = app(SupportInvoiceService::class)->invoice($order->fresh());
        $html = app(InvoicePdfService::class)->generateHtml($invoice, 'sk');
        $this->assertStringContainsString('Agreed public feature', $html);
        $this->assertStringNotContainsString(self::NOTE, $html);
        $this->assertStringNotContainsString((string) self::ESTIMATE, $html);
    }
}
