<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->actingAs(User::factory()->assocAdmin($this->assoc)->create(['name' => 'Fatima Admin']));
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'insurance_company' => 'AXA Gulf', 'insurance_policy_no' => 'POL-777', 'vaccinations' => 'Yellow fever']);
    $this->viewer = User::factory()->sta()->create();
    $this->viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
});

it('shows the submission, its long list and its audit trail', function () {
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])
        ->assertSee('Bahrain Judo Federation')->assertSee('Long List')->assertSee('Ali Hasan')->assertSee('Judo · Men -60kg')
        ->assertSee('AXA Gulf')->assertDontSee('POL-777')->assertDontSee('Yellow fever')->assertSee('Fatima Admin')->assertSee('Entry created');
});

it('shows the policy number only with view_medical', function () {
    $this->viewer->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])->assertSee('POL-777')->assertSee('Yellow fever');
});

it('shows phase 2 numbers', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 3]);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $p2])
        ->assertSee('Entry by Number')->assertSee('Men -60kg')->assertSeeHtml('<td class="py-1">3</td>');
});

it('opens over HTTP for view_submissions and forbids others', function () {
    actingInAdmin($this->viewer);
    $this->get(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))->assertOk();

    actingInAdmin(User::factory()->sta()->create());
    $this->get(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))->assertForbidden();
});

it('returns 404 for a submission of another game', function () {
    actingInAdmin($this->viewer);
    $other = Game::factory()->create();
    $this->get(GameResource::getUrl('submission', ['record' => $other, 'submission' => $this->sub]))->assertNotFound();
});

it('links dashboard cells to the submission and counts entries as in progress, without creating rows', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    actingInAdmin($this->viewer);

    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSeeHtml(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))
        ->assertSee('In progress');
    expect(Submission::withoutGlobalScopes()->count())->toBe(1);
});

it('logs the long list detail in the trail without policy number or vaccinations', function () {
    $a = Activity::where('log_name', 'submission-'.$this->sub->id)->where('subject_type', LongListDetail::class)->sole();
    expect($a->properties['attributes'])->toHaveKey('insurance_company')
        ->and(json_encode($a->properties))->not->toContain('insurance_policy_no')->not->toContain('POL-777')
        ->not->toContain('vaccinations')->not->toContain('Yellow fever');
});

it('shows phase 2 participants and expected medals', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    $p2->sportTotals()->create(['game_sport_id' => $this->m60->game_sport_id, 'male' => 7, 'female' => 5, 'silver' => 2]);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $p2])
        ->assertSee('Participants and expected medals')->assertSeeHtml('<td class="py-1">7</td>')->assertSeeHtml('<td class="py-1">5</td>');
});
