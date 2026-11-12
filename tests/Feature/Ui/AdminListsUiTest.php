<?php

use App\Enums\EventGender;
use App\Enums\Role;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Models\Association;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('lists associations as drawn: header, search, columns, admin state, editors and sports', function () {
    $judo = Association::factory()->create(['name' => 'Bahrain Judo Federation', 'code' => 'BJF', 'email' => 'office@bjf.example']);
    User::factory()->assocAdmin($judo)->create(['name' => 'Yusuf Kamal']);
    Sport::factory()->create(['association_id' => $judo->id, 'name' => 'Judo']);
    Association::factory()->create(['name' => 'Bahrain eSports Federation', 'code' => 'BEF']);

    livewire(ListAssociations::class)
        ->assertSeeInOrder(['Associations', '2 associations · 1 have an active admin · 0 invitations pending', 'New association',
            'Search by name or email', 'Association', 'Admin', 'Editors', 'Sports'])
        ->assertSee('Yusuf Kamal')->assertSee('No admin')->assertSee('0 / 2')->assertSee('office@bjf.example')
        ->assertSeeInOrder(['Users', 'Invitations'])
        // The Filament table is not drawn, so the page must render the action modals itself.
        ->assertSeeHtml('wire:partial="action-modals"');
});

it('shows "Invite pending" and the selected association\'s users and invitations', function () {
    $karate = Association::factory()->create(['name' => 'Bahrain Karate Federation', 'phone' => '+973 1700 4100']);
    Invitation::create(['association_id' => $karate->id, 'email' => 'info@bkf.example', 'role' => Role::AssocAdmin,
        'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addDays(7)]);
    $editor = User::factory()->assocEditor($karate)->create(['name' => 'Reem Hassan']);

    livewire(ListAssociations::class)->call('selectAssociation', $karate->id)
        ->assertSee('Invite pending')->assertSee('+973 1700 4100')
        ->assertSeeInOrder(['Edit details', 'Invite admin', 'Users', 'Reem Hassan', 'Editor', $editor->email, 'Active', 'Deactivate',
            'Invitations', 'info@bkf.example', 'Association Admin', 'expires', 'Pending', 'Invitation link (single use, 7 days)', 'Copy', 'Resend email', 'Revoke'])
        ->assertDontSee('No pending invitations.');
});

it('deactivates users, resends and revokes invitations from the aside, never touching STA accounts', function () {
    Mail::fake();
    $a = Association::factory()->create();
    $editor = User::factory()->assocEditor($a)->create();
    $inv = Invitation::create(['association_id' => $a->id, 'email' => 'new@x.bh', 'role' => Role::AssocEditor,
        'token_hash' => hash('sha256', 'y'), 'expires_at' => now()->addDays(7)]);
    $sta = User::factory()->sta()->create();

    $page = livewire(ListAssociations::class)->call('selectAssociation', $a->id);
    $page->callAction(TestAction::make('deactivateUser')->arguments(['id' => $editor->id]));
    expect($editor->fresh()->is_active)->toBeFalse();

    $page->callAction(TestAction::make('resendInvite')->arguments(['id' => $inv->id]))->assertNotified();
    expect($inv->fresh()->status())->toBe('revoked');
    $new = Invitation::query()->pending()->where('email', 'new@x.bh')->sole();
    expect($page->get('inviteLinks'))->toHaveKey($a->id.':new@x.bh');
    $page->assertSee($page->get('inviteLinks')[$a->id.':new@x.bh']);

    $page->callAction(TestAction::make('revokeInvite')->arguments(['id' => $new->id]));
    expect($new->fresh()->status())->toBe('revoked');

    expect(fn () => livewire(ListAssociations::class)->callAction(TestAction::make('deactivateUser')->arguments(['id' => $sta->id])))
        ->toThrow(ModelNotFoundException::class);
    expect($sta->fresh()->is_active)->toBeTrue();
});

it('keeps a sent link to its own association when the same email is invited by two', function () {
    Mail::fake();
    [$a, $b] = Association::factory()->count(2)->create();
    $mk = fn (Association $x) => Invitation::create(['association_id' => $x->id, 'email' => 'same@x.bh', 'role' => Role::AssocEditor,
        'token_hash' => hash('sha256', 'same'.$x->id), 'expires_at' => now()->addDays(7)]);
    $invA = $mk($a);
    $mk($b);

    $page = livewire(ListAssociations::class)->call('selectAssociation', $a->id)
        ->callAction(TestAction::make('resendInvite')->arguments(['id' => $invA->id]));
    $link = $page->get('inviteLinks')[$a->id.':same@x.bh'];
    $page->assertSee($link);

    $page->call('selectAssociation', $b->id)
        ->assertDontSee($link)->assertSeeHtml('The link is shown only on the page that sent it.');
});

it('shows the link sent by the aside Invite admin under that association\'s invitation', function () {
    Mail::fake();
    $a = Association::factory()->create(['email' => 'Info@Judo.bh']);

    $page = livewire(ListAssociations::class)->call('selectAssociation', $a->id)
        ->callAction(TestAction::make('invite')->table($a), data: ['email' => 'Info@Judo.bh']);

    $link = $page->get('inviteLinks')[$a->id.':info@judo.bh'];
    expect($link)->toContain('/invitations/');
    $page->assertSee($link)->assertSee('info@judo.bh')->assertDontSeeHtml('The link is shown only on the page that sent it.');
});

it('forbids Sports & events to STA without manage_master_data', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin/sports')->assertForbidden();
});

it('searches associations by name or email', function () {
    Association::factory()->create(['name' => 'Bahrain Chess Federation', 'email' => 'chess@bcf.example']);
    Association::factory()->create(['name' => 'Bahrain Judo Federation', 'email' => 'office@bjf.example']);

    livewire(ListAssociations::class)->set('tableSearch', 'chess@bcf')
        ->assertSee('Bahrain Chess Federation')->assertDontSee('Bahrain Judo Federation');
});

it('shows sports & events as drawn: list, details and events with gender, used-in-games and the Active switch', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Karate Federation']);
    $karate = Sport::factory()->create(['association_id' => $a->id, 'name' => 'Karate']);
    Event::create(['sport_id' => $karate->id, 'name' => 'Kata', 'gender' => EventGender::Women, 'is_active' => true]);

    livewire(ListSports::class)
        ->assertSeeInOrder(['Sports &amp; events', 'Association → sports → events. Only STA can change these; associations see them read-only.', 'New sport',
            'All associations', 'Karate', 'Karate Federation', '1 event', 'Active',
            'Sport name', 'Belongs to association', 'Active',
            'Karate events', 'Events used in a game can be deactivated, not deleted', 'New event name', 'Gender', 'Add event',
            'Event', 'Gender', 'Used in games', 'Active', 'Kata', 'Female', 'Not yet'], escape: false)
        ->assertSee('Bahrain Karate Federation');
});

it('adds events with the canvas errors and toggles them active', function () {
    $sport = Sport::factory()->create(['name' => 'Badminton']);

    $page = livewire(ListSports::class)->call('selectSport', $sport->id)
        ->call('addEvent')->assertSee('Type an event name first.')
        ->set('newName', 'Male Singles')->set('newGender', 'men')->call('addEvent')->assertSet('newName', '')
        ->set('newName', 'Male Singles')->call('addEvent')->assertSee('Male Singles (Male) already exists in this sport.');

    $event = $sport->events()->sole();
    expect($event->gender)->toBe(EventGender::Men)->and($event->is_active)->toBeTrue();

    $page->call('toggleEvent', $event->id);
    expect($event->fresh()->is_active)->toBeFalse();
});

it('saves the sport details on change with the edit page rules', function () {
    Sport::factory()->create(['name' => 'Squash']);
    $sport = Sport::factory()->create(['name' => 'Badminton']);
    $other = Association::factory()->create();

    livewire(ListSports::class)
        ->call('saveSport', $sport->id, 'name', 'Squash')->assertHasErrors('sport.name')
        ->call('saveSport', $sport->id, 'name', '')->assertHasErrors('sport.name')
        ->call('saveSport', $sport->id, 'name', 'Badminton & Squash')->assertHasNoErrors()
        ->call('saveSport', $sport->id, 'association_id', $other->id)
        ->call('saveSport', $sport->id, 'is_active', false);

    expect($sport->fresh())->name->toBe('Badminton & Squash')->association_id->toBe($other->id)->is_active->toBeFalse();
});

it('filters the sports list by association', function () {
    $a = Association::factory()->create();
    Sport::factory()->create(['association_id' => $a->id, 'name' => 'Chess']);
    Sport::factory()->create(['name' => 'Judo']);

    livewire(ListSports::class)->set('tableFilters.association.value', $a->id)
        ->assertSee('Chess')->assertDontSee('Judo');
});

it('marks medical and document changes in the audit log', function () {
    activity()->withProperties(['medical_changed' => true, 'documents_changed' => ['passport_no']])->event('updated')->log('updated');

    livewire(ListActivityLogs::class)->assertSee('Audit log')->assertSee('Medical data changed')->assertSee('Documents changed');
});

it('brands the public invitation pages', function () {
    auth()->logout();
    $this->get('/invitations/not-a-real-token')->assertStatus(410)
        ->assertSee('Bahrain Olympic Committee')->assertSee('Sports Entry')->assertSee('invalid or has expired');
});

it('draws the theme-only pages without Filament\'s "X / List" breadcrumb', function () {
    foreach (['/admin/clothing-items', '/admin/sta-users', '/admin/extra-fields', '/admin/settings'] as $url) {
        expect($this->get($url)->assertOk()->getContent())->not->toContain('fi-breadcrumbs');
    }
    $a = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($a)->create());
    foreach (["/portal/{$a->id}/members", "/portal/{$a->id}/invitations", "/portal/{$a->id}/import-people"] as $url) {
        expect($this->get($url)->assertOk()->getContent())->not->toContain('fi-breadcrumbs');
    }
});
