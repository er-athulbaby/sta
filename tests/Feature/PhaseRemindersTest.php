<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PhaseReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create(['reminder_days' => [7, 3, 0]]);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-12-10 23:59:00']);
    $this->a = Association::factory()->create();
    $this->game->associations()->attach($this->a);
    $this->user = User::factory()->assocAdmin($this->a)->create();
});

it('reminds on each configured day before the deadline', function (string $today, bool $sent) {
    Carbon::setTestNow(Carbon::parse($today.' 08:00'));
    $this->artisan('phases:send-reminders')->assertSuccessful();
    $sent ? Notification::assertSentTo($this->user, PhaseReminder::class) : Notification::assertNothingSent();
})->with([
    '7 days before' => ['2026-12-03', true],
    '5 days before' => ['2026-12-05', false],
    '3 days before' => ['2026-12-07', true],
    'deadline day' => ['2026-12-10', true],
    'overdue' => ['2026-12-12', true],
]);

it('skips submitted associations, closed phases and uses the association extension', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-07 08:00'));
    $s = Submission::for($this->game, $this->a, 2);
    $s->update(['deadline_override' => '2026-12-20 23:59:00']); // 13 days left -> no reminder
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();

    $s->update(['deadline_override' => null, 'status' => SubmissionStatus::Submitted]);
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();

    $s->update(['status' => SubmissionStatus::Draft]);
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();
});
