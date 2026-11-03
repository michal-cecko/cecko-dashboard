<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\EditClientProfile;
use App\Filament\Common\Pages\Login;
use App\Http\Controllers\Tickets\ClientTicketAttachmentController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('podpora')
            ->path('podpora')
            ->brandName('Podpora')
            ->login(Login::class)
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->profile(EditClientProfile::class, isSimple: false)
            ->passwordReset()
            // Longer token life, so the invite link from a new client account still works the next days.
            ->authPasswordBroker('clients')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\Filament\Client\Pages')
            ->pages([])
            ->authenticatedRoutes(function (): void {
                Route::get('/tikety/{ticket}/prilohy/{filename}', ClientTicketAttachmentController::class)
                    ->middleware('signed')
                    ->name('ticket-attachment');
            })
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
