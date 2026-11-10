<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Jobs\GenerateEntryBySportPdf;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BWF', 'name' => 'Bahrain Weightlifting Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $mk = fn ($name) => GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => $name])->id]);
    $this->archery = $mk('Archery');
    $this->boxing = $mk('Boxing');
    $this->judo = $mk('Judo');
    $this->p1 = Submission::for($this->game, $this->assoc, 1);
    InterestAnswer::create(['submission_id' => $this->p1->id, 'game_sport_id' => $this->archery->id, 'answer' => 'yes']);
    InterestAnswer::create(['submission_id' => $this->p1->id, 'game_sport_id' => $this->boxing->id, 'answer' => 'maybe']);
    $this->p1->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P1-R1', 'revision' => 1]);
});

it('stores the Entry by Sport PDF with its path key', function () {
    (new GenerateEntryBySportPdf($this->p1))->handle();

    $path = 'submissions/BWF-G001-P1-R1/entry-by-sport.pdf';
    expect($this->p1->fresh()->pdf_paths)->toBe(['entry_by_sport' => $path])
        ->and(substr(Storage::disk(config('filesystems.private'))->get($path), 0, 4))->toBe('%PDF');
});

it('lists every sport with its answer, No response for unanswered, plus reference and declaration', function () {
    $captured = null;
    view()->composer('pdf.entry-by-sport', function ($v) use (&$captured) {
        $captured ??= $v->getData();
    });

    (new GenerateEntryBySportPdf($this->p1))->handle();

    expect(collect($captured['rows'])->map(fn ($r) => $r['sport'].':'.$r['answer'])->all())
        ->toBe(['Archery:Yes', 'Boxing:Maybe', 'Judo:No response']);
    $html = view('pdf.entry-by-sport', $captured)->render();

    expect($html)->toContain('Entry by Sport')->toContain('Bahrain Weightlifting Federation')->toContain('BWF-G001-P1-R1')
        ->toContain(Setting::DEFAULT_DECLARATION)->toContain('Stamp')->toContain('No response');
});

it('is dispatched for phase 1 finalise only', function () {
    Bus::fake();
    Notification::fake();
    $this->p1->update(['status' => SubmissionStatus::Draft]);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    app(SubmissionWorkflow::class)->submit($this->p1->fresh(), User::factory()->assocAdmin($this->assoc)->create());
    Bus::assertDispatched(GenerateEntryBySportPdf::class);

    Bus::fake();
    $p2 = Submission::for($this->game, $this->assoc, 2);
    $this->game->gameSports->each(fn ($gs) => $p2->sportTotals()->create(['game_sport_id' => $gs->id, 'male' => 0, 'female' => 0]));
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    app(SubmissionWorkflow::class)->submit($p2->fresh(), User::factory()->assocAdmin($this->assoc)->create());
    Bus::assertNotDispatched(GenerateEntryBySportPdf::class);
});

it('shows the download link on the Phase 1 page only when submitted with the key', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertDontSee('Download Entry by Sport PDF');

    $this->p1->update(['pdf_paths' => ['entry_by_sport' => 'submissions/BWF-G001-P1-R1/entry-by-sport.pdf'], 'status' => SubmissionStatus::Draft]);
    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertDontSee('Download Entry by Sport PDF');

    $this->p1->update(['status' => SubmissionStatus::Submitted]);
    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertSee('Download Entry by Sport PDF');
});

it('is regenerated after an STA save of a submitted Phase 1', function () {
    Bus::fake();
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    actingInAdmin($sta);

    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p1])
        ->set("answers.{$this->judo->id}", 'yes')->call('save')->assertHasNoErrors();

    Bus::assertDispatched(GenerateEntryBySportPdf::class);
});
