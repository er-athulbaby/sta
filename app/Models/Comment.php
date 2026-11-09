<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An STA comment on a submission; associations read them, they cannot reply. */
class Comment extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'body'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        // STA authors sit outside the association's tenant scope
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
