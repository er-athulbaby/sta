<?php

use App\Enums\AppliesTo;
use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Filament\Portal\Pages\Phase3Events;
use App\Filament\Portal\Resources\People\PersonResource;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
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
    $this->sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    $this->coach = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    actingInPortal($this->admin);
});

it('offers athletes of the event gender and the officials of the association', function () {
    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->assertSee('Men -60kg')->assertSee('Ali Hasan')->assertSee('Coach Karim')->assertDontSee('Sara Ali');
});

it('saves the list and syncs removals, dropping travel details of removed people', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->set("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(3)
        ->and(Entry::where('person_id', $this->coach->id)->sole()->game_event_id)->toBeNull();

    LongListDetail::create(['submission_id' => Submission::sole()->id, 'person_id' => $this->omar->id, 'insurance_company' => 'AXA']);
    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(2)
        ->and(Entry::where('person_id', $this->omar->id)->exists())->toBeFalse()
        ->and(LongListDetail::count())->toBe(0);
});

it('blocks a gender mismatch and writes nothing', function () {
    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->sara->id])->call('save')
        ->assertHasErrors(["athletes.{$this->m60->id}"])->assertSee('Sara Ali (Female) cannot enter a Male event.');
    expect(Entry::count())->toBe(0);
});

it('flags an event over its submitted phase 2 number without blocking', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    $p2->update(['status' => SubmissionStatus::Submitted]);

    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSeeText('Long List 2 / Phase 2: 1')->assertSee('Over the Phase 2 number')
        ->call('save')->assertHasNoErrors();
    expect(Entry::count())->toBe(2);
});

it('shows completeness and warnings per person', function () {
    $yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $yusuf->id])
        ->assertSee('Incomplete')->assertSee('Missing: Full name')->assertSee('Passport scan missing.');
});

it('saves travel and insurance details with a certificate named after the person', function () {
    Storage::fake(config('filesystems.private'));
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');

    $page->callAction('travel', [
        'insurance_company' => 'AXA Gulf', 'insurance_policy_no' => 'POL-1', 'insurance_valid_until' => '2027-04-01',
        'insurance_certificate_path' => UploadedFile::fake()->image('cert.jpg'), 'vaccinations' => 'Yellow fever',
    ], ['person' => $this->ali->id])->assertHasNoActionErrors();

    $d = LongListDetail::sole();
    $path = 'submissions/'.Submission::sole()->id.'/'.$this->ali->code.'_insurance.jpg';
    expect($d->insurance_company)->toBe('AXA Gulf')->and($d->insurance_policy_no)->toBe('POL-1')
        ->and($d->insurance_valid_until->toDateString())->toBe('2027-04-01')->and($d->insurance_certificate_path)->toBe($path);
    Storage::disk(config('filesystems.private'))->assertExists($path);
    $page->assertSee('View certificate');
});

it('refuses travel details for a person not on the saved list', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->callAction('travel', ['insurance_company' => 'AXA'], ['person' => $this->ali->id])
        ->assertNotified('Save the Long List with this person first.');
    expect(LongListDetail::count())->toBe(0);
});

it('refuses a stale save', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id]);
    $s = Submission::sole();
    $s->claimVersion($s->lock_version);
    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(Entry::count())->toBe(0);
});

it('is read-only when the phase is closed', function () {
    $this->game->phase(3)->update(['status' => PhaseStatus::Closed]);
    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');
    expect(Entry::count())->toBe(0);
});

it('saves an incomplete person but blocks submit', function () {
    $yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $yusuf->id])->callAction('submit')->assertNotified('Yusuf Adel is incomplete: '.implode(', ', $yusuf->missingFields()).'.');

    expect(Entry::count())->toBe(1)->and(Submission::sole()->status)->toBe(SubmissionStatus::Draft);
});

it('returns 404 for a game the association is not invited to', function () {
    $other = Game::factory()->create();
    $this->get(Phase3Entry::getUrl(['game' => $other->id], tenant: $this->assoc))->assertNotFound();
});

it('links phase 3 from the games page', function () {
    livewire(Games::class)->assertSeeHtml(Phase3Entry::getUrl(['game' => $this->game->id]));
});

it('drops entries and travel details of a sport hidden after it was saved', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');
    LongListDetail::create(['submission_id' => Submission::sole()->id, 'person_id' => $this->ali->id, 'insurance_company' => 'AXA']);
    expect(Entry::count())->toBe(1);

    $this->game->phase(1)->update(['enabled' => true]);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $this->assoc, 1)->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    livewire(Phase3Entry::class, ['game' => $this->game->id])->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(0)->and(LongListDetail::count())->toBe(0);
});

it('refuses a stale travel save', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');
    $s = Submission::sole();
    $s->claimVersion($s->fresh()->lock_version);

    $page->callAction('travel', ['insurance_company' => 'AXA'], ['person' => $this->ali->id])
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(LongListDetail::count())->toBe(0);
});

it('adds an athlete from the Long List and offers them in the picker straight away', function () {
    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete', $this->judo->id)
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->call('createPerson', false)->assertHasNoErrors()
        ->assertSet('panel', null)->assertSee('Hamad Isa');

    $p = Person::where('first_name', 'Hamad')->sole();
    expect($p->association_id)->toBe($this->assoc->id)->and($p->type)->toBe(PersonType::Athlete)->and($p->code)->toStartWith('BJF-AT-');
});

it('adds a team official with the official form', function () {
    livewire(Phase3Events::class, ['game' => $this->game->id])
        ->call('openPanel', 'official', $this->judo->id)
        ->set('newPerson', ['first_name' => 'Nasser', 'last_name' => 'Ali', 'dob' => '1980-05-01', 'gender' => 'male', 'function' => 'Team Manager',
            'nationality' => 'Bahrain', 'passport_no' => 'BH9998887', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->call('createPerson', false)->assertHasNoErrors()->assertSee('Nasser Ali')->assertSee('Team Manager');

    expect(Person::where('first_name', 'Nasser')->sole()->type)->toBe(PersonType::Official);
});

it('keeps an added person in the tenant association whatever the form sends', function () {
    $other = Association::factory()->create();
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])->set('newPerson.association_id', $other->id)
        ->call('createPerson', false)->assertHasNoErrors();

    expect(Person::withoutGlobalScopes()->where('first_name', 'Hamad')->sole()->association_id)->toBe($this->assoc->id);
});

it('offers List and Grid views and a link to the registry', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('longListView.'.$this->admin->id)
        ->assertSeeHtml("x-on:click=\"view = 'list'\"")->assertSeeHtml("x-on:click=\"view = 'grid'\"")
        ->assertSeeHtml("x-if=\"view === 'grid'\"")
        ->call('openPanel', 'athlete')
        ->assertSee('Pick from your registry')->assertSee('Open registry')->assertSeeHtml(PersonResource::getUrl('index'));
});

it('hides the add buttons while the Long List is read-only', function () {
    $this->game->phase(3)->update(['deadline' => now()->subDay()]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->assertDontSeeHtml("openPanel('athlete')")->assertDontSeeHtml("openPanel('official')")
        ->call('openPanel', 'athlete')->assertSet('panel', null);
});

it('labels the travel modal button Save as draft', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->mountAction('travel', ['person' => $this->ali->id])->assertMountedActionModalSee('Save as draft');
});

it('creates an athlete from Add athlete even if the form sends another type', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])->set('newPerson.type', 'official')->set('newPerson.function', 'Team Manager')
        ->call('createPerson', false)->assertHasNoErrors();

    expect(Person::where('first_name', 'Hamad')->sole()->type)->toBe(PersonType::Athlete);
});

it('lazy-loads grid photos', function () {
    expect(file_get_contents(resource_path('views/filament/portal/pages/phase3-entry.blade.php')))->toContain('<img loading="lazy"');
});

it('draws the canvas short form and saves an athlete with sizes and uploads', function () {
    Storage::fake(config('filesystems.private'));
    $item = ClothingItem::create(['name' => 'T-Shirt', 'sizes' => ['S', 'M'], 'applies_to' => AppliesTo::Both, 'is_active' => true]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->assertSeeHtmlInOrder(['First name', 'Family name', 'Gender', 'Date of birth', 'Nationality', 'Event', 'Passport number', 'Passport expiry',
            'CPR number', 'Photo and scans', 'Upload photo', 'Upload passport scan', 'Upload CPR scan (front and back)', 'T-Shirt'])
        ->assertDontSee('Function')->assertSee('missing — you can still save as draft.')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male', 'nationality' => 'Bahrain',
            'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString(), 'sizes' => [$item->id => 'M']])
        ->set('newPersonPhoto', UploadedFile::fake()->image('me.jpg'))
        ->assertSee('me.jpg')
        ->call('createPerson', false)->assertHasNoErrors()->assertSet('panel', null);

    $p = Person::where('first_name', 'Hamad')->sole();
    expect($p->type)->toBe(PersonType::Athlete)->and($p->association_id)->toBe($this->assoc->id)->and($p->is_active)->toBeTrue()
        ->and($p->photo_path)->toBe('photos/'.$p->code.'_photo.jpg')->and($p->sizes->sole()->size)->toBe('M');
    Storage::disk(config('filesystems.private'))->assertExists($p->photo_path);
});

it('saves a team official from the short form', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'official')
        ->assertSeeHtmlInOrder(['Function', 'First name', 'Family name', 'Gender', 'Date of birth', 'Nationality', 'Mobile', 'Email', 'Passport number'])
        ->set('newPerson', ['function' => 'Team Manager', 'first_name' => 'Nasser', 'last_name' => 'Ali', 'dob' => '1980-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'phone' => '3644 7712', 'email' => 'n.ali@example.com', 'passport_no' => 'BH9998887',
            'passport_expiry' => now()->addYears(2)->toDateString()])
        ->call('createPerson', true)->assertHasNoErrors();

    $p = Person::where('first_name', 'Nasser')->sole();
    expect($p->type)->toBe(PersonType::Official)->and($p->function)->toBe('Team Manager')->and($p->phone)->toBe('3644 7712')
        ->and(Entry::where(['person_id' => $p->id, 'game_event_id' => null])->exists())->toBeTrue();
});

it('validates the short form with the registry rules', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'official')
        ->set('newPerson', ['function' => 'Wizard', 'first_name' => '', 'gender' => 'x', 'dob' => now()->addDay()->toDateString(), 'nationality' => 'Atlantis',
            'phone' => 'abc', 'email' => 'not-an-email', 'cpr_no' => '123456789'])
        ->call('createPerson', false)
        ->assertHasErrors(['newPerson.function', 'newPerson.first_name', 'newPerson.last_name', 'newPerson.gender', 'newPerson.dob',
            'newPerson.nationality', 'newPerson.phone', 'newPerson.email', 'newPerson.passport_no', 'newPerson.passport_expiry', 'newPerson.cpr_expiry'])
        ->assertSee('CPR expiry');

    expect(Person::where('type', PersonType::Official)->count())->toBe(1); // only Coach Karim
});

it('refuses a passport or CPR number already in the registry', function () {
    $this->ali->update(['cpr_no' => '040917332']);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openPanel', 'athlete')
        ->set('newPerson', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male', 'nationality' => 'Bahrain',
            'passport_no' => $this->ali->passport_no, 'passport_expiry' => now()->addYears(2)->toDateString(), 'cpr_no' => '040917332', 'cpr_expiry' => '2030-01-01'])
        ->call('createPerson', false)
        ->assertHasErrors(['newPerson.passport_no', 'newPerson.cpr_no'])->assertSee('This passport number is already registered in the system.');

    expect(Person::where('first_name', 'Hamad')->exists())->toBeFalse();
});

it('lists what the entered person still misses in the Incomplete banner', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])->call('openPanel', 'athlete')
        ->set('newPerson.cpr_no', '040917333');

    expect($page->instance()->shortFormMissing())->toContain('Photo', 'Passport scan', 'CPR scan')->not->toContain('CPR number');
    $page->assertSee('Incomplete')->assertSeeText('Passport scan')->assertSee('missing — you can still save as draft.');
});
