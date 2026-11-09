<?php

namespace App\Models;

use App\Enums\InterestAnswerValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InterestAnswer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['submission_id', 'game_sport_id', 'answer'];

    protected function casts(): array
    {
        return ['answer' => InterestAnswerValue::class];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function gameSport(): BelongsTo
    {
        return $this->belongsTo(GameSport::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
