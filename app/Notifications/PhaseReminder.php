<?php

namespace App\Notifications;

use App\Models\Association;
use App\Models\GamePhase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhaseReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GamePhase $phase, public Association $association, public bool $overdue) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->phase;
        $game = $p->game->name;
        $name = GamePhase::NAMES[$p->phase];
        $submission = $p->game->submissions()->withoutGlobalScopes()
            ->where('association_id', $this->association->id)->where('phase', $p->phase)->first();
        $deadline = $p->effectiveDeadline($submission);

        $mail = (new MailMessage)->subject($this->overdue
            ? "Overdue: Phase {$p->phase} – {$name} for {$game}"
            : "Reminder: Phase {$p->phase} – {$name} for {$game} is due ".($deadline?->format('d M Y') ?? 'soon'))
            ->line("Phase {$p->phase} ({$name}) of {$game} has not yet been submitted for {$this->association->name}.");
        if ($deadline) {
            $mail->line('Deadline: '.$deadline->format('d M Y H:i'));
        }

        return $mail->action('Open games', url('/portal'));
    }
}
