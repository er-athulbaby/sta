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

it('builds a page heading with chip and pill', function () {
    expect((string) Sta::heading('Long List', 3, 'Phase 3', 'draft', 'Draft'))
        ->toContain('Long List')->toContain('sta-ph-3')->toContain('sta-pill-draft');
});
