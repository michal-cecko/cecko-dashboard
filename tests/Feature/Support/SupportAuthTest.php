<?php

namespace Tests\Feature\Support;

use App\Mail\Support\SupportMessage;
use App\Models\Support\SupportLoginLink;
use App\Services\Support\SupportLoginService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SupportAuthTest extends SupportTestCase
{
    public function test_login_link_is_not_consumed_by_get_and_is_single_use(): void
    {
        app(SupportLoginService::class)->send($this->project, $this->contact->email);
        $mail = Mail::sent(SupportMessage::class)->first();
        $this->get($mail->actionUrl)->assertOk();
        $this->assertNull(SupportLoginLink::query()->first()->used_at);
        $this->post($mail->actionUrl, ['confirm' => 1])->assertRedirect(route('support.home', $this->project->slug));
        $this->assertTrue(Auth::guard('support')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->post($mail->actionUrl, ['confirm' => 1])->assertNotFound();
    }

    public function test_unknown_email_has_the_same_response_and_sends_nothing(): void
    {
        config(['support.turnstile.secret' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $url = route('support.login.send', $this->project->slug);
        $this->from($url)->post($url, ['email' => 'unknown@example.test', 'cf-turnstile-response' => 'test'])->assertRedirect()->assertSessionHas('status');
        Mail::assertNothingSent();
        $this->post($url, ['email' => $this->contact->email, 'cf-turnstile-response' => 'test'])->assertRedirect()->assertSessionHas('status');
        Mail::assertSentCount(1);
    }

    public function test_expired_link_and_revoked_membership_cannot_log_in(): void
    {
        app(SupportLoginService::class)->send($this->project, $this->contact->email);
        $url = Mail::sent(SupportMessage::class)->first()->actionUrl;
        $this->project->contacts()->detach($this->contact);
        $this->post($url, ['confirm' => 1])->assertForbidden();
        $this->travel(31)->minutes();
        $this->get($url)->assertNotFound();
    }

    public function test_bad_turnstile_is_rejected(): void
    {
        config(['support.turnstile.secret' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $this->post(route('support.login.send', $this->project->slug), ['email' => $this->contact->email, 'cf-turnstile-response' => 'invalid'])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_revocation_blocks_an_open_session_and_api(): void
    {
        $this->loginClient();
        $this->get(route('support.home', $this->project->slug))->assertOk();
        $this->project->contacts()->detach($this->contact);
        $this->getJson(route('support.api.requests', $this->project->slug))->assertForbidden();
    }

    public function test_changing_contact_email_revokes_existing_session_identity(): void
    {
        $this->loginClient();
        $this->get(route('support.home', $this->project->slug))->assertOk();
        $this->contact->update(['email' => 'new-owner@example.test']);
        $this->getJson(route('support.api.requests', $this->project->slug))->assertForbidden();
    }
}
