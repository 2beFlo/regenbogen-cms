<?php

namespace App\Providers\Filament;

use AdriaanZon\FilamentPasskeys\FilamentPasskeysPlugin;
use AdriaanZon\FilamentPasskeys\PasskeyAuthentication;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WelcomeWidget;
use App\Http\Middleware\EnforcePrivilegedMfa;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            ->plugins([
                FilamentPasskeysPlugin::make()->passwordlessLogin(),
            ])
            ->multiFactorAuthentication([
                PasskeyAuthentication::make(),
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('REGENBOGEN Redaktion'),
            ])
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label('Mein Konto & Sicherheit')
                    ->icon('heroicon-o-shield-check'),
            ])
            ->navigationItems([
                NavigationItem::make('Mein Konto & Sicherheit')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (): string => filament()->getProfileUrl() ?? '#')
                    ->sort(90),
                NavigationItem::make('Website ansehen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): string => route('home'))
                    ->openUrlInNewTab()
                    ->sort(91),
            ])
            ->brandName('REGENBOGEN Redaktion')
            ->favicon(asset('assets/regenbogen-mark.svg'))
            ->colors(['primary' => Color::Green])
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(Width::Full)
            ->breadcrumbs(false)
            ->globalSearch(false)
            ->unsavedChangesAlerts()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="'.e(asset('css/regenbogen-admin.css')).'">',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->widgets([WelcomeWidget::class])
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
                EnforcePrivilegedMfa::class,
            ]);
    }
}
