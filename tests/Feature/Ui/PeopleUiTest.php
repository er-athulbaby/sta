<?php

use App\Enums\AppliesTo;
use App\Filament\Portal\Resources\People\Pages\EditPerson as PortalEditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople as PortalListPeople;
use App\Filament\Resources\People\Pages\ListPeople as AdminListPeople;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;

use function Pest\Livewire\livewire;

it('lists the portal registry with type tabs, size state and the canvas subtitle', function () {
    $a = Association::factory()->create();
    Person::factory()->official()->create(['association_id' => Association::factory()->create()->id]); // another association: not counted
    actingInPortal(User::factory()->assocAdmin($a)->create());
    ClothingItem::factory()->create(['name' => 'Suit', 'applies_to' => AppliesTo::Both, 'is_active' => true]);
    $p = Person::factory()->create(['association_id' => $a->id, 'first_name' => 'Hamad', 'last_name' => 'Buali']);

    livewire(PortalListPeople::class)
        ->assertSee('Your permanent registry · reused for every game · 1 athletes, 0 team officials')
        ->assertSee('1 missing')->assertSee('Athletes')->assertSee('Officials')->assertSee('Import Excel')->assertSee('Add person')
        ->assertCanSeeTableRecords([$p]);
});

it('shows the admin People KPI tiles', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::factory()->count(2)->create();
    Person::factory()->official()->create(['passport_expiry' => now()->addMonths(2)->toDateString()]);

    $page = livewire(AdminListPeople::class)
        ->assertSee('Team officials')->assertSee('Passport expires within 6 months')->assertSee('Missing passport scan');

    expect($page->instance()->kpis())->toMatchArray(['athletes' => 2, 'officials' => 1, 'expiring' => 1, 'missingScan' => 3]);
});

it('colours the person form sections and shows completeness in the header', function () {
    $a = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($a)->create());
    $p = Person::factory()->create(['association_id' => $a->id]);

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertSeeHtml('sta-band-blue')->assertSeeHtml('sta-band-indigo')->assertSeeHtml('sta-band-rose')
        ->assertSee($p->code)->assertSee('Incomplete')->assertSee('Missing:')->assertSee('Save as draft');
});
