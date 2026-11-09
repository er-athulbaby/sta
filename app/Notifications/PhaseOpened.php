<?php

namespace App\Notifications;

use App\Models\GamePhase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhaseOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GamePhase $phase) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->phase;
        $name = 'Phase '.$p->phase.' – '.GamePhase::NAMES[$p->phase];

        return (new MailMessage)
            ->subject("{$name} is open for {$p->game->name}")
            ->line("{$name} of {$p->game->name} is now open for entries.")
            ->line('Deadline: '.$p->deadline->timezone('Asia/Bahrain')->format('d M Y H:i'))
            ->action('Open the portal', url('/portal'));
    }
}
