<?php

use App\Enums\EventGender;
use App\Enums\InterestAnswerValue;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Filament\Resources\Games\Pages\StaPhase2Edit;
use App\Filament\Resources\Games\Pages\StaPhase3Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    foreach ([1, 2, 3] as $n) {
        $this->game->phase($n)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    }
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 1,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->sta = User::factory()->sta()->create(['name' => 'Sta Editor']);
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    actingInAdmin($this->sta);
});

it('lets STA edit a submitted long list after the deadline, audited, and regenerates its PDF', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertDontSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertHasNoErrors();

    expect(Entry::sole()->person_id)->toBe($this->ali->id)
        ->and($s->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($s->fresh()->reference_no)->toBe('BJF-G001-P3-R1')
        ->and(Activity::where('log_name', 'submission-'.$s->id)->where('subject_type', Entry::class)->sole()->causer_id)->toBe($this->sta->id);
    Bus::assertDispatched(GenerateLongListPdf::class);
});

it('keeps the long list hard errors for STA', function () {
    $official = Person::factory()->official()->create(['association_id' => $this->assoc->id]);
    $s = Submission::for($this->game, $this->assoc, 3);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->set("athletes.{$this->m60->id}", [(string) $official->id])->call('save')->assertHasErrors(["athletes.{$this->m60->id}"]);
    expect(Entry::count())->toBe(0);
});

it('keeps phase 2 limits for STA and regenerates the Entry by Event PDFs', function () {
    $s = Submission::for($this->game, $this->assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P2-R1', 'revision' => 1]);
    $page = livewire(StaPhase2Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s]);

    $page->set("counts.{$this->m60->id}", 2)->call('save')->assertHasErrors(["counts.{$this->m60->id}"]);
    Bus::assertNotDispatched(GenerateEntryByEventPdfs::class);

    $page->set("counts.{$this->m60->id}", 1)->call('save')->assertHasNoErrors();
    expect(NumberEntry::sole()->count)->toBe(1);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});

it('lets STA answer phase 1 without changing the status', function () {
    $s = Submission::for($this->game, $this->assoc, 1);

    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->set("answers.{$this->judo->id}", 'yes')->call('save')->assertHasNoErrors();

    expect(InterestAnswer::sole()->answer)->toBe(InterestAnswerValue::Yes)->and($s->fresh()->status)->toBe(SubmissionStatus::Draft);
});

it('refuses a stale STA save', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $page = livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s]);
    $fresh = $s->fresh(); // a just-created model has no lock_version attribute loaded
    $fresh->claimVersion($fresh->lock_version);

    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(Entry::count())->toBe(0);
});

it('refuses STA without edit_submissions, another game and the wrong phase page', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $this->game, 'submission' => $s]))->assertForbidden();
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])->assertActionHidden('edit');

    actingInAdmin($this->sta);
    $other = Game::factory()->create();
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $other, 'submission' => $s]))->assertNotFound();
    $this->get(GameResource::getUrl('submission-edit-2', ['record' => $this->game, 'submission' => $s]))->assertNotFound();
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $this->game, 'submission' => $s]))->assertOk();
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])->assertActionVisible('edit');
});

it('never lets STA without view_medical read or overwrite a policy number', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    LongListDetail::create(['submission_id' => $s->id, 'person_id' => $this->ali->id, 'insurance_policy_no' => 'POL-9']);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->callAction('travel', ['insurance_company' => 'X', 'insurance_policy_no' => 'HACK'], ['person' => $this->ali->id])->assertHasNoActionErrors();

    $d = LongListDetail::where('submission_id', $s->id)->sole();
    expect($d->insurance_policy_no)->toBe('POL-9')->and($d->insurance_company)->toBe('X');
});

it('has no submit workflow on STA pages', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $page = livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id]);
    try {
        $page->callAction('submit');
    } catch (Throwable) {
    }
    expect(Entry::count())->toBe(0)->and($s->fresh()->status)->toBe(SubmissionStatus::Draft);
});

it('is read-only for STA when the phase is disabled', function () {
    $this->game->phase(3)->update(['enabled' => false]);
    $s = Submission::for($this->game, $this->assoc, 3);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertSee('This phase is not used for this game.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');
    expect(Entry::count())->toBe(0);
});
