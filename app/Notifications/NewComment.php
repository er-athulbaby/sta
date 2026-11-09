<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\GamePhase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->comment->submission;
        $game = $s->game->name;

        return (new MailMessage)
            ->subject("New STA comment on Phase {$s->phase} for {$game}")
            ->line("STA commented on {$s->association->name}'s Phase {$s->phase} (".GamePhase::NAMES[$s->phase].") entry for {$game}:")
            ->line($this->comment->body)
            ->action('Open games', url('/portal'));
    }
}
