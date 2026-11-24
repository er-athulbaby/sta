<?php

use App\Enums\AppliesTo;
use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Filament\Portal\Pages\Phase3Events;
use App\Filament\Resources\Games\Pages\StaPhase3EventsEdit;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\LongListChecks;
use App\Services\Reports\Builder\Subjects\People;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create(['name' => 'Gulf Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->w52 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Women -52kg', 'gender' => EventGender::Women])->id]);
    $this->shirt = ClothingItem::factory()->create(['name' => 'Shirt', 'applies_to' => AppliesTo::Both]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->coach = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach',
        'last_name' => 'Karim', 'function' => 'Head Coach']);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
});

it('calls the registry Function a Position', function () {
    expect(Person::REQUIRED_FOR_COMPLETE['official']['function'])->toBe('Position')
        ->and(collect((new People)->fields())->firstWhere('key', 'function')?->label)->toBe('Position');

    livewire(Phase3Entry::class, ['game' => $this->game->id])->call('openPanel', 'official')
        ->assertSee('Position')->assertSeeHtml('placeholder="Search by name, code, CPR or passport"');
});

it('opens the pop-up from Add with the athlete\'s events only and puts them on the list with the passport confirmed', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openAdd', $this->ali->id)
        ->assertSee('Add Ali Hasan to Gulf Games')->assertSee('Men -60kg')->assertDontSee('Women -52kg')
        ->assertSee('The passport shown is the latest one')->assertSee('Upload latest passport')
        ->assertSet('adding.target', (string) $this->m60->id)
        ->call('confirmAdd')->assertHasErrors(['adding.confirmed'])
        ->set('adding.target', (string) $this->w52->id)->set('adding.confirmed', true)
        ->call('confirmAdd')->assertHasErrors(['adding.target']);
    expect(Entry::count())->toBe(0);

    $page->set('adding.target', (string) $this->m60->id)->set("adding.sizes.{$this->shirt->id}", 'L')
        ->call('confirmAdd')->assertHasNoErrors()->assertSet('adding', null);

    $entry = Entry::sole();
    expect($entry->game_event_id)->toBe($this->m60->id)->and($entry->function)->toBeNull()
        ->and($entry->passport_confirmed_at)->not->toBeNull()->and($entry->passport_confirmed_by)->toBe(auth()->id())
        ->and($this->ali->fresh()->sizes->sole()->size)->toBe('L');
});

it('stores the official\'s function in this game and shows it on the lists and the PDF', function () {
    $page = livewire(Phase3Events::class, ['game' => $this->game->id])
        ->call('openAdd', $this->coach->id, $this->judo->id)
        ->assertSee('Function in this game')->assertSet('adding.function', 'Head Coach')
        ->set('adding.function', 'Team Doctor')->set('adding.confirmed', true)
        ->call('confirmAdd')->assertHasNoErrors();

    $s = Submission::for($this->game, $this->assoc, 3);
    expect(Entry::where(['person_id' => $this->coach->id, 'game_event_id' => null])->sole()->function)->toBe('Team Doctor')
        ->and($this->coach->fresh()->function)->toBe('Head Coach') // the registry position is unchanged
        ->and(LongListChecks::officialFunctions($s))->toBe([$this->judo->id => [$this->coach->id => 'Team Doctor']])
        ->and(LongListChecks::officialFunctions(Submission::for($this->game, $this->assoc, 4)))->toBe([$this->judo->id => [$this->coach->id => 'Team Doctor']]); // the Short List reads its Long List
    $page->assertSee('Team official · Team Doctor');
    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('Team Doctor');
    $row = app(LongListChecks::class)->rows($s, ...app(LongListChecks::class)->selection($s))->sole();
    expect($row['function'])->toBe('Team Doctor')->and($row['roles'])->toBe(['Judo · team official · Team Doctor']);

    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });
    (new GenerateLongListPdf($s))->output();
    expect($captured['officials'][0]['function'])->toBe('Team Doctor');
});

it('falls back to the registry position for officials added before the pop-up', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);

    expect(LongListChecks::officialFunction(LongListChecks::officialFunctions($s), $this->coach))->toBe('Head Coach');
    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('Head Coach');
});

it('replaces the main passport scan with the latest upload and updates the passport details', function () {
    $this->ali->update(['passport_scan_extra' => ['scans/page2.pdf']]);
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openAdd', $this->ali->id)
        ->set('addingPassportScan', UploadedFile::fake()->create('new-passport.pdf', 100, 'application/pdf'))
        ->assertSee('new-passport.pdf')
        ->set('adding.passport_no', 'BH7654321')->set('adding.passport_expiry', now()->addYears(5)->toDateString())
        ->set('adding.confirmed', true)->call('confirmAdd')->assertHasNoErrors();

    $ali = $this->ali->fresh();
    expect($ali->passport_scan_path)->toBe('scans/'.$ali->code.'_passport.pdf')->and($ali->passport_scan_extra)->toBe(['scans/page2.pdf'])
        ->and($ali->passport_no)->toBe('BH7654321')->and($ali->passport_expiry->toDateString())->toBe(now()->addYears(5)->toDateString());
    Storage::disk(config('filesystems.private'))->assertExists($ali->passport_scan_path);
    expect(Entry::sole()->person_id)->toBe($ali->id);
});

it('refuses inactive people', function () {
    $this->ali->update(['is_active' => false]);
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('openAdd', $this->ali->id)->assertSet('adding', null)->assertNotified('Ali Hasan is inactive.');

    $this->ali->update(['is_active' => true]);
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])->call('openAdd', $this->ali->id);
    $this->ali->update(['is_active' => false]);
    $page->set('adding.confirmed', true)->call('confirmAdd')->assertHasErrors(['adding.person']);
    expect(Entry::count())->toBe(0);
});

it('never opens the pop-up for another association\'s person', function () {
    $other = Person::factory()->complete()->create();
    $other->forceFill(['association_id' => Association::factory()->create()->id])->saveQuietly(); // the portal tenant would claim it on create

    livewire(Phase3Entry::class, ['game' => $this->game->id])->call('openAdd', $other->id)->assertSet('adding', null);
});

it('uses the same pop-up when STA edits the Long List', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(StaPhase3EventsEdit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->call('openAdd', $this->coach->id, $this->judo->id)->assertSee('Add Coach Karim to Gulf Games')
        ->set('adding.function', 'Team Manager')->set('adding.confirmed', true)
        ->call('confirmAdd')->assertHasNoErrors();

    expect(Entry::sole()->function)->toBe('Team Manager');
});
