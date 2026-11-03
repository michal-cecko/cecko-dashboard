<?php

namespace App\Providers\Filament;

use App\Filament\Common\Pages\Login;
use App\Filament\Common\Resources\Users\UserResource;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;
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

class TicketsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tikety')
            ->path('tikety')
            ->brandName('Tikety')
            ->login(Login::class)
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->profile()
            ->passwordReset()
            ->plugins([
                PasskeysPlugin::make(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Rose,
            ])
            ->navigationGroups([
                NavigationGroup::make('Tikety'),
                NavigationGroup::make('Ostatné'),
            ])
            ->discoverResources(in: app_path('Filament/Tickets/Resources'), for: 'App\Filament\Tickets\Resources')
            // Registered by hand instead of TicketingPlugin, which also adds the form builder,
            // automations and spam filters that we don't use.
            ->resources([
                TicketResource::class,
                TicketStatusResource::class,
                UserResource::class,
            ])
            ->pages([])
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
