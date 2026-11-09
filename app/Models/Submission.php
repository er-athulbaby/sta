<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToAssociation;
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
            'approved_at' => 'datetime', 'pdf_paths' => 'array'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InterestAnswer::class);
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(NumberEntry::class);
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
        return static::withoutGlobalScopes()->firstOrCreate(
            ['game_id' => $game->id, 'association_id' => $association->id, 'phase' => $phase],
            ['status' => SubmissionStatus::Draft],
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
