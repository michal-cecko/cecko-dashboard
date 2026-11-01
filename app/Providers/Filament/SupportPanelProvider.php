<?php

namespace App\Providers\Filament;

use App\Filament\Common\Resources\AiUsage\AiUsageResource;
use App\Filament\Common\Resources\Users\UserResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcelWeidum\Passkeys\PasskeysPlugin;

class SupportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('support')
            ->path('podpora')
            ->brandName('Podpora')
            ->login()
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->profile()
            ->passwordReset()
            ->plugins([
                PasskeysPlugin::make(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->navigationGroups([
                NavigationGroup::make('Podpora'),
                NavigationGroup::make('Ostatné'),
            ])
            ->discoverResources(in: app_path('Filament/Support/Resources'), for: 'App\Filament\Support\Resources')
            ->resources([
                AiUsageResource::class,
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Support/Pages'), for: 'App\Filament\Support\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Support/Widgets'), for: 'App\Filament\Support\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
