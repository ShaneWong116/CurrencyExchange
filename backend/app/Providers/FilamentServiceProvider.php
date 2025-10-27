<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Assets\Css;
use Filament\Widgets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class FilamentServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(
                \App\Filament\Pages\Auth\Login::class
            )
            ->assets([
                Css::make('custom-stylesheet', base_path('public/filament/custom.css')),
            ])
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
                \App\Filament\Resources\TransactionResource::class,
                \App\Filament\Resources\ChannelResource::class,
                \App\Filament\Resources\SettlementResource::class,
                \App\Filament\Resources\TransactionDraftResource::class,
                \App\Filament\Resources\BalanceAdjustmentResource::class,
                \App\Filament\Resources\CapitalAdjustmentResource::class,
                \App\Filament\Resources\ImageResource::class,
                \App\Filament\Resources\FieldUserResource::class,
                \App\Filament\Resources\LocationResource::class,
                \App\Filament\Resources\SettingResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\PrimaryNetInflow::class,
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\BalanceOverview::class,
                \App\Filament\Widgets\SettlementStatsWidget::class,
                \App\Filament\Widgets\ChannelOverview::class,
                \App\Filament\Widgets\LocationOverview::class,
            ])
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
                \Filament\Http\Middleware\Authenticate::class,
            ])
            ->databaseNotifications()
            ->favicon(null)
            ->darkMode(true)
            ;
    }
}