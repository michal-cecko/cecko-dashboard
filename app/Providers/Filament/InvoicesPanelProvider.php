<?php

namespace App\Providers\Filament;

use App\Filament\Common\Resources\Users\UserResource;
use App\Filament\Invoices\Components\CompanySwitcher;
use App\Http\Middleware\SetActiveCompany;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class InvoicesPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('invoices')
            ->path('faktury')
            ->brandName('Faktúry')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->databaseTransactions()
            ->profile()
            ->passwordReset()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Faktúry'),
                NavigationGroup::make('Nastavenia'),
                NavigationGroup::make('Ostatné'),
            ])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => \Blade::render('@livewire(\''.CompanySwitcher::class.'\')'))
            ->discoverResources(in: app_path('Filament/Invoices/Resources'), for: 'App\Filament\Invoices\Resources')
            ->resources([
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Invoices/Pages'), for: 'App\Filament\Invoices\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Invoices/Widgets'), for: 'App\Filament\Invoices\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SetActiveCompany::class,
            ]);
    }
}
