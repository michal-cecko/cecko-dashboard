<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ConfirmLinkRequest;
use App\Http\Requests\Support\LoginRequest;
use App\Models\Support\SupportConfirmation;
use App\Models\Support\SupportProject;
use App\Services\Support\SupportAccess;
use App\Services\Support\SupportLoginService;
use App\Services\Support\SupportWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportAuthController extends Controller
{
    public function login(SupportProject $project): View
    {
        abort_unless($project->is_active, 404);

        return view('support.login', compact('project'));
    }

    public function send(LoginRequest $request, SupportProject $project, SupportLoginService $service): RedirectResponse
    {
        $service->send($project, $request->string('email')->toString());

        return back()->with('status', __('support.check_email'));
    }

    public function loginConfirm(SupportProject $project, string $token, SupportLoginService $service): View
    {
        $service->link($project, $token);

        return view('support.confirm', ['project' => $project, 'heading' => __('support.login'), 'lines' => [__('support.login_body')], 'action' => route('support.login.consume', ['project' => $project->slug, 'token' => $token])]);
    }

    public function loginConsume(ConfirmLinkRequest $request, SupportProject $project, string $token, SupportLoginService $service): RedirectResponse
    {
        $contact = $service->consume($project, $token);
        Auth::guard('support')->login($contact);
        $request->session()->regenerate();
        $request->session()->put('support.email_hash', hash('sha256', $contact->email));

        return redirect()->route('support.home', $project->slug);
    }

    public function confirm(SupportProject $project, string $token, SupportWorkflow $workflow): View
    {
        $confirmation = $this->confirmation($project, $token);
        app(SupportAccess::class)->contact($project, true, $confirmation->contact);
        $lines = $confirmation->order ? $workflow->orderLines($confirmation->order) : [$confirmation->request->title, __('support.'.$confirmation->purpose)];

        return view('support.confirm', ['project' => $project, 'heading' => __('support.confirm'), 'lines' => $lines, 'action' => route('support.consume', ['project' => $project->slug, 'token' => $token])]);
    }

    public function consume(ConfirmLinkRequest $request, SupportProject $project, string $token, SupportWorkflow $workflow): View
    {
        $workflow->consume($this->confirmation($project, $token), $request->ip(), $request->userAgent() ?? '');

        return view('support.confirmed', compact('project'));
    }

    private function confirmation(SupportProject $project, string $token): SupportConfirmation
    {
        return SupportConfirmation::query()->where('project_id', $project->id)->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function logout(Request $request, SupportProject $project): RedirectResponse
    {
        Auth::guard('support')->logout();
        $request->session()->forget('support.email_hash');
        $request->session()->regenerateToken();

        return redirect()->route('support.login', $project->slug);
    }
}
