<?php

namespace App\Filament\Common\Pages;

use App\Enums\Common\FilamentPanelEnum;
use App\Models\Common\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Every panel has its own login page. A user who signs in on a panel they cannot open
 * goes to the first panel they can open, instead of seeing "wrong credentials".
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $data = $this->form->getState();
            $user = $this->userWithValidCredentials($data);
            $panel = $user === null ? null : FilamentPanelEnum::firstAccessibleBy($user);

            if ($panel === null) {
                throw $exception;
            }

            Filament::auth()->login($user, (bool) ($data['remember'] ?? false));
            session()->regenerate();

            return $this->redirectTo($panel);
        }
    }

    /**
     * Runs in a timebox like the parent's check, so the response time does not tell
     * whether the e-mail exists.
     *
     * @param  array<string, mixed>  $data
     */
    private function userWithValidCredentials(array $data): ?User
    {
        $credentials = $this->getCredentialsFromFormData($data);

        return app(Timebox::class)->call(function (Timebox $timebox) use ($credentials): ?User {
            $provider = Filament::auth()->getProvider();
            $user = $provider->retrieveByCredentials($credentials);

            if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
                return null;
            }

            $timebox->returnEarly();

            return $user;
        }, (int) config('auth.timebox_duration', 200_000));
    }

    private function redirectTo(Panel $panel): LoginResponse
    {
        return new class($panel->getUrl()) implements LoginResponse
        {
            public function __construct(private string $url) {}

            public function toResponse($request): RedirectResponse|Redirector
            {
                return redirect()->to($this->url);
            }
        };
    }
}
