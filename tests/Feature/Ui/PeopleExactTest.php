<?php

use App\Enums\AppliesTo;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson as PortalCreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson as PortalEditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople as PortalListPeople;
use App\Filament\Portal\Resources\People\PersonResource as PortalPersonResource;
use App\Filament\Resources\People\Pages\EditPerson as AdminEditPerson;
use App\Filament\Resources\People\Pages\ListPeople as AdminListPeople;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/** Task 8b-3: canvas Registry.dc.html, AdminPeople.dc.html, PlayerForm.dc.html and OfficialForm.dc.html. */
beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
    $this->shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L'], 'applies_to' => AppliesTo::Both, 'is_active' => true]);
});

/** assertSeeInOrder on a Livewire update response reads its JSON (non-ASCII escaped); check the rendered HTML instead. */
function expectInOrder(string $html, array $needles): void
{
    $at = 0;
    foreach ($needles as $needle) {
        $found = strpos($html, $needle, $at);
        expect($found)->not->toBeFalse("\"{$needle}\" not found in order");
        $at = $found;
    }
}

function otherAssociationPerson(array $attrs = []): Person
{
    $person = Person::factory()->create($attrs);
    DB::table('people')->where('id', $person->id)->update(['association_id' => Association::factory()->create()->id]);

    return $person->refresh();
}

it('draws the registry list and edit aside in the canvas order', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Hamad', 'last_name' => 'Buali']);

    livewire(PortalListPeople::class)
        ->assertSeeInOrder(['Athletes &amp; officials', 'Your permanent registry', 'Download template', 'Import Excel', 'Add person'], false)
        ->assertSeeInOrder(['Search by name or passport', 'All', 'Athletes', 'Officials', 'Name', 'Type', 'Date of birth', 'Passport expiry', 'Sizes'])
        ->assertSee('Showing 1 of 1 · passport dates in orange expire within 6 months')
        ->assertSeeInOrder(['Hamad Buali', 'Documents', 'Passport number', 'Passport expiry', 'CPR number', 'CPR expiry', 'Upload passport scan',
            'Clothing sizes', 'T-Shirt', 'Required for this game', 'Save changes', 'Save as draft', 'Cancel'])
        ->assertCanSeeTableRecords([$p]);
});

it('filters the registry by the type switch and finds a passport number exactly', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $athlete = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_no' => 'BH5521907']);
    $official = Person::factory()->official()->create(['association_id' => $this->assoc->id]);

    livewire(PortalListPeople::class)
        ->set('activeTab', 'officials')->assertCanSeeTableRecords([$official])->assertCanNotSeeTableRecords([$athlete])
        ->set('activeTab', 'all')->set('tableSearch', 'BH5521907')->assertCanSeeTableRecords([$athlete])->assertCanNotSeeTableRecords([$official]);
});

it('saves documents and sizes from the registry aside with the registry rules', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    otherAssociationPerson(['passport_no' => 'TAKEN123']);

    $page = livewire(PortalListPeople::class)->call('selectPerson', $p->id)
        ->set('panel.passport_no', 'TAKEN123')->call('savePanel')->assertHasErrors(['panel.passport_no']);

    $page->set('panel.passport_no', 'NEW55555')->set("panel.sizes.{$this->shirt->id}", 'M')->call('savePanel')->assertHasNoErrors();
    expect($p->refresh()->passport_no)->toBe('NEW55555')->and($p->sizesMap())->toBe([$this->shirt->id => 'M']);

    $page->set("panel.sizes.{$this->shirt->id}", 'XXXL')->call('savePanel')->assertHasErrors(["panel.sizes.{$this->shirt->id}"]);
});

it('never opens another association\'s person in the registry aside', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $mine = Person::factory()->create(['association_id' => $this->assoc->id]);
    $theirs = otherAssociationPerson(['first_name' => 'Foreign', 'last_name' => 'Person']);

    livewire(PortalListPeople::class)->call('selectPerson', $theirs->id)
        ->assertSet('panelFor', $mine->id)->assertDontSee('Foreign Person')
        ->call('savePanel');
    expect($theirs->refresh()->updated_at->equalTo($theirs->updated_at))->toBeTrue();
});

it('draws the admin People list with its filters and the person aside', function () {
    $admin = actingInAdmin(User::factory()->superAdmin()->create());
    $soon = Person::factory()->create(['first_name' => 'Ali', 'last_name' => 'Soon', 'passport_no' => 'AB1234567', 'passport_expiry' => now()->addMonths(2)->toDateString()]);
    $later = Person::factory()->official()->create(['passport_expiry' => now()->addYears(3)->toDateString()]);

    $page = livewire(AdminListPeople::class)
        ->assertSeeInOrder(['People', 'Export Excel', 'Athletes', 'Team officials', 'Passport expires within 6 months', 'Missing passport scan'])
        ->assertSeeInOrder(['Name, passport or CPR', 'All associations', 'All', 'Athletes', 'Officials', 'Expiring passports',
            'Name', 'Association', 'Type', 'Passport expiry', 'Scans'])
        ->call('selectPerson', $soon->id);
    expectInOrder($page->html(), ['Ali Soon', 'Date of birth', 'Gender', 'Nationality', 'Passport number', '•••• 4567', 'Passport expiry', 'CPR number',
        'Scans uploaded', 'Clothing sizes', 'View passport scan', 'View CPR scan', 'Scans open through a secure link valid for 5 minutes.', 'Edit', 'Delete (Super Admin)']);
    expect($page->html())->not->toContain('AB1234567');

    $page->set('tableFilters.expiring.isActive', true)->assertCanSeeTableRecords([$soon])->assertCanNotSeeTableRecords([$later])
        ->set('tableFilters.expiring.isActive', false)
        ->set('tableFilters.association.value', (string) $later->association_id)->assertCanSeeTableRecords([$later])->assertCanNotSeeTableRecords([$soon]);

    expect($page->instance()->exportExcel()->headers->get('content-disposition'))->toContain('people.xlsx');
});

it('shows the admin aside Delete only to the super admin', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    Person::factory()->create();

    livewire(AdminListPeople::class)->assertSee('Edit')->assertDontSee('Delete (Super Admin)');
});

it('numbers the athlete form sections and jump links as drawn', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_issuing_country' => null, 'passport_issue_date' => null]);

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertSeeInOrder(['Athletes &amp; officials', 'Edit athlete', e($p->fullName()), $p->code, 'Incomplete', 'Missing: ',
            'Cancel', 'Save as draft', 'Save'], false)
        ->assertSee('Required to save. Fields marked “needed for Long List” can stay empty for now, but the athlete stays Incomplete')
        ->assertSeeHtml('href="#passport"')->assertSeeHtml('id="passport"')->assertSeeHtml('id="consents"')
        ->assertSeeInOrder(['1 Person', '2 Passport', 'missing', '3 CPR', '4 Contact', '5 Guardian', '6 Clothing', '7 Medical', '8 Consents'])
        ->assertSeeInOrder(['1 · Person', 'Photo', 'Type', 'Full name (as in passport)', 'First name', 'Second name', 'Third name', 'Last name',
            'Date of birth', 'Age', 'Gender', 'Nationality', 'Height (cm)', 'Weight (kg)',
            '2 · Passport', 'Passport scan', 'Passport number', 'Issuing country', 'Missing · needed for Long List', 'Issue date', 'Expiry date',
            '3 · CPR', 'CPR number', 'CPR expiry', 'CPR front', 'CPR back', 'CPR reader copy', 'Upload reader printout',
            '4 · Contact', 'Player mobile', 'WhatsApp is the same as mobile', 'Player email', 'Player address', 'Emergency contact name', 'Relationship', 'Emergency phone',
            '5 · Guardian', 'For athletes under 18.', '6 · Clothing', 'Fit', 'Chest (cm)', 'Waist (cm)', 'T-Shirt size',
            '7 · Medical', 'Visible to your association and STA staff with medical permission', 'Blood group', 'Injuries',
            '8 · Consents', 'Consent to store personal data', 'Consent to use photos in media', 'Consent for emergency medical treatment']);
});

it('renumbers for team officials and when medical is hidden', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $html = livewire(PortalCreatePerson::class)->fillForm(['type' => 'official'])->html();
    expectInOrder($html, ['New team official', '1 Person', '2 Official details', '3 Passport', '4 CPR', '5 Contact', '6 Visas', '7 Clothing', '8 Health', '9 Declarations']);
    expectInOrder($html, ['2 · Team official details', 'Function', 'Languages spoken', '6 · Visas', 'Add one row per country', 'Add visa',
        '8 · Health', 'Meal requirement', '9 · Declarations', 'Accepts the delegation code of conduct']);

    $p = Person::factory()->create();
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    livewire(AdminEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertSeeInOrder(['6 Clothing', '7 Consents'])->assertSee('7 · Consents')->assertDontSee('7 · Medical');
});

it('saves as draft and stays, or saves and returns to the registry', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])->fillForm(['first_name' => 'Draft'])->call('save')
        ->assertHasNoFormErrors()->assertNoRedirect();
    expect($p->refresh()->first_name)->toBe('Draft');

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])->fillForm(['first_name' => 'Closed'])->call('saveAndClose')
        ->assertHasNoFormErrors()->assertRedirect(PortalPersonResource::getUrl('index'));
    expect($p->refresh()->first_name)->toBe('Closed');

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])->fillForm(['first_name' => ''])->call('saveAndClose')
        ->assertHasFormErrors(['first_name' => 'required'])->assertNoRedirect();
});
