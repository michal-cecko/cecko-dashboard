<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'cf-turnstile-response' => ['required', 'string', 'max:2048']];
    }

    public function messages(): array
    {
        return ['email.required' => __('support.email_required'), 'email.email' => __('support.email_required'), 'cf-turnstile-response.required' => __('support.captcha')];
    }

    protected function passedValidation(): void
    {
        $key = 'support-login:'.hash('sha256', mb_strtolower(trim($this->string('email')->toString())));
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429);
        RateLimiter::hit($key, 600);
        $secret = config('support.turnstile.secret');
        abort_unless($secret && Http::asForm()->timeout(15)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => $secret, 'response' => $this->input('cf-turnstile-response'), 'remoteip' => $this->ip()])->throw()->json('success'), 422, __('support.captcha'));
    }
}
