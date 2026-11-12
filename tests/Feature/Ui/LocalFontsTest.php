<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

/** Final wave item 14: every font is self-hosted; nothing loads from Google Fonts (or another font CDN). */
it('references no Google Fonts or other font CDN in views, CSS or app code', function () {
    $files = collect([resource_path('views'), resource_path('css'), resource_path('js'), app_path()])
        ->flatMap(fn ($dir) => File::allFiles($dir));

    foreach ($files as $file) {
        expect(file_get_contents($file->getPathname()))
            ->not->toContain('fonts.googleapis.com', $file->getPathname())
            ->not->toContain('fonts.gstatic.com', $file->getPathname())
            ->not->toContain('fonts.bunny.net', $file->getPathname());
    }
    expect(file_get_contents(resource_path('css/fonts.css')))->toContain("@import '@fontsource/inter/400.css'")->toContain("@import '@fontsource/space-grotesk/700.css'")
        ->and(file_get_contents(resource_path('css/filament/sta/theme.css')))->toContain("@import '../../fonts.css'");
});

it('serves the panels, invitation and welcome pages without a font CDN', function () {
    $this->get('/admin/login')->assertOk()->assertDontSee('fonts.googleapis.com')->assertDontSee('fonts.bunny.net');
    $this->get('/portal/login')->assertOk()->assertDontSee('fonts.googleapis.com');
    $this->get('/')->assertOk()->assertDontSee('fonts.bunny.net');
    actingInAdmin(User::factory()->superAdmin()->create());
    $this->get('/admin')->assertOk()->assertDontSee('fonts.googleapis.com');
});

it('bundles the PDF fonts with their OFL licences', function () {
    foreach (['Inter/Inter-Regular.ttf', 'Inter/Inter-Bold.ttf', 'SpaceGrotesk/SpaceGrotesk-Medium.ttf', 'SpaceGrotesk/SpaceGrotesk-Bold.ttf'] as $font) {
        expect(file_exists(resource_path('fonts/'.$font)))->toBeTrue($font);
    }
    expect(file_get_contents(resource_path('fonts/Inter/OFL.txt')))->toContain('SIL Open Font License')
        ->and(file_get_contents(resource_path('fonts/SpaceGrotesk/OFL.txt')))->toContain('SIL Open Font License')
        ->and(view('pdf.partials.styles')->render())->toContain("font-family: 'Inter'")->toContain('Inter-Regular.ttf')->toContain('SpaceGrotesk-Bold.ttf');
});
