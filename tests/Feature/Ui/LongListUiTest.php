<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Filament\Portal\Pages\Phase3Events;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\StaPhase3Edit;
use App\Filament\Resources\Games\Pages\StaPhase3EventsEdit;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Support\Sta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
});

it('adds and removes people with chips and pickers, then saves through the usual checks', function () {
    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->call('addAthleteChip', $this->m60->id, (string) $this->ali->id)
        ->call('addAthleteChip', $this->m60->id, (string) $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSeeHtml('sta-chip-person')->assertSeeHtml('aria-label="Remove Ali Hasan from Men -60kg"')
        ->call('addOfficialChip', $this->judo->id, (string) $this->coach->id)
        ->assertSet("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->call('addAthleteChip', $this->m60->id, (string) $this->omar->id)
        ->call('removeAthleteChip', $this->m60->id, $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->omar->id])
        ->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(2);
});

it('compares with Phase 2 and summarises the people on the list', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    $p2->update(['status' => SubmissionStatus::Submitted]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSee('Compared with your Phase 2 numbers')->assertSee('Men -60kg · 2 of 1')->assertSee('1 event differs')
        ->assertSee('Athletes (2)')->assertSeeText('2 male · 0 female · 0 team officials');

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSeeText('Long List 2 / Phase 2: 1')->assertSee('Over the Phase 2 number');
});

it('ignores a person from another association', function () {
    $other = Association::factory()->create(['code' => 'XYZ']);
    $foreign = Person::factory()->complete()->create(['association_id' => $other->id, 'first_name' => 'Zed', 'last_name' => 'Foreign']);
    Person::withoutGlobalScopes()->whereKey($foreign->id)->update(['association_id' => $other->id]); // the model pins new people to the current association

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('addAthleteChip', $this->m60->id, (string) $foreign->id)
        ->call('addOfficialChip', $this->judo->id, (string) $foreign->id)
        ->call('addOfficialChip', $this->judo->id, (string) $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [])
        ->assertSet("officials.{$this->judo->id}", [])
        ->set("athletes.{$this->m60->id}", [(string) $foreign->id])
        ->assertDontSee('Zed Foreign');
});

it('draws the LongList canvas sections in order, with EVENT links to the events screen', function () {
    Submission::for($this->game, $this->assoc, 3)->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->assertSeeHtmlInOrder(['Long List', 'Phase 3', 'Preview PDF', 'Save as draft', 'Submit to STA',
            'Athletes (1)', 'Team officials (0)', 'Warnings (', 'Add athlete', 'Add team official', 'List', 'Grid',
            'Athlete', 'Gender', 'Born · age', 'Event', 'Scans', 'Warning', 'Ali Hasan', 'Men -60kg', 'Team officials'])
        ->assertDontSee('Pick athletes from your registry')->assertDontSee('Search athletes…')
        ->assertSeeHtml(e(Phase3Events::getUrl(['game' => $this->game->id]).'#sport-'.$this->judo->id));
});

it('draws the LongListEvents canvas sections in order', function () {
    $this->actingAs(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->assertSeeHtmlInOrder(['Long List', 'Phase 3', 'Only sports you entered in Phase 1 are shown', 'Save as draft', 'Send for approval',
            'The association admin sees', 'Judo', 'Add athlete', 'Add team official', 'Pick athletes from your registry', 'Men -60kg', 'Search athletes…',
            'Team officials', 'Search officials by name or function', 'People on your Long List'])
        ->assertDontSee('Preview PDF')
        ->assertSeeHtml('role="listbox"')->assertSeeHtml('aria-multiselectable="true"')
        ->assertSee('Female and inactive athletes are not listed for this event.');
});

it('lists the people with Just added and the not saved state on the events screen', function () {
    $sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    Submission::for($this->game, $this->assoc, 3)->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->call('addAthleteChip', $this->m60->id, $this->omar->id)
        ->call('addOfficialChip', $this->judo->id, $this->coach->id)
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id, (string) $sara->id])
        ->call('save')->assertHasErrors(["athletes.{$this->m60->id}"])
        ->assertSeeHtmlInOrder(['Person', 'Gender', 'Role', 'Sport · events'])
        ->assertSee('Just added')->assertSee('Team official · '.$this->coach->function)
        ->assertSee('Judo · Men -60kg — not saved')->assertSeeHtml('color: var(--sta-fg-red-700, #B91C1C)">Judo · Men -60kg — not saved');
});

it('labels events with their gender unless the name says it', function () {
    $event = Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => '60kg', 'gender' => EventGender::Women]);

    expect(Sta::eventLabel($event))->toBe('Female 60kg')
        ->and(Sta::eventLabel($this->m60->event))->toBe('Men -60kg');
});

it('streams a preview of the Long List PDF after saving the draft', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('addAthleteChip', $this->m60->id, (string) $this->ali->id)
        ->call('previewPdf')->assertFileDownloaded('long-list-preview.pdf');

    expect(Entry::count())->toBe(1);
});

it('never previews off the portal: no button and the call does nothing on the STA page', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $s->update(['status' => SubmissionStatus::Submitted, 'pdf_paths' => ['long_list' => 'kept.pdf']]);
    $version = $s->fresh()->lock_version;
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertDontSee('Preview PDF')->call('previewPdf')->assertNoFileDownloaded();

    expect($s->fresh()->lock_version)->toBe($version)->and($s->fresh()->pdf_paths)->toBe(['long_list' => 'kept.pdf']);
    Bus::assertNothingDispatched();
});

it('previews a read-only Long List without saving', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $this->game->phase(3)->update(['deadline' => now()->subDay()]);
    $version = $s->fresh()->lock_version;

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('previewPdf')->assertFileDownloaded('long-list-preview.pdf');

    expect($s->fresh()->lock_version)->toBe($version)->and($s->fresh()->pdf_paths)->toBeNull();
});

it('gives no preview when the save before it is stale', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])->call('addAthleteChip', $this->m60->id, $this->ali->id);
    $s = Submission::sole();
    $s->claimVersion($s->fresh()->lock_version);

    $page->call('previewPdf')->assertNoFileDownloaded();
    expect(Entry::count())->toBe(0);
});

it('picks a registry athlete into the event from the side panel and adds a new one to the Long List', function () {
    $this->ali->update(['cpr_no' => '040917332']);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->assertSeeHtmlInOrder(['Add athlete', 'Judo · stays on the Long List page', 'Pick from your registry', 'Open registry',
            'Ali Hasan', 'Complete', 'Add', 'Or add a new athlete', 'Same fields and rules as the registry', 'Event', 'Cancel', 'Save as draft', 'Save and add to Long List'])
        ->assertSeeHtml('placeholder="Search by name, code or CPR"')->assertDontSee('Coach Karim')
        ->set('panelSearch', '040917332')->assertSee('Ali Hasan')->assertDontSee('Omar Saleh')
        ->set('panelSearch', '')
        ->call('addAthleteChip', $this->m60->id, $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->set('newPersonEvent', (string) $this->m60->id)
        ->call('createPerson', true)->assertHasNoErrors()->assertSet('panel', null);

    $hamad = Person::where('first_name', 'Hamad')->sole();
    expect($hamad->association_id)->toBe($this->assoc->id)
        ->and(Entry::where('game_event_id', $this->m60->id)->pluck('person_id')->sort()->values()->all())->toBe(collect([$this->ali->id, $hamad->id])->sort()->values()->all());
});

it('needs a matching event to add a new athlete to the Long List', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Huda', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'female',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112299', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->set('newPersonEvent', (string) $this->m60->id)
        ->call('createPerson', true)->assertHasErrors(['newPersonEvent']);

    expect(Person::where('first_name', 'Huda')->exists())->toBeFalse();
});

it('adds a registry official from the side panel', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'official')
        ->assertSee('Add team official')->assertSee('Coach Karim')->assertDontSee('Omar Saleh')
        ->call('addOfficialChip', $this->judo->id, $this->coach->id)
        ->assertSet("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->assertDontSeeHtml('wire:key="pick-'.$this->coach->id.'"');
});

it('gives STA both Long List screens without the side panel', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    actingInAdmin(User::factory()->superAdmin()->create());
    $events = GameResource::getUrl('submission-edit-3-events', ['record' => $this->game, 'submission' => $s]);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertSeeHtml(e($events.'#sport-'.$this->judo->id))->assertDontSee('Add athlete');
    livewire(StaPhase3EventsEdit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertSee('Pick athletes from your registry')->assertSee('People on your Long List')->assertDontSee('Add athlete')
        ->call('addAthleteChip', $this->m60->id, $this->omar->id)->call('save')->assertHasNoErrors();
    $this->get($events)->assertOk();

    expect(Entry::count())->toBe(2);
});

it('counts only the people that get a chip and draws 44px chip and picker targets', function () {
    $other = Association::factory()->create();
    $foreign = Person::factory()->complete()->create(['association_id' => $other->id]);
    Person::withoutGlobalScopes()->whereKey($foreign->id)->update(['association_id' => $other->id]);

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $foreign->id, '999999'])
        ->assertSeeText('Long List 1')->assertSeeText('1 athlete · 0 team officials')
        ->assertSeeHtml('width: 44px; height: 44px; margin: -4px -4px -4px -8px')
        ->assertSeeHtml('min-width: 120px; min-height: 44px');
});

it('keeps a read-only Long List: no × or picker, and chip calls change nothing', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $s->update(['status' => SubmissionStatus::Submitted]);

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->assertSee('Ali Hasan')->assertDontSeeHtml('aria-label="Remove Ali Hasan')->assertDontSeeHtml('role="combobox"')
        ->call('addAthleteChip', $this->m60->id, $this->omar->id)
        ->call('removeAthleteChip', $this->m60->id, $this->ali->id)
        ->call('addOfficialChip', $this->judo->id, $this->coach->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSet("officials.{$this->judo->id}", []);
});

it('lets STA remove and add chips on the events screen', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(StaPhase3EventsEdit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->call('removeAthleteChip', $this->m60->id, $this->ali->id)
        ->call('addOfficialChip', $this->judo->id, $this->coach->id)
        ->assertSet("athletes.{$this->m60->id}", [])->assertSet("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->call('save')->assertHasNoErrors();

    expect(Entry::pluck('person_id')->all())->toBe([$this->coach->id]);
});

it('asks for the sport of a new team official when the panel covers several sports', function () {
    $karate = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Karate'])->id]);
    $official = ['function' => 'Team Manager', 'first_name' => 'Nasser', 'last_name' => 'Ali', 'dob' => '1980-05-01', 'gender' => 'male',
        'nationality' => 'Bahrain', 'phone' => '3644 7712', 'email' => 'n.ali@example.com', 'passport_no' => 'BH9998887',
        'passport_expiry' => now()->addYears(2)->toDateString()];

    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'official')
        ->assertSeeHtmlInOrder(['Sport', 'Judo', 'Karate', 'Mobile'])
        ->set('newPerson', $official)
        ->call('createPerson', true)->assertHasErrors(['newPersonSport'])->assertSee('Choose the sport for this team official.');
    expect(Person::where('first_name', 'Nasser')->exists())->toBeFalse();

    $page->set('newPersonSport', (string) $karate->id)->call('createPerson', true)->assertHasNoErrors()->assertSet('panel', null);
    expect(Entry::where(['person_id' => Person::where('first_name', 'Nasser')->sole()->id, 'game_sport_id' => $karate->id])->exists())->toBeTrue();
});

it('closes the panel and shows the list errors when the new person is saved but the Long List is not', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->coach->id]) // an official in an athlete event: a Long List error
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->set('newPersonEvent', (string) $this->m60->id)
        ->call('createPerson', true)
        ->assertSet('panel', null)->assertHasErrors(["athletes.{$this->m60->id}"])
        ->assertNotified('Saved to the registry, but the Long List was not saved');

    $hamad = Person::where('first_name', 'Hamad')->sole();
    expect(Entry::count())->toBe(0);
    $page->assertSet("athletes.{$this->m60->id}", [(string) $this->coach->id, (string) $hamad->id]);
});

it('deletes the uploaded files when the new person cannot be saved', function () {
    Storage::fake(config('filesystems.private'));
    Person::creating(fn () => throw new RuntimeException('database down'));

    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->set('newPersonPhoto', UploadedFile::fake()->image('me.jpg'));

    expect(fn () => $page->call('createPerson', false))->toThrow(RuntimeException::class);
    expect(Storage::disk(config('filesystems.private'))->allFiles('photos'))->toBe([]);
});

it('names only the gaps the short form can fill, sends the rest to the registry, and views an upload', function () {
    Storage::fake(config('filesystems.private'));
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])->call('openPanel', 'athlete');

    expect($page->instance()->shortFormMissing())->toBe(['Photo', 'Passport scan', 'CPR number', 'CPR scan'])
        ->and($page->instance()->shortFormLeavesRest())->toBeTrue();
    $page->assertSee('Photo, Passport scan, CPR number and CPR scan missing — you can still save as draft. Complete the rest in the registry.')
        ->assertDontSee('Passport issuing country')
        ->set('newPersonPhoto', UploadedFile::fake()->image('me.jpg'))
        ->assertSee('me.jpg')->assertSeeHtml('aria-label="View the photo"')->assertDontSeeHtml('aria-label="Remove the photo"')
        ->call('viewUpload', 'newPersonPhoto')->assertFileDownloaded('me.jpg');
    expect($page->instance()->viewUpload('panel'))->toBeNull();
});
