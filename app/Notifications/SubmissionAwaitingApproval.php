<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission;
        $game = $s->game->name;

        return (new MailMessage)
            ->subject("Phase {$s->phase} entry for {$game} is waiting for your approval")
            ->line("{$s->association->name} has a Phase {$s->phase} entry for {$game} waiting for your approval.")
            ->action('Open games', url('/portal'));
    }
}
