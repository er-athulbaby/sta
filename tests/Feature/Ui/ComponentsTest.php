<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Support\Sta;
use Illuminate\Support\Facades\Blade;

it('renders the phase chip, pill, KPI tile and meter with the canvas classes', function () {
    $html = Blade::render('<x-sta.phase-chip :phase="3" /><x-sta.pill status="submitted">Submitted</x-sta.pill>'
        .'<x-sta.kpi label="Submitted to STA" value="31" hint="of 50 associations" tone="submitted" />'
        .'<x-sta.meter label="Male participants" :value="5" :max="4" />');

    expect($html)->toContain('sta-phase-chip sta-ph-3')->toContain('>Phase 3<')
        ->toContain('sta-pill sta-pill-submitted')->toContain('Submitted to STA')->toContain('sta-tone-submitted')
        ->toContain('is-over')->toContain('5 of 4')->toContain('aria-valuemax="4"');
});

it('maps phase and submission states to canvas pills', function () {
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => false]);
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open]);
    $game->refresh();

    expect(Sta::phaseStatus($game->phase(1)))->toBe(['Skipped', 'skipped'])
        ->and(Sta::phaseStatus($game->phase(3)))->toBe(['Open', 'open'])
        ->and(Sta::submissionPill(SubmissionStatus::AwaitingAssocAdmin))->toBe('awaiting')
        ->and(Sta::cellPill('no_response'))->toBe('none')
        ->and(Sta::daysLeft(now()->addDays(6)->addHour()))->toBe('6 days left')
        ->and(Sta::daysLeft(now()->subDay()))->toBe('Overdue');
});

it('draws an association avatar from its code when there is no logo', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Judo Federation', 'code' => 'BJF']);

    expect(Blade::render('<x-sta.assoc-avatar :association="$a" />', ['a' => $a]))
        ->toContain('sta-avatar')->toContain('>BJF<')->toContain(Sta::avatarColour('BJF'));
});

it('marks the current step and skipped phases in the stepper', function () {
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => false]);

    $html = Blade::render('<x-sta.stepper :game="$g" :current="2" />', ['g' => $game->fresh('phases')]);

    expect($html)->toContain('aria-current="step"')->toContain('1 · Entry by Sport — skipped')->toContain('4 · Short List');
});

it('maps every cell, submission and phase state', function () {
    foreach (['submitted' => 'submitted', 'awaiting' => 'awaiting', 'draft' => 'draft', 'overdue' => 'overdue', 'no_response' => 'none', 'bogus' => 'none'] as $in => $out) {
        expect(Sta::cellPill($in))->toBe($out);
    }
    expect(Sta::submissionPill(SubmissionStatus::Submitted))->toBe('submitted')
        ->and(Sta::submissionPill(SubmissionStatus::AwaitingAssocAdmin))->toBe('awaiting')
        ->and(Sta::submissionPill(SubmissionStatus::Draft))->toBe('draft');

    $game = Game::factory()->create();
    $p = $game->phase(2);
    foreach ([[PhaseStatus::Draft, ['Not opened', 'none']], [PhaseStatus::Open, ['Open', 'open']], [PhaseStatus::Closed, ['Closed', 'closed']]] as [$s, $want]) {
        $p->update(['enabled' => true, 'status' => $s]);
        expect(Sta::phaseStatus($p->fresh()))->toBe($want);
    }
    $p->update(['enabled' => false]);
    expect(Sta::phaseStatus($p->fresh()))->toBe(['Skipped', 'skipped']);
});

it('words the days left', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    expect(Sta::daysLeft(null))->toBeNull()
        ->and(Sta::daysLeft(now()->subHour()))->toBe('Overdue')
        ->and(Sta::daysLeft(now()->addHours(3)))->toBe('Due today')
        ->and(Sta::daysLeft(now()->addDay()->addHour()))->toBe('1 day left')
        ->and(Sta::daysLeft(now()->addDays(5)->addHour()))->toBe('5 days left');
});

it('escapes user text in every component', function () {
    $x = '<script>x</script>"quote"';
    $a = Association::factory()->create(['name' => $x, 'code' => $x]);
    $html = Blade::render(
        '<x-sta.pill status="draft">{{ $x }}</x-sta.pill><x-sta.kpi :label="$x" value="1" :hint="$x" />'
        .'<x-sta.meter :label="$x" :value="1" :max="2" /><x-sta.person-avatar :name="$x" /><x-sta.assoc-avatar :association="$a" />'
        .'<x-sta.phase-card :phase="1" :name="$x" status="draft" :status-label="$x" :meta="$x" />'
        .'<x-sta.card :heading="$x" :description="$x">body</x-sta.card>',
        ['x' => $x, 'a' => $a]);

    expect($html)->not->toContain('<script>x</script>')->not->toContain('"quote"')->toContain('&lt;script&gt;x&lt;/script&gt;');
});

it('renders card, phase card, person avatar and the logo branch', function () {
    $a = Association::factory()->create(['name' => 'Judo', 'logo_path' => 'logos/x.png']);
    $html = Blade::render(
        '<x-sta.card heading="Sports" description="d" band="blue">body<x-slot:icon>I</x-slot:icon><x-slot:actions>ACT</x-slot:actions><x-slot:footer>FOOT</x-slot:footer></x-sta.card>'
        .'<x-sta.phase-card :phase="2" name="By Number" status="open" status-label="Open" meta="m" :active="true" />'
        .'<x-sta.person-avatar name="Ali Hassan" size="xl" /><x-sta.assoc-avatar :association="$a" size="lg" />',
        ['a' => $a]);

    expect($html)->toContain('sta-band sta-band-blue')->toContain('sta-band-icon')->toContain('ACT')->toContain('FOOT')
        ->toContain('is-active')->toContain('aria-current="true"')->toContain('sta-avatar-xl')->toContain('aria-label="Ali Hassan"')
        ->toContain('>AH<')->toContain('sta-logo sta-logo-lg')->toContain('Judo logo');
});

it('tolerates missing props', function () {
    $html = Blade::render('<x-sta.meter label="M" /><x-sta.meter label="Z" :value="3" :max="0" /><x-sta.kpi label="K" /><x-sta.assoc-avatar /><x-sta.phase-card :phase="1" />'
        .'<x-sta.meter label="O" :value="5" :max="4" />');

    expect($html)->not->toContain('aria-valuemax="0"')->toContain('aria-valuetext="5 of 4"');
});
