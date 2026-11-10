<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\StaPhase4Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Jobs\GenerateShortListPdf;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\ShortListChecks;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);
    $p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $this->omar] as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }
    $p3->update(['status' => SubmissionStatus::Submitted]);
    $this->p4 = Submission::for($this->game, $this->assoc, 4);
    app(ShortListChecks::class)->preload($this->p4); // the copy of the Long List happens on first open (Ali and Omar)
    $this->p4->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P4-R1', 'revision' => 1, 'justification' => 'Both can medal']);
    $this->sta = User::factory()->sta()->create();
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    actingInAdmin($this->sta);
});

it('shows the Short List with its over-limit lines and the justification', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);

    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->assertSee('Short List')->assertSee('Ali Hasan')->assertSee('Omar Saleh')
        ->assertSee('Judo: 2 men (limit 1)')->assertSee('Both can medal')->assertActionHidden('edit');
});

it('lets STA edit a submitted Short List and its justification after the deadline, audited, and regenerates the PDF', function () {
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])->assertActionVisible('edit');

    livewire(StaPhase4Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->assertDontSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->set('justification', 'Updated by STA')
        ->call('save')->assertHasNoErrors();

    $s = $this->p4->fresh();
    expect($s->entries()->pluck('person_id')->all())->toBe([$this->ali->id])
        ->and($s->status)->toBe(SubmissionStatus::Submitted)->and($s->reference_no)->toBe('BJF-G001-P4-R1')
        ->and($s->justification)->toBe('Updated by STA')
        ->and(Activity::where('log_name', 'submission-'.$s->id)->where('causer_id', $this->sta->id)->exists())->toBeTrue();
    Bus::assertDispatched(GenerateShortListPdf::class);
});

it('keeps the Long List rule for STA', function () {
    livewire(StaPhase4Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->set("athletes.{$this->m60->id}", [(string) $this->yusuf->id])->call('save')->assertHasErrors(["athletes.{$this->m60->id}"]);

    expect($this->p4->entries()->count())->toBe(2);
    Bus::assertNotDispatched(GenerateShortListPdf::class);
});

it('needs edit_submissions for the STA Short List page', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('submission-edit-4', ['record' => $this->game, 'submission' => $this->p4]))->assertForbidden();

    actingInAdmin($this->sta);
    $this->get(GameResource::getUrl('submission-edit-4', ['record' => $this->game, 'submission' => $this->p4]))->assertOk();
});

it('warns on the submission view when a submitted Short List no longer matches the Long List', function () {
    Submission::where(['game_id' => $this->game->id, 'phase' => 3])->update(['status' => SubmissionStatus::Draft]);

    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->assertSee('Short List needs attention')->assertSee(ShortListChecks::LONG_LIST_FIRST);
});
