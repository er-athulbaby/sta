<?php

namespace App\Notifications;

use App\Filament\Portal\Pages\Games;
use App\Filament\Resources\Games\GameResource;
use App\Models\Comment;
use App\Models\GamePhase;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** An STA remark (to the association) or an association reply (to STA): bell + email, both linking to the submission. */
class NewComment extends Notification implements ShouldQueue
{
    use Queueable;

    /** A remark or reply deleted before the job runs: drop the job instead of failing it. */
    public bool $deleteWhenMissingModels = true;

    private ?Submission $loadedSubmission = null;

    public function __construct(public Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission();
        $game = $s->game->name;
        $phase = "Phase {$s->phase} (".GamePhase::NAMES[$s->phase].')';

        return (new MailMessage)
            ->subject($this->title())
            ->line($this->comment->from_association
                ? "{$this->comment->user?->name} of {$s->association->name} replied on their {$phase} entry for {$game}:"
                : "STA left a remark on {$s->association->name}'s {$phase} entry for {$game}:")
            ->line(self::plainText($this->comment->body))
            ->action($this->comment->from_association ? 'Open submission' : 'Open the phase page', $this->url());
    }

    /** Filament's bell format, plus submission_id so opening the submission marks it read. The bell only sanitises HTML, so escape. */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(e($this->title()))
            ->body(e(Str::limit($this->comment->body, 200)))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->actions([Action::make('open')->label('Open')->url($this->url())->markAsRead()])
            ->getDatabaseMessage() + ['submission_id' => $this->comment->submission_id, 'comment_id' => $this->comment->id];
    }

    /** Opening the submission (STA view or phase page) marks the user's remark/reply bells for it as read. */
    public static function markRead(object $user, int $submissionId): void
    {
        $user->unreadNotifications()->where('type', static::class)->where('data->submission_id', $submissionId)->update(['read_at' => now()]);
    }

    /** Mail lines are Markdown: backslash-escape its syntax so a written body never becomes a link, image or heading. */
    public static function plainText(string $body): string
    {
        return preg_replace('/([\\\\`*_\[\]()#>!])/', '\\\\$1', $body);
    }

    /** Loaded once per job: title(), url() and toMail() share it. */
    private function submission(): Submission
    {
        return $this->loadedSubmission ??= Submission::withoutGlobalScopes()->with(['game', 'association'])->findOrFail($this->comment->submission_id);
    }

    private function title(): string
    {
        $s = $this->submission();

        return $this->comment->from_association
            ? "{$s->association->name} replied on Phase {$s->phase} for {$s->game->name}"
            : "New STA remark on Phase {$s->phase} for {$s->game->name}";
    }

    /** Replies open the STA submission view; remarks open the association's phase page. */
    private function url(): string
    {
        $s = $this->submission();
        if ($this->comment->from_association) {
            return GameResource::getUrl('submission', ['record' => $s->game_id, 'submission' => $s->id], panel: 'admin');
        }

        return Games::phasePage($s->phase)::getUrl(['game' => $s->game_id], panel: 'portal', tenant: $s->association);
    }
}
