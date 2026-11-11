<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase3Entry;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

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
    livewire(Phase3Entry::class, ['game' => $this->game->id])
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
        ->assertSee('People on your Long List')->assertSee('2 male · 0 female · 0 team officials')
        ->assertSee('Long List 2 / Phase 2: 1')->assertSee('Over the Phase 2 number');
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
