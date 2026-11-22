<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GamePhase extends Model
{
    use HasFactory, LogsActivity;

    public const NAMES = [1 => 'Entry by Sport', 2 => 'Entry by Number', 3 => 'Long List', 4 => 'Short List'];

    protected $fillable = ['game_id', 'phase', 'enabled', 'status', 'opens_at', 'deadline'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'status' => PhaseStatus::class, 'opens_at' => 'datetime', 'deadline' => 'datetime'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function effectiveDeadline(?Submission $s = null): ?CarbonInterface
    {
        return $s?->deadline_override ?? $this->deadline;
    }

    /**
     * Open for this association: the phase is open, or STA re-opened a closed phase for this association only (M6) and its
     * own deadline has not passed. The deadline of an open phase is checked separately (PhaseGate).
     */
    public function isOpenFor(?Submission $s): bool
    {
        return $this->status === PhaseStatus::Open || $this->isReopenedFor($s);
    }

    /** A closed phase re-opened for one association, until that association's own deadline. */
    public function isReopenedFor(?Submission $s): bool
    {
        return $this->status === PhaseStatus::Closed && $s?->deadline_change === 'reopened' && $s->deadline_override?->isFuture();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
