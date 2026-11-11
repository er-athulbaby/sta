<?php

use App\Models\Association;
use App\Models\User;
use App\Support\InitialsAvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

it('registers the shared STA theme on both panels and in Vite', function () {
    foreach (['admin', 'portal'] as $id) {
        expect(Filament::getPanel($id)->getViteTheme())->toBe('resources/css/filament/sta/theme.css');
    }
    expect(file_get_contents(base_path('vite.config.js')))->toContain('resources/css/filament/sta/theme.css')
        ->and(file_exists(public_path('images/boc-logo.webp')))->toBeTrue()
        ->and(file_get_contents(resource_path('css/filament/sta/theme.css')))
        ->toContain("@source '../../../../resources/views/components/**/*'")
        ->toContain('--sta-nav: #0F2440');
});

it('shows the BOC brand, the panel name and the user role in the admin menu', function () {
    actingInAdmin(User::factory()->superAdmin()->create(['name' => 'Ali Haroona']));

    $this->get('/admin')->assertOk()
        ->assertSee('Bahrain Olympic Committee')->assertSee('Sports Entry')->assertSee('Admin panel')
        ->assertSee('images/boc-logo.webp', escape: false)->assertSee('Super Admin');
});

it('shows the association portal brand, the association and the role', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    actingInPortal(User::factory()->assocEditor($a)->create());

    $this->get("/portal/{$a->id}")->assertOk()
        ->assertSee('Association portal')->assertSee('Bahrain Weightlifting Federation')->assertSee('Association Editor');
});

it('orders the admin menu like the canvas', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $this->get('/admin')->assertSeeInOrder(
        ['Games', 'Associations', 'Sports &amp; events', 'Clothing items', 'People', 'Reports', 'Audit log', 'STA users'], escape: false);
});

it('draws initials avatars without calling an outside service', function () {
    $uri = (new InitialsAvatarProvider)->get(User::factory()->make(['name' => 'Mariam Khalil']));

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(Str::after($uri, 'base64,')))->toContain('>MK<')->toContain('#2B4C7E');
});
