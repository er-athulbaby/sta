<?php

use App\Models\Association;
use App\Models\User;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

// Task 15: Light / Dark / System switch in the user menu; light stays the canvas look.

it('enables dark mode on both panels and keeps the canvas light theme as the default', function () {
    foreach (['admin', 'portal'] as $id) {
        $panel = Filament::getPanel($id);
        expect($panel->hasDarkMode())->toBeTrue()
            ->and($panel->isDarkModeForced())->toBeFalse()
            ->and($panel->getDefaultThemeMode())->toBe(ThemeMode::Light);
    }
});

it('shows Profile, the Light / Dark / System switch and Sign out in the admin user menu', function () {
    actingInAdmin(User::factory()->superAdmin()->create(['name' => 'Ali Haroona']));

    $html = $this->get('/admin')->assertOk()->getContent();
    $menu = Str::after($html, 'fi-sidebar-footer');

    expect($menu)->toMatch('/fi-user-menu-trigger.*?Ali Haroona/s')
        ->toMatch('/admin\/profile.*?fi-theme-switcher.*?Sign out/s')
        ->toContain('Enable light theme')->toContain('Enable dark theme')->toContain('Enable system theme');
});

// The portal has no user profile page (only the association profile in the menu), so its user menu is the switch and Sign out.
it('shows the theme switch and Sign out in the portal user menu', function () {
    $a = Association::factory()->create();
    actingInPortal(User::factory()->assocEditor($a)->create());

    $menu = Str::after($this->get("/portal/{$a->id}")->assertOk()->getContent(), 'fi-sidebar-footer');

    expect($menu)->toMatch('/fi-user-menu-trigger.*?fi-theme-switcher.*?Sign out/s')
        ->toContain('Enable light theme')->toContain('Enable dark theme')->toContain('Enable system theme');
});

it('defines every colour token for light (canvas value) and for dark', function () {
    $css = file_get_contents(resource_path('css/filament/sta/theme.css'));
    preg_match_all('/var\((--sta-(?:bg|fg|bd)-[\w-]+), (#[0-9A-F]{6})\)/', $css.implode('', array_map('file_get_contents', bladeViews())), $uses, PREG_SET_ORDER);
    [$root, $dark] = [Str::between($css, 'Role tokens', '.dark {'), Str::between($css, '.dark {', '}')];

    foreach ($uses as [, $token, $hex]) {
        expect($root)->toContain("{$token}: {$hex};");
        if ($token !== '--sta-bg-logo') {
            expect($dark)->toContain("{$token}:");
        }
    }
});

// Guard: a raw colour in a style attribute would stay light in dark mode.
it('writes no raw #RRGGBB in a style attribute outside the PDFs and invitation pages', function () {
    $raw = [];
    foreach (bladeViews() as $file) {
        preg_match_all('/style="[^"]*"/', file_get_contents($file), $styles);
        foreach ($styles[0] as $style) {
            if (preg_match_all('/(?<!, )#[0-9A-Fa-f]{6}\b/', $style, $m)) {
                $raw[] = Str::after($file, 'views').': '.implode(', ', $m[0]);
            }
        }
    }

    expect($raw)->toBe([]);
});

function bladeViews(): array
{
    return collect(File::allFiles(resource_path('views')))
        ->map(fn ($f) => $f->getPathname())
        ->filter(fn ($p) => str_ends_with($p, '.blade.php') && ! preg_match('#/(pdf|invitations)/#', str_replace('\\', '/', $p)))
        ->values()->all();
}
