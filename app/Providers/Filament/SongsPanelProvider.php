<?php

namespace App\Providers\Filament;

use App\Filament\Common\Resources\MobileApps\MobileAppResource;
use App\Filament\Common\Resources\Users\UserResource;
use App\Filament\Songs\Resources\Songs\SongResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SongsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('songs')
            ->path('kniha-piesni')
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->brandName("Kniha Piesní • Veľký Dom")
            ->brandLogo("/logo/songs/logo-vd-dark.png")
            ->darkModeBrandLogo("/logo/songs/logo-vd-white.png")
            ->login()
            ->favicon("/favicon/songs/favicon.ico")
            ->colors([
                'primary' => Color::Sky,
            ])
            ->databaseTransactions()
            ->profile()
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Songs/Resources'), for: 'App\Filament\Songs\Resources')
            ->resources([
                UserResource::class,
                MobileAppResource::class
            ])
            ->discoverPages(in: app_path('Filament/Songs/Pages'), for: 'App\Filament\Songs\Pages')
            ->pages([
                //Dashboard::class,
            ])
            ->assets([
                Css::make('songs', public_path('css/songs.css')),
            ])
            ->discoverWidgets(in: app_path('Filament/Songs/Widgets'), for: 'App\Filament\Songs\Widgets')
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
