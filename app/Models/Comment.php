<?php

namespace App\Models;

use App\Enums\StaAction;
use App\Enums\StaArea;
use App\Notifications\NewComment;
use App\Services\Phases\Recipients;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;

/** A remark on a submission: STA writes remarks, the association's admin and editors reply (from_association). */
class Comment extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'from_association', 'body'];

    protected function casts(): array
    {
        return ['from_association' => 'boolean'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        // STA authors sit outside the association's tenant scope
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Stores a remark (STA author) or reply (association author) and, for a published game, notifies the other side by bell
     * and email: remarks go to the association's active users, replies to STA users with edit_submissions.
     */
    public static function post(Submission $s, User $author, string $body): self
    {
        $reply = $author->association_id !== null;
        $comment = $s->comments()->create(['user_id' => $author->id, 'from_association' => $reply, 'body' => $body]);
        if ($s->game->isPublished()) {
            Notification::send($reply ? Recipients::sta(StaArea::Submissions, StaAction::Save) : Recipients::association($s->association), new NewComment($comment));
        }

        return $comment;
    }
}
