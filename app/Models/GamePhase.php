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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
