<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
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
use App\Support\PdfContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/** Positions of each needle in $html, to assert the sample's section order. */
function pdfOrder(string $html, array $needles): array
{
    return array_map(fn ($n) => strpos($html, $n), $needles);
}

it('prints X marks, notes, and the prepared and approved block on Entry by Sport in the sample order', function () {
    $html = view('pdf.entry-by-sport', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']),
        'association' => Association::factory()->make(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']),
        'rows' => [
            ['sport' => 'Weightlifting', 'answer' => 'Yes', 'value' => 'yes', 'note' => 'Male and female'],
            ['sport' => 'Judo', 'answer' => 'No response', 'value' => null, 'note' => null],
        ],
        'referenceNo' => 'BWF-G001-P1-R1', 'declaration' => 'I confirm.', 'nocLogo' => PdfContext::bocLogo(), 'gameLogo' => null, 'assocLogo' => null,
        'phase' => 1, 'preparedBy' => 'Mariam Khalil, Association Editor · 2 Sep 2026', 'approvedBy' => 'Ahmed Saleh, Association Admin · 3 Sep 2026', 'deadline' => '5 Sep 2026',
    ])->render();
    $order = ['National Olympic Committee of Bahrain', 'Phase 1</span>', 'Association</span>', 'For each sport you were invited to',
        '>Sport</th>', '>Yes</th>', '>No</th>', '>Maybe</th>', '>Note</th>', '>Total</th>', 'Prepared by', 'Approved by', 'Phase 1 deadline',
        'Declaration.', '>Name</td>', '>Position</td>', 'Stamp', '>Signature</td>', '>Date</td>'];

    expect($html)->toContain('chip-1')->toContain('Entry by Sport')->toContain('>X<')->toContain('Male and female')->toContain('No response')
        ->toContain('2 sports invited')->toContain('Prepared by')->toContain('Mariam Khalil, Association Editor · 2 Sep 2026')
        ->toContain('Phase 1 deadline')->toContain('Bahrain Olympic Committee')->toContain('data:image/png;base64,')
        ->and(pdfOrder($html, $order))->not->toContain(false)
        ->and(pdfOrder($html, $order))->toBe(collect(pdfOrder($html, $order))->sort()->values()->all());
});

it('prints Allowed, grey cells, entry totals and the boxes on Entry by Event in the sample order', function () {
    $html = view('pdf.entry-by-event', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']), 'association' => Association::factory()->make(['code' => 'BWF']),
        'gameSport' => GameSport::factory()->make(['entry_policy' => null, 'dob_to' => '2011-12-31']), 'sportName' => 'Weightlifting',
        'rows' => [['event' => '60kg', 'gender' => 'men', 'count' => 2, 'max' => 2], ['event' => '49kg', 'gender' => 'women', 'count' => 0, 'max' => null]],
        'totals' => ['men' => 2, 'women' => 0, 'total' => 2], 'participants' => ['male' => 2, 'female' => 0], 'medals' => ['gold' => 1, 'silver' => null, 'bronze' => null],
        'referenceNo' => 'BWF-G001-P2-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null, 'assocLogo' => null,
    ])->render();
    $order = ['chip chip-2', 'Entry by Event — Weightlifting', '>Event</th>', '>Allowed</th>', '>Male</th>', '>Female</th>', 'Up to 2', 'Event entries',
        'All events (incl. mixed)', 'Grey cells: event not open to that gender.', 'Total number of participants', 'Expected medals',
        'Participants are different athletes', 'Entry policy', 'Age eligibility', 'Declaration.', 'Stamp'];

    expect($html)->toContain('class="num off"')->toContain('2 entries')->toContain('Born to: 31 Dec 2011')->toContain('Born from: no lower limit')
        ->toContain('Each event allows the number of athletes shown above.')->not->toContain('Prepared by')
        ->and(pdfOrder($html, $order))->toBe(collect(pdfOrder($html, $order))->sort()->values()->all())
        ->and(pdfOrder($html, $order))->not->toContain(false);
});

it('prints the one-row header, boxed summary, S.No tables, justification and signature row on the Short List', function () {
    $html = view('pdf.long-list', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']), 'association' => Association::factory()->make(['name' => 'Bahrain Judo Federation', 'code' => 'BJF']),
        'athleteItems' => ['Suit', 'T-Shirt'],
        'athletes' => [['photo' => null, 'name' => 'Ali Hasan', 'sport' => 'Judo', 'events' => '-60kg', 'dob' => '10 Mar 2005', 'sizes' => ['', 'L'], 'passport' => true]],
        'officials' => [], 'summary' => ['male' => 1, 'female' => 0, 'officials' => 0],
        'referenceNo' => 'BJF-G001-P4-R1', 'declaration' => 'I confirm.', 'nocLogo' => PdfContext::bocLogo(), 'gameLogo' => null, 'assocLogo' => null,
        'title' => 'Short List', 'justification' => 'Two medal chances', 'overLimits' => ['Judo — Male: 4 of max 3'],
        'submittedLine' => 'Submitted 5 Nov 2026 by Mariam Khalil, Association Editor',
    ])->render();
    $order = ['Bahrain Olympic Committee', 'chip chip-4', '<h1', 'Asian Indoor Games', 'Bahrain Judo Federation (BJF)', '· Judo', 'Summary</td>', 'Athletes Male <strong>1</strong>',
        'Athletes Female', 'Athletes Total', 'Team officials <strong>0</strong>', 'Submitted 5 Nov 2026 by Mariam Khalil', '<h3>Athletes</h3>', 'S.No', '<h3>Team officials</h3>',
        'Justification (over limit)', 'Limit exceeded: Judo — Male: 4 of max 3', 'Two medal chances', 'Declaration.', '>Name</td>', '>Position</td>',
        '>Signature</td>', '>Date</td>', 'Stamp</td>'];

    expect(pdfOrder($html, $order))->not->toContain(false)
        ->and(pdfOrder($html, $order))->toBe(collect(pdfOrder($html, $order))->sort()->values()->all())
        ->and($html)->toContain('<td class="c">—</td>')->toContain('No team officials.');
});

it('builds the PDF context from the submission', function () {
    $assoc = Association::factory()->create(['code' => 'BWF', 'logo_path' => 'logos/missing.png']);
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-09-05 18:00']);
    $editor = User::factory()->assocEditor($assoc)->create(['name' => 'Mariam Khalil']);
    $admin = User::factory()->assocAdmin($assoc)->create(['name' => 'Ahmed Saleh']);
    $s = Submission::for($game, $assoc, 1);
    $s->update(['submitted_by' => $editor->id, 'submitted_at' => '2026-09-02 10:00', 'approved_by' => $admin->id, 'approved_at' => '2026-09-03 09:00']);

    $ctx = PdfContext::for($s->fresh());

    expect($ctx['phase'])->toBe(1)
        ->and($ctx['preparedBy'])->toBe('Mariam Khalil, Association Editor · 2 Sep 2026')
        ->and($ctx['approvedBy'])->toBe('Ahmed Saleh, Association Admin · 3 Sep 2026')
        ->and($ctx['deadline'])->toBe('5 Sep 2026')
        ->and($ctx['submittedLine'])->toBe('Submitted 2 Sep 2026 by Mariam Khalil, Association Editor')
        ->and($ctx['assocLogo'])->toBeNull()
        ->and($ctx['nocLogo'])->toStartWith('data:image/png;base64,');
});

it('formats the header dates as on the samples', function () {
    expect(PdfContext::dates(Carbon::parse('2026-11-20'), Carbon::parse('2026-11-30')))->toBe('20 – 30 Nov 2026')
        ->and(PdfContext::dates(Carbon::parse('2026-11-28'), Carbon::parse('2026-12-03')))->toBe('28 Nov – 3 Dec 2026')
        ->and(PdfContext::dates(Carbon::parse('2026-12-30'), Carbon::parse('2027-01-02')))->toBe('30 Dec 2026 – 2 Jan 2027')
        ->and(PdfContext::dates(null, null))->toBe('');
});

it('passes the exceeded limits to the Short List PDF', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BJF']);
    $game = Game::factory()->create();
    $game->associations()->attach($assoc);
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'p4_max_men' => 0,
        'sport_id' => Sport::factory()->create(['association_id' => $assoc->id, 'name' => 'Judo'])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'gender' => EventGender::Men])->id]);
    $ali = Person::factory()->create(['association_id' => $assoc->id, 'gender' => PersonGender::Male]);
    $p4 = Submission::for($game, $assoc, 4);
    $p4->entries()->create(['person_id' => $ali->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);
    $p4->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P4-R1', 'revision' => 1, 'justification' => 'Why']);
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateShortListPdf($p4))->handle();

    expect($captured['overLimits'])->toBe(['Judo — Male: 1 of max 0'])->and($captured['phase'])->toBe(4);
});
