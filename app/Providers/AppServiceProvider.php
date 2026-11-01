<?php

namespace App\Providers;

use App\Http\Middleware\EnsureSupportAccess;
use App\Http\Middleware\SetSupportLocale;
use App\Models\Common\User;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;

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

        Livewire::addPersistentMiddleware([EnsureSupportAccess::class, SetSupportLocale::class]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament.shared.panel-topbar-switcher')->render(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_END,
            fn (): string => view('filament.shared.panel-sidebar-switcher')->render(),
        );
    }
}
