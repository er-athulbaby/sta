<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Sport;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

it('stores one Entry by Event PDF per sport with the reference number', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'entry_policy' => 'Max 1 per category',
        'sport_id' => Sport::factory()->create(['association_id' => $assoc->id, 'name' => 'Weightlifting'])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => 'Men 61kg', 'gender' => EventGender::Men])->id]);
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);
    NumberEntry::create(['submission_id' => $s->id, 'game_event_id' => $ge->id, 'count' => 1]);

    (new GenerateEntryByEventPdfs($s))->handle();

    $path = $s->fresh()->pdf_paths[$gs->id];
    expect($path)->toBe("submissions/BWF-G001-P2-R1/{$gs->id}-weightlifting.pdf");
    $pdf = Storage::disk(config('filesystems.private'))->get($path);
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});

it('skips sports answered no in Phase 1', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open]);
    $mk = fn ($name) => GameSport::factory()->create(['game_id' => $game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $assoc->id, 'name' => $name])->id]);
    $yes = $mk('Archery');
    $no = $mk('Boxing');
    $p1 = Submission::for($game, $assoc, 1);
    InterestAnswer::create(['submission_id' => $p1->id, 'game_sport_id' => $yes->id, 'answer' => 'yes']);
    InterestAnswer::create(['submission_id' => $p1->id, 'game_sport_id' => $no->id, 'answer' => 'no']);
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);

    (new GenerateEntryByEventPdfs($s))->handle();

    expect(array_keys($s->fresh()->pdf_paths))->toBe([$yes->id]);
    Storage::disk(config('filesystems.private'))->assertMissing("submissions/BWF-G001-P2-R1/{$no->id}-boxing.pdf");
});

it('renders the expected content', function () {
    $html = view('pdf.entry-by-event', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']), 'association' => Association::factory()->make(['name' => 'Bahrain Weightlifting Federation']),
        'gameSport' => GameSport::factory()->make(['entry_policy' => 'Max 1 per category']), 'sportName' => 'Weightlifting',
        'rows' => [['event' => 'Men 61kg', 'gender' => 'men', 'count' => 1], ['event' => 'Team', 'gender' => 'mixed', 'count' => 2]],
        'totals' => ['men' => 1, 'women' => 0, 'total' => 3],
        'participants' => ['male' => 3, 'female' => 0], 'medals' => ['gold' => 1, 'silver' => null, 'bronze' => 2],
        'referenceNo' => 'BWF-G001-P2-R1', 'declaration' => 'I confirm.', 'nocLogo' => 'data:image/png;base64,AA==', 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Entry by Event')->toContain('Men 61kg')->toContain('Max 1 per category')
        ->toContain('BWF-G001-P2-R1')->toContain('I confirm.')->toContain('Stamp')->toContain('(mixed)')->toContain('All events (incl. mixed)')->toContain('max-height:60px')
        ->toContain('Total number of participants')->toContain('Expected medals')->toContain('Gold')->toContain('Bronze');
});

it('passes the sport participants and medals to the view', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $assoc->id])->id]);
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);
    $s->sportTotals()->create(['game_sport_id' => $gs->id, 'male' => 4, 'female' => 2, 'gold' => 1]);
    $captured = null;
    view()->composer('pdf.entry-by-event', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateEntryByEventPdfs($s))->handle();

    expect($captured['participants'])->toBe(['male' => 4, 'female' => 2])
        ->and($captured['medals'])->toBe(['gold' => 1, 'silver' => null, 'bronze' => null]);
});

it('orders rows men, women, mixed then by event name', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $assoc->id])->id]);
    foreach ([['Team', EventGender::Mixed], ['Women A', EventGender::Women], ['Men B', EventGender::Men], ['Men A', EventGender::Men]] as [$n, $g]) {
        GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
            'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => $n, 'gender' => $g])->id]);
    }
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);
    $captured = null;
    view()->composer('pdf.entry-by-event', function ($v) use (&$captured) {
        $captured = array_column($v->getData()['rows'], 'event');
    });

    (new GenerateEntryByEventPdfs($s))->handle();

    expect($captured)->toBe(['Men A', 'Men B', 'Women A', 'Team']);
});
