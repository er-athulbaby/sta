<?php

namespace App\Providers\Filament;

use App\Enums\Role;
use App\Filament\Portal\Auth\EditMyProfile;
use App\Filament\Portal\Pages\EditAssociationProfile;
use App\Models\Association;
use App\Support\InitialsAvatarProvider;
use Filament\Enums\ThemeMode;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
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

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->tenant(Association::class)
            ->tenantProfile(EditAssociationProfile::class)
            ->tenantMenu(false) // one association per user: the canvas association block replaces the switcher
            ->passwordReset()
            ->profile(EditMyProfile::class)
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
            ->font('Inter', provider: LocalFontProvider::class) // self-hosted in the theme (resources/css/fonts.css)
            ->viteTheme('resources/css/filament/sta/theme.css')
            ->darkMode()
            // The approved canvas is the light theme; users can switch to Dark or System from the user menu.
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarWidth('15.5rem')
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.sidebar-role'))
            ->renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn () => view('filament.sidebar-association'))
            ->navigationItems([
                NavigationItem::make('Association profile')
                    ->url(fn () => EditAssociationProfile::getUrl())
                    ->isActiveWhen(fn () => request()->is('portal/*/profile'))
                    ->sort(5)
                    ->visible(fn () => auth()->user()?->role === Role::AssocAdmin),
            ])
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\Filament\Portal\Widgets')
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
