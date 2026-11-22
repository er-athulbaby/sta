<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson as PortalCreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson as PortalEditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople as PortalListPeople;
use App\Filament\Portal\Resources\People\PersonResource as PortalPersonResource;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\People\Pages\ListPeople as AdminListPeople;
use App\Filament\Resources\People\PersonResource as AdminPersonResource;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\User;
use App\Services\GameFiles\ExportSources;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

use function Pest\Livewire\livewire;

/** M6 Task A: testing feedback items 1, 7, 8 and 10. */
beforeEach(function () {
    $this->assoc = Association::factory()->create();
});

it('item 1: centres the label with the icon in link actions such as the Games row actions', function () {
    expect(file_get_contents(resource_path('css/filament/sta/theme.css')))
        ->toContain('.fi-link > .fi-link-label { align-self: center; white-space: nowrap; }');

    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::ViewReports->value, StaPermission::ManageGames->value]);
    actingInAdmin($sta);
    $game = Game::factory()->create();

    livewire(ListGames::class)->assertTableActionVisible('dashboard', $game)->assertTableActionVisible('reports', $game)
        ->assertSeeHtml('class="fi-link-label"');
});

it('item 7: says athlete, not player, in user-facing text', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    livewire(PortalCreatePerson::class)->assertSee(['Athlete mobile', 'Athlete email', 'Athlete address'])->assertDontSee('Player');

    // Source sweep: comments naming the canvas files (PlayerForm.dc.html) are the only allowed mentions.
    $hits = [];
    foreach (Finder::create()->files()->in(array_filter([app_path(), resource_path('views'), base_path('lang')], 'is_dir')) as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $n => $line) {
            if (preg_match('/\bplayers?\b/i', str_replace('PlayerForm', '', $line))) {
                $hits[] = $file->getRelativePathname().':'.($n + 1);
            }
        }
    }
    expect($hits)->toBe([]);
});

it('item 8: no longer shows or collects the clothing fit', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    DB::table('people')->where('id', $p->id)->update(['fit' => 'Youth']);

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])->assertFormFieldDoesNotExist('fit')
        ->assertDontSee('Fit</');
    expect(ExportSources::all())->not->toHaveKey('person.fit')
        ->and(collect(ExportSources::options(null))->flatten()->all())->not->toContain('Clothing fit')
        ->and($p->refresh()->fit)->toBe('Youth'); // column kept, value untouched
});

it('item 10: the registry has an Edit button per row that opens the full edit form', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);

    livewire(PortalListPeople::class)
        ->assertSeeHtml('href="'.e(PortalPersonResource::getUrl('edit', ['record' => $p])).'" class="sta-btn"')
        ->assertSeeHtml('Edit<span class="sta-sr-only"> '.e($p->fullName()).'</span>')
        ->assertSeeHtml('wire:click.prevent="selectPerson('.$p->id.')"'); // the name still opens the side panel
});

it('item 10: the admin People list has an Edit button per row for STA with manage_people', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    $p = Person::factory()->create();

    livewire(AdminListPeople::class)
        ->assertSeeHtml('href="'.e(AdminPersonResource::getUrl('edit', ['record' => $p])).'" class="sta-btn"')
        ->assertSeeHtml('wire:click="selectPerson('.$p->id.')"');
});

it('item 10: STA without manage_people cannot open the admin People list or edit form', function () {
    actingInAdmin(User::factory()->sta()->create());
    $p = Person::factory()->create();

    $this->get(AdminPersonResource::getUrl('edit', ['record' => $p]))->assertForbidden();
    $this->get(AdminPersonResource::getUrl('index'))->assertForbidden();
});
