<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReturned extends Notification implements ShouldQueue
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
            ->subject("Phase {$s->phase} entry for {$game} was returned to draft")
            ->line("The Phase {$s->phase} entry of {$s->association->name} for {$game} was returned to draft for changes.")
            ->action('Open games', url('/portal'));
    }
}
