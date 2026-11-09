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
        GamePhase::query()->where('enabled', true)->where('status', PhaseStatus::Open)->whereNotNull('deadline')
            ->with('game.associations')->each(function (GamePhase $phase) use ($today, &$sent) {
                foreach ($phase->game->associations as $association) {
                    $s = Submission::withoutGlobalScopes()->where(['game_id' => $phase->game_id, 'association_id' => $association->id, 'phase' => $phase->phase])->first();
                    if ($s?->status === SubmissionStatus::Submitted) {
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
