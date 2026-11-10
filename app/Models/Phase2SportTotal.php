<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Phase 2 participants (distinct athletes) and expected medals per submission and game sport (spec 16.2). */
class Phase2SportTotal extends Model
{
    use LogsActivity;

    /** field => maximum digits: participants 0–999, medals 0–99. */
    public const DIGITS = ['male' => 3, 'female' => 3, 'gold' => 2, 'silver' => 2, 'bronze' => 2];

    public const LABELS = ['male' => 'Male participants', 'female' => 'Female participants',
        'gold' => 'Gold medals', 'silver' => 'Silver medals', 'bronze' => 'Bronze medals'];

    protected $table = 'phase2_sport_totals';

    protected $fillable = ['submission_id', 'game_sport_id', 'male', 'female', 'gold', 'silver', 'bronze'];

    protected function casts(): array
    {
        return ['male' => 'integer', 'female' => 'integer', 'gold' => 'integer', 'silver' => 'integer', 'bronze' => 'integer'];
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
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
