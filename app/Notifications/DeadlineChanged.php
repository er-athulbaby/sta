<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeadlineChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission, public bool $reopened = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission;
        $game = $s->game->name;
        $deadline = $s->gamePhase()->effectiveDeadline($s);

        $mail = (new MailMessage)
            ->subject($this->reopened
                ? "Phase {$s->phase} for {$game} has been re-opened"
                : "Phase {$s->phase} deadline for {$game} extended")
            ->line("Phase {$s->phase} of {$game} for {$s->association->name} ".($this->reopened ? 'has been re-opened.' : 'has a new deadline.'));
        if ($deadline) {
            $mail->line('New deadline: '.$deadline->format('d M Y H:i'));
        }

        return $mail->action('Open games', url('/portal'));
    }
}
