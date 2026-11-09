<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GameSport extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['game_id', 'sport_id', 'p2_max_men', 'p2_max_women', 'p2_max_total', 'p4_max_men', 'p4_max_women',
        'p4_max_officials', 'dob_from', 'dob_to', 'entry_policy'];

    protected function casts(): array
    {
        return ['dob_from' => 'date', 'dob_to' => 'date'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
