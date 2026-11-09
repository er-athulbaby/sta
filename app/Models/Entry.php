<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A Long List row: an athlete in a game event, or a team official in a game sport (game_event_id null). */
class Entry extends Model
{
    use LogsActivity;

    protected $fillable = ['submission_id', 'person_id', 'game_event_id', 'game_sport_id'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function gameEvent(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class);
    }

    public function gameSport(): BelongsTo
    {
        return $this->belongsTo(GameSport::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
