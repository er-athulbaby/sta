<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReceived extends Notification implements ShouldQueue
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
        $assoc = $s->association->name;

        return (new MailMessage)
            ->subject("{$assoc} submitted Phase {$s->phase} for {$game} ({$s->reference_no})")
            ->line("{$assoc} submitted Phase {$s->phase} for {$game}. Reference: {$s->reference_no}.")
            ->action('Open admin', url('/admin'));
    }
}
