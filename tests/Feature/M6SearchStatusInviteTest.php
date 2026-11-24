<?php

use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Portal\Resources\People\Pages\ListPeople as PortalListPeople;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\People\Pages\ListPeople as AdminListPeople;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

/** Feedback batch items 3-6: people search, Active/Inactive filter, invite editor, Registry status footnote. */
beforeEach(function () {
    $this->assoc = Association::factory()->create();
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'second_name' => 'Ahmed',
        'last_name' => 'Hasan', 'full_name' => 'Ali Ahmed Hasan Alsayed', 'passport_no' => 'BH1234567', 'cpr_no' => '040917332']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
});

it('searches people by partial name in any case, several words, code, and exact CPR or passport', function () {
    $found = fn (string $term) => Person::withoutGlobalScopes()->searchTerm($term)->pluck('id')->all();

    expect($found('ali'))->toBe([$this->ali->id])
        ->and($found('HAS'))->toBe([$this->ali->id])
        ->and($found('ahm'))->toBe([$this->ali->id])
        ->and($found('alsayed'))->toBe([$this->ali->id])
        ->and($found('ali hasan'))->toBe([$this->ali->id])
        ->and($found('hasan  ALI'))->toBe([$this->ali->id])
        ->and($found('ali saleh'))->toBe([])
        ->and($found(substr($this->omar->code, 0, -1)))->toContain($this->omar->id)
        ->and($found('bh 123 4567'))->toBe([$this->ali->id])
        ->and($found('040 917 332'))->toBe([$this->ali->id])
        ->and($found('BH12345'))->toBe([])
        ->and($found('  '))->toHaveCount(2);
});

it('searches the portal and admin registry lists by passport with spaces and by two names', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    livewire(PortalListPeople::class)->set('tableSearch', 'bh1234 567')
        ->assertCanSeeTableRecords([$this->ali])->assertCanNotSeeTableRecords([$this->omar])
        ->set('tableSearch', 'omar saleh')->assertCanSeeTableRecords([$this->omar])->assertCanNotSeeTableRecords([$this->ali]);

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(AdminListPeople::class)->set('tableSearch', '040917332')
        ->assertCanSeeTableRecords([$this->ali])->assertCanNotSeeTableRecords([$this->omar]);
});

it('shows active people by default with an Active / Inactive / All filter on both registry lists', function () {
    $this->omar->update(['is_active' => false]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(PortalListPeople::class)
        ->assertSeeHtml('wire:model.live="tableFilters.status.value"')
        ->assertCanSeeTableRecords([$this->ali])->assertCanNotSeeTableRecords([$this->omar])
        ->set('tableFilters.status.value', 'inactive')->assertCanSeeTableRecords([$this->omar])->assertCanNotSeeTableRecords([$this->ali])
        ->set('tableFilters.status.value', '')->assertCanSeeTableRecords([$this->ali, $this->omar]);

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(AdminListPeople::class)
        ->assertCanSeeTableRecords([$this->ali])->assertCanNotSeeTableRecords([$this->omar])
        ->set('tableFilters.status.value', 'inactive')->assertCanSeeTableRecords([$this->omar]);
});

it('lets STA invite an editor with a name, from the button or the Invitations "+", within the editor limit', function () {
    Mail::fake();
    actingInAdmin(User::factory()->superAdmin()->create());

    $page = livewire(ListAssociations::class)->call('selectAssociation', $this->assoc->id)
        ->assertSeeInOrder(['Invite admin', 'Invite editor'])->assertSeeHtml('aria-label="Invite a user to '.e($this->assoc->name).'"')
        ->callAction(TestAction::make('inviteEditor')->table($this->assoc), data: ['name' => 'Reem Hassan', 'email' => 'reem@x.bh'])
        ->assertNotified('Invitation sent');
    $inv = Invitation::where('email', 'reem@x.bh')->sole();
    expect($inv->role)->toBe(Role::AssocEditor)->and($inv->name)->toBe('Reem Hassan');

    $page->callAction(TestAction::make('inviteUser')->table($this->assoc), data: ['role' => Role::AssocEditor->value, 'email' => 'two@x.bh'])
        ->assertNotified('Invitation sent')
        ->callAction(TestAction::make('inviteUser')->table($this->assoc), data: ['role' => Role::AssocEditor->value, 'email' => 'three@x.bh'])
        ->assertNotified('This association already has 2 editors (active or invited).');
    expect(Invitation::where('email', 'three@x.bh')->exists())->toBeFalse();

    $page->callAction(TestAction::make('inviteUser')->table($this->assoc), data: ['role' => Role::AssocAdmin->value, 'email' => 'boss@x.bh']);
    expect(Invitation::where('email', 'boss@x.bh')->sole()->role)->toBe(Role::AssocAdmin);
});

it('pre-fills the invited name on the accept page', function () {
    $inv = Invitation::create(['association_id' => $this->assoc->id, 'email' => 'n@x.bh', 'name' => 'Reem Hassan', 'role' => Role::AssocEditor,
        'token_hash' => hash('sha256', 'tok'), 'expires_at' => now()->addDays(7)]);

    $this->get(route('invitations.show', 'tok'))->assertOk()->assertSee('value="Reem Hassan"', false);
});

it('builds the Registry status footnote from the Long List fields', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Dashboard::class)->assertSee('Registry status')->assertDontSee('Registry health')
        ->assertSee('Incomplete = missing any Long List field: Date of birth, Gender, Nationality, Passport number, Passport expiry, Full name')
        ->assertSee('team officials also Function, Mobile')
        ->assertDontSee('CPR number.');
});
