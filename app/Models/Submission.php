<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToAssociation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Submission extends Model
{
    use BelongsToAssociation, HasFactory, LogsActivity;

    protected $fillable = ['game_id', 'association_id', 'phase', 'status', 'reference_no', 'revision', 'deadline_override',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'pdf_paths', 'justification'];

    protected function casts(): array
    {
        return ['status' => SubmissionStatus::class, 'deadline_override' => 'datetime', 'submitted_at' => 'datetime',
            'approved_at' => 'datetime', 'pdf_paths' => 'array', 'lock_version' => 'integer'];
    }

    /**
     * Atomically claims the next version: succeeds only if the row still holds $loaded.
     * One conditional UPDATE, so concurrent saves cannot both pass.
     */
    public function claimVersion(int $loaded): bool
    {
        $n = static::withoutGlobalScope('association')->whereKey($this->id)
            ->where('lock_version', $loaded)->increment('lock_version');

        if ($n === 1) {
            $this->lock_version = $loaded + 1;
        }

        return $n === 1;
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InterestAnswer::class);
    }

    public function sportTotals(): HasMany
    {
        return $this->hasMany(Phase2SportTotal::class);
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(NumberEntry::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(LongListDetail::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return Collection<int, Comment> newest first, author loaded */
    public function commentsNewestFirst(): Collection
    {
        return $this->comments()->with('user')->latest('id')->get();
    }

    public function gamePhase(): GamePhase
    {
        return $this->game->phase($this->phase);
    }

    /**
     * Skips the association global scope on purpose: callers have already checked access
     * (portal pages pass the tenant, admin pages hold the permission).
     */
    public static function for(Game $game, Association $association, int $phase): self
    {
        $user = auth()->user();
        if ($user instanceof User && $user->role->isAssociationRole() && $user->association_id !== $association->id) {
            abort(404);
        }

        return static::withoutGlobalScope('association')->firstOrCreate(
            ['game_id' => $game->id, 'association_id' => $association->id, 'phase' => $phase],
            ['status' => SubmissionStatus::Draft],
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
