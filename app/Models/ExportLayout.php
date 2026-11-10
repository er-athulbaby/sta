<?php

namespace App\Models;

use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A game system upload file layout (spec 15.2): one per game and person type; columns are validated by ExportSources. */
class ExportLayout extends Model
{
    use LogsActivity;

    public const FORMATS = ['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (UTF-8, comma)'];

    public const ROW_MODES = ['one_per_athlete' => 'One row per person, events joined by "; "', 'one_per_event' => 'One row per person per event'];

    protected $fillable = ['game_id', 'type', 'file_format', 'row_mode', 'columns', 'zip_pattern'];

    protected $attributes = ['file_format' => 'xlsx', 'row_mode' => 'one_per_athlete', 'columns' => '[]', 'zip_pattern' => '{code}_{kind}'];

    protected function casts(): array
    {
        return ['type' => PersonType::class, 'columns' => 'array'];
    }

    public static function for(Game $game, PersonType $type): self
    {
        return static::firstOrNew(['game_id' => $game->id, 'type' => $type->value]);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
