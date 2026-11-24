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

    /** Free text "Expected achievement", e.g. "Reach the semi-final"; required on submit when no medal is expected. */
    public const ACHIEVEMENT_MAX = 500;

    protected $table = 'phase2_sport_totals';

    protected $fillable = ['submission_id', 'game_sport_id', 'male', 'female', 'gold', 'silver', 'bronze', 'expected_achievement'];

    protected function casts(): array
    {
        return ['male' => 'integer', 'female' => 'integer', 'gold' => 'integer', 'silver' => 'integer', 'bronze' => 'integer'];
    }

    /** The submit error for a sport's expected medals / achievement, or null when complete (0 medals is a valid answer). */
    public static function expectationError(string $sport, ?self $t): ?string
    {
        if ($t?->gold === null || $t->silver === null || $t->bronze === null) {
            return "{$sport}: enter expected medals (0 if none).";
        }
        if ($t->gold + $t->silver + $t->bronze === 0 && blank($t->expected_achievement)) {
            return "{$sport}: describe the expected achievement when no medal is expected.";
        }

        return null;
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
