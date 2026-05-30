<?php

namespace App\Providers;

use App\Models\Common\User;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The User model lives under App\Models\Common, so point laravel/passkeys
        // at it (it defaults to App\Models\User, which does not exist here).
        Passkeys::useUserModel(User::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS URLs in production
        if ($this->app->environment('production') || request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        Model::automaticallyEagerLoadRelationships();
        JsonResource::withoutWrapping();

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $address = config('mail.reply_to.address');

            if ($address) {
                $event->message->replyTo(new Address($address, config('mail.reply_to.name')));
            }
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament.shared.panel-topbar-switcher')->render(),
        );
    }
}
