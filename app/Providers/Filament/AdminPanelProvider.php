<?php

namespace App\Providers\Filament;

use App\Support\InitialsAvatarProvider;
use App\Support\Sta;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Enums\ThemeMode;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()])
            // Canvas shell: no top bar on desktop, so notifications live in the menu and there is no global search.
            ->databaseNotifications(position: DatabaseNotificationsPosition::Sidebar)
            ->globalSearch(false)
            ->brandName('BOC Sports Entry')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('3.5rem')
            ->colors([
                'primary' => Color::hex('#1F3A5F'),
                'gray' => Color::Slate,
                'success' => Color::Green,   // Submitted / Complete
                'warning' => Color::Orange,  // canvas warnings #9A3412 family
                'danger' => Color::Red,
                'info' => Color::Indigo,     // Draft / Male
                'pink' => Color::Pink,       // Female (event gender badge)
            ])
            ->font('Inter', url: Sta::FONTS_URL, provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/sta/theme.css')
            ->darkMode()
            // The approved canvas is the light theme; users can switch to Dark or System from the user menu.
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarWidth('15.5rem')
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.sidebar-role'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Discovers App\Filament\Pages\Dashboard, the canvas admin home.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ]);
    }
}
