<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase4Entry;
use App\Jobs\GenerateLongListPdf;
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
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->p4 = Submission::for($this->game, $this->assoc, 4); // no submitted Long List yet: nothing preloaded
    $this->p4->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $this->p4->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P4-R1', 'revision' => 1,
        'justification' => 'Two medal chances']);
});

it('stores the Short List PDF under its own key and file name', function () {
    (new GenerateShortListPdf($this->p4))->handle();

    $path = 'submissions/BJF-G001-P4-R1/short-list.pdf';
    expect($this->p4->fresh()->pdf_paths)->toBe(['short_list' => $path])
        ->and(substr(Storage::disk(config('filesystems.private'))->get($path), 0, 4))->toBe('%PDF');
});

it('titles it Short List and passes the justification and the Long List columns', function () {
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateShortListPdf($this->p4))->handle();

    expect($captured['title'])->toBe('Short List')->and($captured['justification'])->toBe('Two medal chances')
        ->and(collect($captured['athletes'])->pluck('name')->all())->toBe(['Ali Hasan']);
});

it('keeps the Long List PDF titled Long List', function () {
    $p3 = Submission::for($this->game, $this->assoc, 3);
    $p3->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateLongListPdf($p3))->handle();

    expect($captured['title'])->toBe('Long List')->and($p3->fresh()->pdf_paths)->toBe(['long_list' => 'submissions/BJF-G001-P3-R1/long-list.pdf']);
});

it('prints the title and the justification', function () {
    $html = view('pdf.long-list', [
        'game' => $this->game, 'association' => $this->assoc, 'athleteItems' => [], 'athletes' => [], 'officials' => [], 'officialItems' => [],
        'referenceNo' => 'BJF-G001-P4-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null,
        'title' => 'Short List', 'justification' => 'Two medal chances', 'summary' => ['male' => 3, 'female' => 2, 'officials' => 1],
    ])->render();

    expect($html)->toContain('Short List')->toContain('Justification')->toContain('Two medal chances')->toContain('Athletes Male <strong>3</strong>')->toContain('Athletes Female <strong>2</strong>')->toContain('Athletes Total <strong>5</strong>')->not->toContain('Long List');
});

it('is dispatched when a Short List is finalised', function () {
    Bus::fake([GenerateShortListPdf::class, GenerateLongListPdf::class]);
    Notification::fake();
    Submission::for($this->game, $this->assoc, 3)->update(['status' => SubmissionStatus::Submitted]);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->p4->entries()->delete();
    $this->p4->update(['status' => SubmissionStatus::Draft]);

    app(SubmissionWorkflow::class)->submit($this->p4->fresh(), User::factory()->assocAdmin($this->assoc)->create());

    Bus::assertDispatched(GenerateShortListPdf::class);
    Bus::assertNotDispatched(GenerateLongListPdf::class);
});

it('offers the download on the phase 4 page once submitted', function () {
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->p4->update(['pdf_paths' => ['short_list' => 'submissions/BJF-G001-P4-R1/short-list.pdf']]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Phase4Entry::class, ['game' => $this->game->id])->assertSee('View Short List PDF');
});
