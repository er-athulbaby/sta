<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\StaleSubmission;
use Carbon\Carbon;

function gatedSubmission(array $phase = [], array $sub = []): Submission
{
    $game = Game::factory()->create();
    $game->phase(2)->update(array_merge(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-12-01 23:59:00'], $phase));
    $s = Submission::for($game, Association::factory()->create(), 2);
    $s->update($sub);

    return $s->fresh();
}

it('allows editing an open, enabled phase before the deadline', function () {
    expect(app(PhaseGate::class)->denialReason(gatedSubmission(), Carbon::parse('2026-11-20')))->toBeNull();
});

it('explains why editing is refused', function (array $phase, array $sub, string $now, string $reason) {
    expect(app(PhaseGate::class)->denialReason(gatedSubmission($phase, $sub), Carbon::parse($now)))->toBe($reason);
})->with([
    'disabled' => [['enabled' => false], [], '2026-11-20', 'This phase is not used for this game.'],
    'draft phase' => [['status' => PhaseStatus::Draft], [], '2026-11-20', 'This phase is not open yet.'],
    'closed' => [['status' => PhaseStatus::Closed], [], '2026-11-20', 'This phase is closed.'],
    'past deadline' => [[], [], '2026-12-02', 'The deadline has passed.'],
    'awaiting' => [[], ['status' => SubmissionStatus::AwaitingAssocAdmin], '2026-11-20', 'This entry has been sent for approval.'],
    'submitted' => [[], ['status' => SubmissionStatus::Submitted], '2026-11-20', 'This entry has been submitted.'],
]);

it('lets an extension override the phase deadline', function () {
    $s = gatedSubmission([], ['deadline_override' => '2026-12-05 23:59:00']);
    expect(app(PhaseGate::class)->canEdit($s, Carbon::parse('2026-12-03')))->toBeTrue();
});

it('refuses a stale save and claims the version on success', function () {
    $s = gatedSubmission();
    $gate = app(PhaseGate::class);
    $other = Submission::find($s->id);

    $gate->assertFresh($other, 0);
    expect($other->fresh()->lock_version)->toBe(1);

    expect(fn () => $gate->assertFresh($s, 0))->toThrow(StaleSubmission::class);
    expect($s->fresh()->lock_version)->toBe(1);

    $gate->assertFresh($s->fresh(), 1);
    expect($s->fresh()->lock_version)->toBe(2);
});

it('lets only one of two saves from the same loaded version through, within the same second', function () {
    $s = gatedSubmission();
    $gate = app(PhaseGate::class);

    $gate->assertFresh($s, 0);
    expect(fn () => $gate->assertFresh($s, 0))->toThrow(StaleSubmission::class);
});
