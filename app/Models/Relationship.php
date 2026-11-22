<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** STA-managed list for the person form's emergency contact and guardian relationship (stored there as the name). */
class Relationship extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return array<string, string> active names in list order, plus $current when it is a saved value that is not on the active list */
    public static function options(?string $current = null): array
    {
        $names = static::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
        if (filled($current) && ! in_array($current, $names, true)) {
            $names[] = $current;
        }

        return array_combine($names, $names);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
