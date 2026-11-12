<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase4Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
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
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1, 'p4_max_officials' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $this->omar] as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }
    $p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $p3->update(['status' => SubmissionStatus::Submitted]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
});

it('counts ticked people per sport', function () {
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id]);

    expect($page->instance()->tickedCounts($this->judo->fresh('gameEvents')))->toBe(['male' => 2, 'female' => 0, 'officials' => 1]);
});

it('shows meters, the violet justification card when over, and the all-clear when within', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSee('Judo · ticked vs maximum')->assertSee('2 / max 1')->assertSeeHtml('color: var(--sta-fg-orange-800, #9A3412)">2 / max 1')
        ->assertSee('Justification needed')->assertSee('Over a maximum')->assertSee('Over the maximum: Judo — Male 2 / max 1')
        ->assertSeeHtml('border: 2px solid var(--sta-bd-violet-700, #6D28D9)')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSee('Within all maximums — no justification needed.')->assertSee('Within maximums');
});

it('shows only the green status within the maximums, and a saved justification so it can be cleared', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSee('Within all maximums — no justification needed.')->assertDontSeeHtml('id="justification"');

    Submission::where(['game_id' => $this->game->id, 'phase' => 4])->update(['justification' => 'Both can medal']);

    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSeeHtml('id="justification"')->assertSee('Why do you need more than the maximum?')
        ->assertDontSee('(required to submit)')->assertDontSee('Justification needed')
        ->set('justification', '')->call('save')->assertHasNoErrors();

    expect(Submission::where(['game_id' => $this->game->id, 'phase' => 4])->sole()->justification)->toBeNull();
});

it('streams a preview of the Short List PDF', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->call('previewPdf')->assertFileDownloaded('short-list-preview.pdf');
});

it('draws the ShortList canvas sections in order', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSeeInOrder(['Short List', 'Phase 4', 'Choose from your submitted Long List', 'Preview PDF', 'Save as draft',
            'Everyone on your Long List is shown per sport', 'Judo', 'Ticked: 2 male, 0 female, 1 team officials', 'Over a maximum',
            'Athletes', 'On Short List', 'Gender', 'Event', 'Passport', 'Team officials', 'Function',
            'Judo · ticked vs maximum', 'Updates as you tick', 'Male', 'Female', 'Team officials', 'Justification needed',
            'Why do you need more than the maximum? (required to submit)', 'STA sees this on the Short List PDF and decides.']);
});
