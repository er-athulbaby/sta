<?php

namespace App\Console\Commands;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\PhaseReminder;
use App\Services\Phases\Recipients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPhaseReminders extends Command
{
    protected $signature = 'phases:send-reminders';

    protected $description = 'Email associations that have not submitted an open phase, on the game\'s reminder days and daily while overdue';

    public function handle(): int
    {
        $today = now()->startOfDay(); // app time zone is Asia/Bahrain
        $sent = 0;
        GamePhase::query()->where('enabled', true)->whereNotNull('deadline')
            // Closed phases only when re-opened for an association whose own deadline is still ahead (M6).
            ->where(fn ($q) => $q->where('status', PhaseStatus::Open)->orWhere(fn ($q) => $q->where('status', PhaseStatus::Closed)
                ->whereExists(fn ($e) => $e->from('submissions')->whereColumn('submissions.game_id', 'game_phases.game_id')
                    ->whereColumn('submissions.phase', 'game_phases.phase')->where('submissions.deadline_change', 'reopened')
                    ->where('submissions.deadline_override', '>', now()))))
            ->whereHas('game', fn ($q) => $q->whereNotNull('published_at'))
            ->with('game.associations')->each(function (GamePhase $phase) use ($today, &$sent) {
                foreach ($phase->game->associations as $association) {
                    $s = Submission::withoutGlobalScopes()->where(['game_id' => $phase->game_id, 'association_id' => $association->id, 'phase' => $phase->phase])->first();
                    // A closed phase only reminds an association it was re-opened for (M6).
                    if ($s?->status === SubmissionStatus::Submitted || ! $phase->isOpenFor($s)) {
                        continue;
                    }
                    $deadlineDay = $phase->effectiveDeadline($s)->copy()->startOfDay();
                    $daysLeft = (int) $today->diffInDays($deadlineDay, false);
                    $overdue = $daysLeft < 0;
                    if ($overdue || in_array($daysLeft, $phase->game->reminder_days ?? [], true)) {
                        Notification::send(Recipients::association($association), new PhaseReminder($phase, $association, $overdue));
                        $sent++;
                    }
                }
            });
        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
