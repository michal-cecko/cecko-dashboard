<?php

namespace App\Providers\Filament;

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

class ToolkitPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('toolkit')
            ->path('toolkit')
            ->brandName('Toolkit')
            ->login()
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->profile()
            ->passwordReset()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Violet,
            ])
            ->navigationGroups([
                NavigationGroup::make('Médiá'),
                NavigationGroup::make('Ostatné'),
            ])
            ->discoverResources(in: app_path('Filament/Toolkit/Resources'), for: 'App\Filament\Toolkit\Resources')
            ->resources([
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Toolkit/Pages'), for: 'App\Filament\Toolkit\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Toolkit/Widgets'), for: 'App\Filament\Toolkit\Widgets')
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
